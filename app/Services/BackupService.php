<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Measurement;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductService;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export, restore and deletion of the shop's records.
 *
 * Every data type the Backup & Data panel offers is declared once in `TYPES`,
 * so the counters, the export, the restore picker and the Danger Zone can
 * never disagree about what exists.
 */
class BackupService
{
    /**
     * label, model, icon and colour for each exportable type, in the order
     * they must be restored (parents before children).
     */
    public const TYPES = [
        'customers'    => ['label' => 'Customers',    'model' => Customer::class,      'icon' => 'fa-users',           'color' => 'text-indigo-500'],
        'products'     => ['label' => 'Products',     'model' => ProductService::class, 'icon' => 'fa-tags',           'color' => 'text-violet-500'],
        'measurements' => ['label' => 'Measurements', 'model' => Measurement::class,   'icon' => 'fa-ruler-combined',  'color' => 'text-teal-500'],
        'orders'       => ['label' => 'Orders',       'model' => Order::class,         'icon' => 'fa-box',             'color' => 'text-sky-500'],
        'payments'     => ['label' => 'Payments',     'model' => Payment::class,       'icon' => 'fa-file-invoice',    'color' => 'text-emerald-500'],
        'expenses'     => ['label' => 'Expenses',     'model' => Expense::class,       'icon' => 'fa-receipt',         'color' => 'text-amber-500'],
        'notifications' => ['label' => 'Notifications', 'model' => Notification::class, 'icon' => 'fa-bell',           'color' => 'text-rose-500'],
        'activity'     => ['label' => 'Activity Log', 'model' => ActivityLog::class,   'icon' => 'fa-clock-rotate-left', 'color' => 'text-slate-500'],
    ];

    public const FORMAT_VERSION = 1;

    /**
     * The type list as the browser needs it — label, icon and colour only.
     * Model class names stay on the server.
     *
     * @return array<string, array{label: string, icon: string, color: string}>
     */
    public static function typesForClient(): array
    {
        return collect(self::TYPES)
            ->map(fn (array $t) => ['label' => $t['label'], 'icon' => $t['icon'], 'color' => $t['color']])
            ->all();
    }

    /**
     * Live record counts for every type.
     *
     * @return array<string, int>
     */
    public static function counts(): array
    {
        $counts = [];

        foreach (self::TYPES as $key => $meta) {
            /** @var class-string<Model> $model */
            $model = $meta['model'];
            $counts[$key] = $model::query()->count();
        }

        return $counts;
    }

    /* ------------------------------------------------------------------ */
    /*  Export                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * The complete backup as a nested array. Rows are exported raw so a
     * restore reproduces them exactly.
     */
    public static function payload(): array
    {
        $data = [];

        foreach (self::TYPES as $key => $meta) {
            /** @var class-string<Model> $model */
            $model = $meta['model'];
            $data[$key] = $model::query()->orderBy('id')->get()->map->getAttributes()->all();
        }

        return [
            'format'      => 'atelier-backup',
            'version'     => self::FORMAT_VERSION,
            'exported_at' => now()->toIso8601String(),
            'shop'        => Settings::str('store_name'),
            'settings'    => Setting::query()->pluck('value', 'key')->all(),
            'counts'      => self::counts(),
            'data'        => $data,
        ];
    }

    public static function streamJson(): StreamedResponse
    {
        $filename = 'atelier-backup-' . now()->format('Y-m-d-His') . '.json';

        return response()->streamDownload(function () {
            echo json_encode(self::payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    /** Readable CSV export, one section per data type. */
    public static function streamCsv(): StreamedResponse
    {
        $filename = 'atelier-backup-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['# Atelier data export', now()->toDateTimeString()]);
            fputcsv($handle, ['# Shop', Settings::str('store_name')]);

            fputcsv($handle, []);
            fputcsv($handle, ['## SETTINGS']);
            fputcsv($handle, ['Key', 'Value']);
            foreach (Setting::map() as $key => $value) {
                // Never write a live API token into a file the user may share.
                $secret = !empty(Settings::SCHEMA[$key]['secret']);
                fputcsv($handle, [$key, $secret ? '[redacted]' : $value]);
            }

            foreach (self::TYPES as $key => $meta) {
                /** @var class-string<Model> $model */
                $model = $meta['model'];
                $columns = Schema::getColumnListing((new $model)->getTable());

                fputcsv($handle, []);
                fputcsv($handle, ['## ' . strtoupper($meta['label'])]);
                fputcsv($handle, $columns);

                $model::query()->orderBy('id')->chunk(500, function ($rows) use ($handle, $columns) {
                    foreach ($rows as $row) {
                        $attributes = $row->getAttributes();
                        fputcsv($handle, array_map(
                            fn ($c) => self::flatten($attributes[$c] ?? null),
                            $columns
                        ));
                    }
                });
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /* ------------------------------------------------------------------ */
    /*  Restore                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Validates a backup file and reports what it holds, without writing.
     *
     * @return array{valid: bool, message: string, exported_at?: string, shop?: string, counts?: array}
     */
    public static function inspect(string $path): array
    {
        $raw = @file_get_contents($path);

        if ($raw === false) {
            return ['valid' => false, 'message' => 'The file could not be read.'];
        }

        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            return ['valid' => false, 'message' => 'That is not valid JSON.'];
        }

        if (($payload['format'] ?? null) !== 'atelier-backup') {
            return ['valid' => false, 'message' => 'That file is not an Atelier backup.'];
        }

        if ((int) ($payload['version'] ?? 0) > self::FORMAT_VERSION) {
            return ['valid' => false, 'message' => 'That backup was made by a newer version of Atelier.'];
        }

        $counts = [];
        foreach (self::TYPES as $key => $meta) {
            $counts[$key] = is_array($payload['data'][$key] ?? null) ? count($payload['data'][$key]) : 0;
        }

        return [
            'valid'       => true,
            'message'     => 'Backup file read successfully.',
            'exported_at' => $payload['exported_at'] ?? null,
            'shop'        => $payload['shop'] ?? null,
            'has_settings' => !empty($payload['settings']),
            'counts'      => $counts,
        ];
    }

    /**
     * Writes selected types back into the database.
     *
     * `merge` upserts by primary key and leaves anything not in the file
     * alone. `replace` empties each selected table first. The whole run is one
     * transaction, so a malformed row can never leave a half-restored shop.
     *
     * @param array<int, string> $types
     * @return array{success: bool, message: string, imported?: array<string,int>, skipped?: array<string,int>}
     */
    public static function restore(string $path, array $types, string $mode = 'merge'): array
    {
        $check = self::inspect($path);

        if (!$check['valid']) {
            return ['success' => false, 'message' => $check['message']];
        }

        $payload = json_decode(file_get_contents($path), true);

        // Keep the declared order so parents land before their children.
        $types = array_values(array_intersect(array_keys(self::TYPES), $types));

        $imported = [];
        $skipped  = [];

        try {
            DB::transaction(function () use ($payload, $types, $mode, &$imported, &$skipped) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');

                foreach ($types as $type) {
                    $meta = self::TYPES[$type];
                    /** @var class-string<Model> $modelClass */
                    $modelClass = $meta['model'];
                    $model = new $modelClass;
                    $table = $model->getTable();

                    $rows = $payload['data'][$type] ?? [];
                    if (!is_array($rows)) {
                        $rows = [];
                    }

                    if ($mode === 'replace') {
                        DB::table($table)->delete();
                    }

                    $columns = Schema::getColumnListing($table);
                    $imported[$type] = 0;
                    $skipped[$type]  = 0;

                    foreach (array_chunk($rows, 200) as $chunk) {
                        $clean = [];

                        foreach ($chunk as $row) {
                            if (!is_array($row)) {
                                $skipped[$type]++;
                                continue;
                            }

                            // Drop any column the current schema no longer has,
                            // so an older backup still restores cleanly.
                            $filtered = array_intersect_key($row, array_flip($columns));

                            if (!$filtered) {
                                $skipped[$type]++;
                                continue;
                            }

                            $clean[] = $filtered;
                        }

                        if (!$clean) {
                            continue;
                        }

                        // Upsert on the primary key so re-running a restore is safe.
                        DB::table($table)->upsert(
                            $clean,
                            [$model->getKeyName()],
                            array_values(array_diff(array_keys($clean[0]), [$model->getKeyName()]))
                        );

                        $imported[$type] += count($clean);
                    }
                }

                // Settings come last: restoring them changes how everything above
                // is then displayed.
                if (!empty($payload['settings']) && is_array($payload['settings'])) {
                    foreach ($payload['settings'] as $key => $value) {
                        if (isset(Settings::SCHEMA[$key]) && $value !== '[redacted]') {
                            Setting::put($key, (string) $value, Settings::SCHEMA[$key]['group'] ?? 'general');
                        }
                    }
                }

                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            });
        } catch (\Throwable $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            return ['success' => false, 'message' => 'Restore failed and was rolled back: ' . $e->getMessage()];
        }

        Settings::flush();
        StatsService::flush();
        NotificationService::flushCache();

        return [
            'success'  => true,
            'message'  => sprintf('Restored %s record(s).', number_format(array_sum($imported))),
            'imported' => $imported,
            'skipped'  => $skipped,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Deletion                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Permanently removes every record of the selected types.
     *
     * Deletes in reverse declaration order so children go before parents and
     * foreign keys stay satisfied.
     *
     * @param array<int, string> $types
     * @return array<string, int>
     */
    public static function purge(array $types): array
    {
        $ordered = array_reverse(array_values(array_intersect(array_keys(self::TYPES), $types)));
        $deleted = [];

        DB::transaction(function () use ($ordered, &$deleted) {
            foreach ($ordered as $type) {
                /** @var class-string<Model> $model */
                $model = self::TYPES[$type]['model'];

                $deleted[$type] = $model::query()->count();
                $model::query()->delete();
            }
        });

        StatsService::flush();
        NotificationService::flushCache();

        return $deleted;
    }

    /* ------------------------------------------------------------------ */

    /** Renders any column value as a single CSV-safe string. */
    private static function flatten(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) ($value ?? '');
    }
}
