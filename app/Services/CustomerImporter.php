<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Measurement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bulk import of a shop's existing customer book, measurements included.
 *
 * The shape of a real shop's spreadsheet is never the shape of our database,
 * so nothing here assumes fixed column positions: the file is read, its
 * headers are matched against a table of known aliases, and whatever could not
 * be guessed is mapped by hand in the wizard. The import then runs in chunks
 * over a normalised copy of the file, which keeps a four-thousand-row book
 * well inside PHP's execution limit and lets the page show real progress.
 *
 * Every run is reversible in the sense that matters: an existing customer is
 * never silently overwritten unless the operator asked for it.
 */
class CustomerImporter
{
    /** Rows handled per request. Small enough to never trip a timeout. */
    public const CHUNK = 250;

    public const EXISTING_FILL   = 'fill_blanks';
    public const EXISTING_UPDATE = 'overwrite';
    public const EXISTING_SKIP   = 'skip';

    public const PHONE_SKIP        = 'skip';
    public const PHONE_PLACEHOLDER = 'placeholder';

    /**
     * Every field a row can carry, with the header names shops actually use.
     *
     * `aliases` are matched loosely — case, spaces and punctuation are all
     * ignored — so "Mobile No.", "mobile_no" and "MOBILE NO" all land on phone.
     */
    public static function fields(): array
    {
        $measurementLabels = [
            'length'         => ['Length', 'Kameez Length', 'Qameez Length', 'Lambai'],
            'shoulder_width' => ['Shoulder', 'Shoulder Width', 'Kandha', 'Kandhay'],
            'sleeve_length'  => ['Sleeve', 'Sleeve Length', 'Bazu', 'Astin'],
            'chest'          => ['Chest', 'Seena', 'Bust'],
            'chest_losing'   => ['Chest Losing', 'Chest Loose', 'Seena Losing'],
            'waist'          => ['Waist', 'Kamar'],
            'waist_losing'   => ['Waist Losing', 'Waist Loose', 'Kamar Losing'],
            'hip'            => ['Hip', 'Hips', 'Gheera Hip'],
            'hip_losing'     => ['Hip Losing', 'Hip Loose'],
            'collar'         => ['Collar', 'Neck', 'Gala'],
            'ghera'          => ['Ghera', 'Gherra', 'Gera'],
            'patti'          => ['Patti', 'Patty'],
            'button'         => ['Button', 'Buttons'],
            'cuff'           => ['Cuff', 'Cuffs'],
            'koni'           => ['Koni', 'Kohni', 'Konii'],
            'elbow'          => ['Elbow'],
            'armhole'        => ['Armhole', 'Arm Hole', 'Mohri Bazu'],
            'takai'          => ['Takai', 'Tukai'],
            'salwar_length'  => ['Salwar Length', 'Shalwar Length', 'Trouser Length', 'Pajama Length'],
            'pancho'         => ['Pancho', 'Paincha', 'Panchay', 'Mohri'],
        ];

        $fields = [
            'name' => [
                'label'    => 'Customer name',
                'group'    => 'customer',
                'type'     => 'text',
                'required' => true,
                'aliases'  => ['name', 'customer name', 'customer', 'full name', 'client name', 'party name', 'naam'],
            ],
            'phone' => [
                'label'    => 'Phone number',
                'group'    => 'customer',
                'type'     => 'text',
                'required' => true,
                'aliases'  => ['phone', 'mobile', 'mobile no', 'mobile number', 'phone no', 'phone number', 'contact', 'contact no', 'cell', 'cell no', 'number'],
            ],
            'email' => [
                'label'   => 'Email',
                'group'   => 'customer',
                'type'    => 'text',
                'aliases' => ['email', 'e mail', 'email address', 'mail'],
            ],
            'city' => [
                'label'   => 'City',
                'group'   => 'customer',
                'type'    => 'text',
                'aliases' => ['city', 'town', 'area', 'location', 'shehar'],
            ],
            'address' => [
                'label'   => 'Address',
                'group'   => 'customer',
                'type'    => 'text',
                'aliases' => ['address', 'street', 'full address', 'pata'],
            ],
            'type' => [
                'label'   => 'Customer type',
                'group'   => 'customer',
                'type'    => 'choice',
                'aliases' => ['type', 'customer type', 'category', 'grade', 'tier'],
            ],
            'customer_notes' => [
                'label'   => 'Customer notes',
                'group'   => 'customer',
                'type'    => 'text',
                'aliases' => ['notes', 'note', 'remarks', 'comment', 'comments', 'customer notes'],
            ],
            'garment_type' => [
                'label'   => 'Garment type',
                'group'   => 'measurement',
                'type'    => 'text',
                'aliases' => ['garment', 'garment type', 'dress', 'dress type', 'item', 'suit type', 'kapra'],
            ],
            'tailor' => [
                'label'   => 'Tailor',
                'group'   => 'measurement',
                'type'    => 'text',
                'aliases' => ['tailor', 'master', 'karigar', 'stitched by', 'assigned to'],
            ],
            'unit' => [
                'label'   => 'Unit (cm / in)',
                'group'   => 'measurement',
                'type'    => 'choice',
                'aliases' => ['unit', 'units', 'measure unit', 'measurement unit'],
            ],
            'measurement_notes' => [
                'label'   => 'Measurement notes',
                'group'   => 'measurement',
                'type'    => 'text',
                'aliases' => ['measurement notes', 'fitting notes', 'style notes', 'special instructions'],
            ],
        ];

        foreach ($measurementLabels as $key => $aliases) {
            $fields[$key] = [
                'label'   => Measurement::label($key),
                'group'   => 'measurement',
                'type'    => 'number',
                'aliases' => array_map('strval', $aliases),
            ];
        }

        return $fields;
    }

    /** @return array<int, string> */
    public static function measurementKeys(): array
    {
        return array_values(array_filter(
            array_keys(self::fields()),
            fn ($key) => in_array($key, Measurement::FIELDS, true)
        ));
    }

    /* ------------------------------------------------------------------ */
    /*  Reading the file                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Reads the upload, caches a normalised copy for the chunked run, and
     * returns everything the mapping screen needs.
     *
     * @return array{headers: array<int, string>, preview: array<int, array<int, string>>, total: int, mapping: array<string, int|null>}
     */
    public static function analyse(string $sourcePath, string $cachePath): array
    {
        $rows = SpreadsheetReader::rows($sourcePath);

        if ($rows === []) {
            throw new RuntimeException('That file has no rows in it.');
        }

        $headers = array_map(fn ($h) => trim((string) $h), array_shift($rows));

        while ($headers !== [] && end($headers) === '') {
            array_pop($headers);
        }

        if ($headers === []) {
            throw new RuntimeException('The first row of the file must be the column headings.');
        }

        $width = count($headers);
        $clean = [];

        foreach ($rows as $row) {
            $row = array_pad(array_slice(array_values($row), 0, $width), $width, '');

            if (implode('', array_map('trim', $row)) === '') {
                continue;
            }

            $clean[] = $row;
        }

        // One normalised CSV means each chunk re-reads a cheap flat file
        // instead of unzipping and re-parsing the workbook every time.
        self::writeCache($cachePath, $headers, $clean);

        return [
            'headers' => $headers,
            'preview' => array_slice($clean, 0, 8),
            'total'   => count($clean),
            'mapping' => self::guess($headers),
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    private static function writeCache(string $path, array $headers, array $rows): void
    {
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException('The import workspace could not be written to.');
        }

        fputcsv($handle, $headers, ',', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }

        fclose($handle);
    }

    /**
     * Rows [offset, offset + limit) of the cached copy.
     *
     * @return array<int, array<int, string>>
     */
    public static function slice(string $cachePath, int $offset, int $limit): array
    {
        $handle = fopen($cachePath, 'r');

        if ($handle === false) {
            throw new RuntimeException('The import workspace has expired — please upload the file again.');
        }

        fgetcsv($handle, 0, ',', '"', '\\');   // headers

        $rows  = [];
        $index = 0;

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($row === [null]) {
                continue;
            }

            if ($index >= $offset) {
                $rows[] = array_map(fn ($v) => (string) ($v ?? ''), $row);

                if (count($rows) >= $limit) {
                    break;
                }
            }

            $index++;
        }

        fclose($handle);

        return $rows;
    }

    /* ------------------------------------------------------------------ */
    /*  Header guessing                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array<int, string>  $headers
     * @return array<string, int|null>
     */
    public static function guess(array $headers): array
    {
        $normalised = [];

        foreach ($headers as $index => $header) {
            $normalised[$index] = self::normalise($header);
        }

        $mapping = [];
        $taken   = [];

        foreach (self::fields() as $key => $field) {
            $candidates = array_merge([$field['label'], $key], $field['aliases']);
            $candidates = array_map([self::class, 'normalise'], $candidates);

            $match = null;

            // Exact match first, so "chest" never steals "chest losing".
            foreach ($normalised as $index => $header) {
                if ($header !== '' && !isset($taken[$index]) && in_array($header, $candidates, true)) {
                    $match = $index;
                    break;
                }
            }

            if ($match === null) {
                foreach ($normalised as $index => $header) {
                    if ($header === '' || isset($taken[$index])) {
                        continue;
                    }

                    foreach ($candidates as $candidate) {
                        if ($candidate !== '' && (str_starts_with($header, $candidate) || str_starts_with($candidate, $header))) {
                            $match = $index;
                            break 2;
                        }
                    }
                }
            }

            if ($match !== null) {
                $taken[$match] = true;
            }

            $mapping[$key] = $match;
        }

        return $mapping;
    }

    private static function normalise(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    /* ------------------------------------------------------------------ */
    /*  The run                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Processes one slice of the file.
     *
     * With `$dryRun` nothing is written: the same decisions are made and
     * counted, which is what the wizard's "check the file" step reports.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function process(array $state, int $offset, int $limit, bool $dryRun, array $seen = []): array
    {
        $rows    = self::slice($state['cache'], $offset, $limit);
        $mapping = $state['mapping'];
        $options = $state['options'];

        $summary = [
            'read'         => 0,
            'created'      => 0,
            'updated'      => 0,
            'skipped'      => 0,
            'failed'       => 0,
            'measurements' => 0,
            'issues'       => [],
            'new_keys'     => [],
        ];

        if ($rows === []) {
            return $summary;
        }

        // A dry run writes nothing, so phones created earlier in the same run
        // are carried forward by the caller instead of being read back from
        // the database. Without this the preview counts one create per
        // duplicate row rather than one create and one update.
        $directory = self::phoneDirectory();

        foreach ($seen as $key) {
            $directory[$key] ??= -1;
        }

        foreach ($rows as $i => $row) {
            $lineNumber = $offset + $i + 2;   // +1 for the header, +1 for 1-based
            $summary['read']++;

            try {
                self::processRow($row, $lineNumber, $mapping, $options, $directory, $summary, $dryRun);
            } catch (\Throwable $e) {
                $summary['failed']++;
                $summary['issues'][] = [
                    'line'    => $lineNumber,
                    'level'   => 'error',
                    'name'    => self::value($row, $mapping, 'name'),
                    'phone'   => self::value($row, $mapping, 'phone'),
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $summary;
    }

    /**
     * @param  array<int, string>       $row
     * @param  array<string, int|null>  $mapping
     * @param  array<string, mixed>     $options
     * @param  array<string, int>       $directory
     * @param  array<string, mixed>     $summary
     */
    private static function processRow(
        array $row,
        int $line,
        array $mapping,
        array $options,
        array &$directory,
        array &$summary,
        bool $dryRun
    ): void {
        $name  = self::value($row, $mapping, 'name');
        $phone = self::value($row, $mapping, 'phone');

        if ($name === '' && $phone === '') {
            $summary['skipped']++;

            return;
        }

        if ($name === '') {
            $summary['failed']++;
            $summary['issues'][] = self::issue($line, 'error', $name, $phone, 'No customer name in this row.');

            return;
        }

        $key = self::phoneKey($phone);

        if ($key === '') {
            if (($options['missing_phone'] ?? self::PHONE_SKIP) === self::PHONE_SKIP) {
                $summary['skipped']++;
                $summary['issues'][] = self::issue($line, 'warning', $name, $phone, 'No phone number — row skipped.');

                return;
            }

            $phone = 'N/A-' . strtoupper(substr(md5($name . $line), 0, 8));
            $key   = self::phoneKey($phone);
            $summary['issues'][] = self::issue($line, 'warning', $name, $phone, 'No phone number — imported with a placeholder.');
        }

        $customerData = self::customerData($row, $mapping, $name, $phone, $line, $summary);
        $existingId   = $directory[$key] ?? null;

        if ($existingId !== null) {
            $mode = $options['existing'] ?? self::EXISTING_FILL;

            if ($mode === self::EXISTING_SKIP) {
                $summary['skipped']++;
                $summary['issues'][] = self::issue($line, 'info', $name, $phone, 'Customer already on file — left untouched.');

                return;
            }

            $summary['updated']++;

            if (!$dryRun) {
                $customer = Customer::find($existingId);

                if ($customer) {
                    $customer->fill(self::mergeInto($customer, $customerData, $mode))->save();
                }
            }

            $customerId = $existingId;
        } else {
            $summary['created']++;
            $summary['new_keys'][] = $key;

            if ($dryRun) {
                $directory[$key] = -1;
                $customerId = null;
            } else {
                $customer = Customer::create($customerData);
                $directory[$key] = $customer->id;
                $customerId = $customer->id;
            }
        }

        self::maybeMeasurement($row, $mapping, $options, $customerId, $line, $name, $phone, $summary, $dryRun);
    }

    /**
     * @param  array<int, string>       $row
     * @param  array<string, int|null>  $mapping
     * @param  array<string, mixed>     $summary
     * @return array<string, mixed>
     */
    private static function customerData(array $row, array $mapping, string $name, string $phone, int $line, array &$summary): array
    {
        $data = [
            'name'  => mb_substr($name, 0, 255),
            'phone' => mb_substr($phone, 0, 50),
        ];

        $email = self::value($row, $mapping, 'email');
        if ($email !== '') {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $data['email'] = mb_substr($email, 0, 255);
            } else {
                $summary['issues'][] = self::issue($line, 'warning', $name, $phone, "Email \"{$email}\" is not valid — left blank.");
            }
        }

        foreach (['city' => 255, 'address' => 500] as $field => $length) {
            $value = self::value($row, $mapping, $field);

            if ($value !== '') {
                $data[$field] = mb_substr($value, 0, $length);
            }
        }

        $notes = self::value($row, $mapping, 'customer_notes');
        if ($notes !== '') {
            $data['notes'] = mb_substr($notes, 0, 2000);
        }

        $type = self::value($row, $mapping, 'type');
        if ($type !== '') {
            $matched = collect(['Regular', 'Premium', 'VIP'])
                ->first(fn ($allowed) => strcasecmp($allowed, $type) === 0);

            if ($matched) {
                $data['type'] = $matched;
            } else {
                $summary['issues'][] = self::issue($line, 'warning', $name, $phone, "Customer type \"{$type}\" is not one of Regular/Premium/VIP — set to Regular.");
                $data['type'] = 'Regular';
            }
        }

        return $data;
    }

    /**
     * Which of the file's values may touch a customer already on record.
     *
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private static function mergeInto(Customer $customer, array $incoming, string $mode): array
    {
        if ($mode === self::EXISTING_UPDATE) {
            return $incoming;
        }

        // fill_blanks: the file may only supply what the record is missing, so
        // an import can never erase details someone typed in by hand.
        return collect($incoming)
            ->filter(fn ($value, $field) => blank($customer->{$field}))
            ->all();
    }

    /**
     * @param  array<int, string>       $row
     * @param  array<string, int|null>  $mapping
     * @param  array<string, mixed>     $options
     * @param  array<string, mixed>     $summary
     */
    private static function maybeMeasurement(
        array $row,
        array $mapping,
        array $options,
        ?int $customerId,
        int $line,
        string $name,
        string $phone,
        array &$summary,
        bool $dryRun
    ): void {
        $values = [];

        foreach (self::measurementKeys() as $field) {
            $raw = self::value($row, $mapping, $field);

            if ($raw === '') {
                continue;
            }

            $number = self::number($raw);

            if ($number === null) {
                $summary['issues'][] = self::issue($line, 'warning', $name, $phone, Measurement::label($field) . " value \"{$raw}\" is not a number — left blank.");
                continue;
            }

            if ($number < 0 || $number > 999) {
                $summary['issues'][] = self::issue($line, 'warning', $name, $phone, Measurement::label($field) . " value {$number} is out of range — left blank.");
                continue;
            }

            $values[$field] = $number;
        }

        if ($values === []) {
            return;
        }

        $garment = self::value($row, $mapping, 'garment_type');
        $garment = $garment !== '' ? mb_substr($garment, 0, 255) : ($options['default_garment'] ?? 'General');

        $unit = strtolower(self::value($row, $mapping, 'unit'));
        $unit = in_array($unit, ['cm', 'in'], true) ? $unit : ($options['default_unit'] ?? 'cm');

        if (!empty($options['skip_duplicate_measurements']) && $customerId !== null) {
            $exists = Measurement::where('customer_id', $customerId)
                ->where('garment_type', $garment)
                ->exists();

            if ($exists) {
                $summary['issues'][] = self::issue($line, 'info', $name, $phone, "A {$garment} measurement sheet already exists for this customer — not added again.");

                return;
            }
        }

        $summary['measurements']++;

        if ($dryRun || $customerId === null) {
            return;
        }

        $tailor = self::value($row, $mapping, 'tailor');
        $notes  = self::value($row, $mapping, 'measurement_notes');

        Measurement::create($values + [
            'customer_id'  => $customerId,
            'garment_type' => $garment,
            'tailor'       => $tailor !== '' ? mb_substr($tailor, 0, 255) : null,
            'unit'         => $unit,
            'notes'        => $notes !== '' ? mb_substr($notes, 0, 2000) : null,
            'created_by'   => auth()->id(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Existing customers keyed by their comparable phone number.
     *
     * @return array<string, int>
     */
    public static function phoneDirectory(): array
    {
        $out = [];

        Customer::query()
            ->select('id', 'phone')
            ->orderBy('id')
            ->chunk(2000, function ($customers) use (&$out) {
                foreach ($customers as $customer) {
                    $key = self::phoneKey((string) $customer->phone);

                    // First one wins: the oldest record is the real customer.
                    if ($key !== '' && !isset($out[$key])) {
                        $out[$key] = $customer->id;
                    }
                }
            });

        return $out;
    }

    /**
     * Reduces a phone number to what actually identifies it, so
     * "+92 300 1234567", "0300-1234567" and "03001234567" are one customer.
     */
    public static function phoneKey(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        // Pakistani numbers arrive as 0300…, 92300… and 92300… interchangeably.
        if (str_starts_with($digits, '0092')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '92') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }

        $digits = ltrim($digits, '0');

        return $digits;
    }

    /** Parses a spreadsheet cell into a number, tolerating stray text. */
    public static function number(string $raw): ?float
    {
        $value = str_replace([',', ' ', '"', "'"], '', trim($raw));

        // Half sizes are often written as fractions: 38 1/2, 38½.
        $value = str_replace(['½', '¼', '¾'], ['.5', '.25', '.75'], $value);

        if (preg_match('/^(-?\d+)\s*(\d+)\/(\d+)$/', $value, $m) && (int) $m[3] !== 0) {
            return (float) $m[1] + (int) $m[2] / (int) $m[3];
        }

        if (preg_match('/^(-?\d+)\.(\d+)\.(\d+)$/', $value)) {
            return null;
        }

        if (!is_numeric($value)) {
            // "38 in", "38cm" — keep the number, drop the unit.
            if (preg_match('/^-?\d+(\.\d+)?/', $value, $m)) {
                return (float) $m[0];
            }

            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * @param  array<int, string>       $row
     * @param  array<string, int|null>  $mapping
     */
    private static function value(array $row, array $mapping, string $field): string
    {
        $index = $mapping[$field] ?? null;

        if ($index === null || !array_key_exists($index, $row)) {
            return '';
        }

        return trim((string) $row[$index]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function issue(int $line, string $level, string $name, string $phone, string $message): array
    {
        return compact('line', 'level', 'name', 'phone', 'message');
    }
}
