<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\ActivityLogger;
use App\Services\CustomerImporter;
use App\Services\NotificationService;
use App\Services\SpreadsheetReader;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The bulk import wizard for an existing customer book.
 *
 * Each step is its own request so a four-thousand-row workbook never has to be
 * finished inside one PHP execution window: the upload is parsed and cached
 * once, the mapping is confirmed by hand, a dry run reports what would happen,
 * and only then does the real run walk the file a few hundred rows at a time
 * while the page shows progress.
 *
 * Work in progress lives under storage/app/imports/{token}. Nothing there is
 * needed once a run finishes, and anything left behind by an abandoned run is
 * swept away after a day.
 */
class ImportController extends Controller
{
    /** How long an unfinished import is kept before it is swept away. */
    private const KEEP_HOURS = 24;

    private const MAX_UPLOAD_KB = 20480;   // 20 MB — far above any real book

    public function index()
    {
        return view('customers.import', [
            'fields'  => CustomerImporter::fields(),
            'chunk'   => CustomerImporter::CHUNK,
            'formats' => SpreadsheetReader::SUPPORTED,
        ]);
    }

    /**
     * A blank workbook in exactly the shape the importer understands.
     */
    public function template(): StreamedResponse
    {
        $fields = CustomerImporter::fields();

        $samples = [
            [
                'name' => 'Adnan Shaikh', 'phone' => '0300 1234567', 'email' => 'adnan@example.com',
                'city' => 'Larkana', 'address' => 'Main Bazaar Road', 'type' => 'Regular',
                'customer_notes' => 'Prefers loose fitting',
                'garment_type' => 'Shalwar Kameez', 'tailor' => 'Master Ghulam Rasool', 'unit' => 'in',
                'measurement_notes' => 'Round collar',
                'length' => 42, 'shoulder_width' => 18.5, 'sleeve_length' => 24, 'chest' => 40,
                'chest_losing' => 2, 'waist' => 36, 'waist_losing' => 1.5, 'hip' => 40, 'hip_losing' => 2,
                'collar' => 15.5, 'ghera' => 26, 'patti' => 1.5, 'button' => 5, 'cuff' => 9,
                'koni' => 12, 'elbow' => 13, 'armhole' => 20, 'takai' => 3,
                'salwar_length' => 40, 'pancho' => 8,
            ],
            [
                'name' => 'Fatima Memon', 'phone' => '0321 7654321', 'city' => 'Sukkur', 'type' => 'Premium',
                'garment_type' => 'Kurta', 'unit' => 'in',
                'length' => 38, 'chest' => 36, 'chest_losing' => 1.5, 'waist' => 32, 'waist_losing' => 1,
                'hip' => 38, 'hip_losing' => 1.5,
            ],
        ];

        return response()->streamDownload(function () use ($fields, $samples) {
            $handle = fopen('php://output', 'w');

            // Excel on Windows needs the BOM to read UTF-8 correctly.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, array_column($fields, 'label'), ',', '"', '\\');

            foreach ($samples as $sample) {
                fputcsv($handle, array_map(
                    fn ($key) => $sample[$key] ?? '',
                    array_keys($fields)
                ), ',', '"', '\\');
            }

            fclose($handle);
        }, 'customer-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Takes the file, caches a normalised copy and reports what is in it.
     */
    public function upload(Request $request): JsonResponse
    {
        // PHP discards an oversized upload before Laravel ever sees it, which
        // otherwise surfaces as a baffling "the file field is required".
        if (!$request->hasFile('file') && (int) $request->server('CONTENT_LENGTH') > 0) {
            return response()->json([
                'message' => sprintf(
                    'The file is larger than this server accepts (currently %s). Raise upload_max_filesize and post_max_size in php.ini, or save the book as CSV, which is far smaller.',
                    ini_get('upload_max_filesize') ?: 'the configured limit'
                ),
            ], 422);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:' . self::MAX_UPLOAD_KB],
        ]);

        $file      = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, SpreadsheetReader::SUPPORTED, true)) {
            return response()->json([
                'message' => 'That file type cannot be read. Save the book as .xlsx or .csv and try again.',
            ], 422);
        }

        $this->sweep();

        $token = Str::random(32);
        $dir   = $this->directory($token);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $source = $dir . '/source.' . $extension;
        $file->move($dir, basename($source));

        try {
            $analysis = CustomerImporter::analyse($source, $dir . '/rows.csv');
        } catch (\Throwable $e) {
            $this->remove($dir);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($analysis['total'] === 0) {
            $this->remove($dir);

            return response()->json(['message' => 'That file has headings but no data rows.'], 422);
        }

        $this->writeState($token, [
            'cache'     => $dir . '/rows.csv',
            'headers'   => $analysis['headers'],
            'total'     => $analysis['total'],
            'file'      => $file->getClientOriginalName(),
            'mapping'   => $analysis['mapping'],
            'options'   => [],
            'dry_seen'  => [],
            'created_at' => now()->toDateTimeString(),
        ]);

        return response()->json([
            'success' => true,
            'token'   => $token,
            'file'    => $analysis['headers'] ? $file->getClientOriginalName() : '',
            'headers' => $analysis['headers'],
            'preview' => $analysis['preview'],
            'total'   => $analysis['total'],
            'mapping' => $analysis['mapping'],
        ]);
    }

    /**
     * Runs one slice, either as a rehearsal or for real.
     */
    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'   => ['required', 'string'],
            'offset'  => ['required', 'integer', 'min:0'],
            'dry_run' => ['required', 'boolean'],
            'mapping' => ['required', 'array'],
            'options' => ['nullable', 'array'],
        ]);

        $token = $this->safeToken($validated['token']);
        $state = $this->readState($token);

        if ($state === null) {
            return response()->json([
                'message' => 'This import has expired. Please upload the file again.',
            ], 410);
        }

        $mapping = $this->cleanMapping($validated['mapping'], count($state['headers']));

        if (($mapping['name'] ?? null) === null) {
            return response()->json([
                'message' => 'Choose which column holds the customer name before importing.',
            ], 422);
        }

        $options = $this->cleanOptions($validated['options'] ?? []);
        $offset  = (int) $validated['offset'];
        $dryRun  = (bool) $validated['dry_run'];

        // A fresh pass starts the tallies and the issue log over.
        if ($offset === 0) {
            $state['dry_seen'] = [];
            $this->resetIssues($token, $dryRun);
        }

        $state['mapping'] = $mapping;
        $state['options'] = $options;

        $summary = CustomerImporter::process(
            $state,
            $offset,
            CustomerImporter::CHUNK,
            $dryRun,
            $dryRun ? ($state['dry_seen'] ?? []) : []
        );

        if ($dryRun) {
            $state['dry_seen'] = array_slice(
                array_merge($state['dry_seen'] ?? [], $summary['new_keys']),
                -20000
            );
        }

        $this->writeState($token, $state);
        $this->appendIssues($token, $dryRun, $summary['issues']);

        $processed = $offset + $summary['read'];
        $done      = $summary['read'] === 0 || $processed >= $state['total'];

        if ($done && !$dryRun) {
            StatsService::flush();
        }

        unset($summary['new_keys']);

        return response()->json([
            'success'   => true,
            'summary'   => $summary,
            'issues'    => array_slice($summary['issues'], 0, 40),
            'processed' => $processed,
            'total'     => $state['total'],
            'done'      => $done,
        ]);
    }

    /**
     * Records the finished run once, rather than 4,000 times mid-import.
     *
     * A bulk import is the kind of thing someone starts and then walks away
     * from, so it leaves a notification behind as well as an audit entry —
     * otherwise the only trace of it is a page they may already have closed.
     */
    public function finish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'   => ['required', 'string'],
            'created' => ['required', 'integer', 'min:0'],
            'updated' => ['required', 'integer', 'min:0'],
            'sheets'  => ['required', 'integer', 'min:0'],
            'skipped' => ['nullable', 'integer', 'min:0'],
            'failed'  => ['nullable', 'integer', 'min:0'],
        ]);

        $token = $this->safeToken($validated['token']);
        $state = $this->readState($token);
        $file  = $state['file'] ?? 'an uploaded file';

        $created = (int) $validated['created'];
        $updated = (int) $validated['updated'];
        $sheets  = (int) $validated['sheets'];
        $skipped = (int) ($validated['skipped'] ?? 0);
        $failed  = (int) ($validated['failed'] ?? 0);

        $headline = sprintf(
            '%s added, %s updated, %s measurement sheet(s) created',
            number_format($created),
            number_format($updated),
            number_format($sheets)
        );

        if ($skipped > 0 || $failed > 0) {
            $headline .= sprintf(' — %s row(s) left out', number_format($skipped + $failed));
        }

        ActivityLogger::log(
            'Customers imported',
            $headline . ' from ' . $file,
            'customers',
            null,
            [
                'file'    => $file,
                'created' => $created,
                'updated' => $updated,
                'sheets'  => $sheets,
                'skipped' => $skipped,
                'failed'  => $failed,
            ],
            'created'
        );

        $notification = NotificationService::push(
            'Customer import finished',
            $headline . '.',
            'system',
            'fa-solid fa-file-import',
            $failed > 0 ? 'warning' : 'success',
            null,
            null,
            route('customers.index')
        );

        StatsService::flush();

        // The sidebar badges and the bell read a 30-second cache. After an
        // import that just added thousands of customers, waiting out that
        // window would make the app look as though nothing had happened.
        Cache::forget('layout.counters');

        return response()->json([
            'success'      => true,
            'message'      => $headline,
            'notification' => (bool) $notification,
        ]);
    }

    /**
     * The full issue log, so a shop can fix its book and re-run.
     */
    public function issues(Request $request, string $token): StreamedResponse
    {
        $token = $this->safeToken($token);
        $dry   = $request->boolean('dry', true);
        $path  = $this->issuePath($token, $dry);

        return response()->streamDownload(function () use ($path) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Row in file', 'Level', 'Customer', 'Phone', 'What happened'], ',', '"', '\\');

            if (is_file($path)) {
                $in = fopen($path, 'r');

                while (($row = fgetcsv($in, 0, ',', '"', '\\')) !== false) {
                    if ($row !== [null]) {
                        fputcsv($out, $row, ',', '"', '\\');
                    }
                }

                fclose($in);
            }

            fclose($out);
        }, 'import-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function discard(Request $request): JsonResponse
    {
        $token = $this->safeToken($request->input('token', ''));
        $this->remove($this->directory($token));

        return response()->json(['success' => true]);
    }

    /* ------------------------------------------------------------------ */
    /*  Workspace                                                          */
    /* ------------------------------------------------------------------ */

    private function safeToken(string $token): string
    {
        if (!preg_match('/^[A-Za-z0-9]{16,64}$/', $token)) {
            abort(404);
        }

        return $token;
    }

    private function directory(string $token): string
    {
        return storage_path('app/imports/' . $token);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function writeState(string $token, array $state): void
    {
        $dir = $this->directory($token);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($dir . '/state.json', json_encode($state, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readState(string $token): ?array
    {
        $path = $this->directory($token) . '/state.json';

        if (!is_file($path)) {
            return null;
        }

        $state = json_decode((string) file_get_contents($path), true);

        if (!is_array($state) || !is_file($state['cache'] ?? '')) {
            return null;
        }

        return $state;
    }

    private function issuePath(string $token, bool $dry): string
    {
        return $this->directory($token) . '/' . ($dry ? 'check' : 'import') . '-issues.csv';
    }

    private function resetIssues(string $token, bool $dry): void
    {
        $path = $this->issuePath($token, $dry);

        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $issues
     */
    private function appendIssues(string $token, bool $dry, array $issues): void
    {
        if ($issues === []) {
            return;
        }

        $handle = fopen($this->issuePath($token, $dry), 'a');

        if ($handle === false) {
            return;
        }

        foreach ($issues as $issue) {
            fputcsv($handle, [
                $issue['line'],
                ucfirst($issue['level']),
                $issue['name'],
                $issue['phone'],
                $issue['message'],
            ], ',', '"', '\\');
        }

        fclose($handle);
    }

    /** Clears out workspaces left behind by imports nobody finished. */
    private function sweep(): void
    {
        $root = storage_path('app/imports');

        if (!is_dir($root)) {
            return;
        }

        $cutoff = now()->subHours(self::KEEP_HOURS)->getTimestamp();

        foreach (glob($root . '/*') ?: [] as $dir) {
            if (is_dir($dir) && filemtime($dir) < $cutoff) {
                $this->remove($dir);
            }
        }
    }

    private function remove(string $dir): void
    {
        if (!is_dir($dir) || !str_starts_with($dir, storage_path('app/imports'))) {
            return;
        }

        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        @rmdir($dir);
    }

    /* ------------------------------------------------------------------ */
    /*  Input                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>  $mapping
     * @return array<string, int|null>
     */
    private function cleanMapping(array $mapping, int $columns): array
    {
        $out = [];

        foreach (array_keys(CustomerImporter::fields()) as $field) {
            $value = $mapping[$field] ?? null;

            $out[$field] = is_numeric($value) && $value >= 0 && $value < $columns
                ? (int) $value
                : null;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function cleanOptions(array $options): array
    {
        $existing = $options['existing'] ?? CustomerImporter::EXISTING_FILL;
        $phone    = $options['missing_phone'] ?? CustomerImporter::PHONE_SKIP;
        $unit     = strtolower((string) ($options['default_unit'] ?? 'cm'));
        $garment  = trim((string) ($options['default_garment'] ?? 'General'));

        return [
            'existing' => in_array($existing, [
                CustomerImporter::EXISTING_FILL,
                CustomerImporter::EXISTING_UPDATE,
                CustomerImporter::EXISTING_SKIP,
            ], true) ? $existing : CustomerImporter::EXISTING_FILL,

            'missing_phone' => in_array($phone, [
                CustomerImporter::PHONE_SKIP,
                CustomerImporter::PHONE_PLACEHOLDER,
            ], true) ? $phone : CustomerImporter::PHONE_SKIP,

            'default_unit'    => in_array($unit, ['cm', 'in'], true) ? $unit : 'cm',
            'default_garment' => $garment !== '' ? mb_substr($garment, 0, 255) : 'General',

            'skip_duplicate_measurements' => (bool) ($options['skip_duplicate_measurements'] ?? false),
        ];
    }
}
