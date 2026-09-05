<?php

namespace App\Services;

use RuntimeException;
use XMLReader;

/**
 * Reads a spreadsheet into plain rows.
 *
 * Handles .xlsx and .csv with nothing but PHP's bundled zip/XML extensions, so
 * a shop can import its existing customer book without installing anything.
 * When phpoffice/phpspreadsheet happens to be present it is used instead,
 * because it also opens the older binary .xls format and copes with the odder
 * files real offices produce.
 *
 * The reader is streaming: a 4,000-row sheet is walked one row at a time
 * rather than being materialised twice in memory.
 */
class SpreadsheetReader
{
    public const SUPPORTED = ['csv', 'txt', 'tsv', 'xlsx', 'xlsm', 'xls'];

    /** Date number-format ids Excel ships with. */
    private const BUILTIN_DATE_FORMATS = [14, 15, 16, 17, 18, 19, 20, 21, 22, 45, 46, 47];

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>, total: int}
     */
    public static function read(string $path, ?int $limit = null): array
    {
        $rows = self::rows($path, $limit === null ? null : $limit + 1);

        if ($rows === []) {
            return ['headers' => [], 'rows' => [], 'total' => 0];
        }

        $headers = array_map(
            fn ($h) => trim((string) $h),
            array_shift($rows)
        );

        // A trailing run of blank headers is just Excel's empty columns.
        while ($headers !== [] && end($headers) === '') {
            array_pop($headers);
        }

        $width = count($headers);

        $rows = array_values(array_filter(
            array_map(fn (array $row) => self::fit($row, $width), $rows),
            fn (array $row) => implode('', $row) !== ''
        ));

        return ['headers' => $headers, 'rows' => $rows, 'total' => count($rows)];
    }

    /**
     * Every row including the header row, as flat arrays of strings.
     *
     * @return array<int, array<int, string>>
     */
    public static function rows(string $path, ?int $limit = null): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('The uploaded file could not be opened.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            return self::viaPhpSpreadsheet($path, $limit);
        }

        return match ($extension) {
            'csv', 'txt', 'tsv'  => self::readCsv($path, $limit),
            'xlsx', 'xlsm'       => self::readXlsx($path, $limit),
            'xls'                => throw new RuntimeException(
                'The old .xls format needs converting first — open it in Excel and use "Save As" to pick .xlsx or CSV.'
            ),
            default              => throw new RuntimeException('Only .xlsx and .csv files can be read.'),
        };
    }

    /* ------------------------------------------------------------------ */
    /*  CSV                                                                */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<int, array<int, string>>
     */
    private static function readCsv(string $path, ?int $limit = null): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('The CSV file could not be opened.');
        }

        // Excel on Windows writes a BOM; left in place it corrupts the very
        // first header, which is the column people map first.
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $delimiter = self::sniffDelimiter($path);
        $rows      = [];

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            // fgetcsv reports a blank line as [null]; keep it out of the count.
            if ($row === [null]) {
                continue;
            }

            $rows[] = array_map(fn ($v) => self::clean((string) ($v ?? '')), $row);

            if ($limit !== null && count($rows) >= $limit) {
                break;
            }
        }

        fclose($handle);

        return $rows;
    }

    private static function sniffDelimiter(string $path): string
    {
        $line = '';
        $handle = fopen($path, 'r');

        if ($handle !== false) {
            $line = (string) fgets($handle, 8192);
            fclose($handle);
        }

        $counts = [
            ','  => substr_count($line, ','),
            ';'  => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
            '|'  => substr_count($line, '|'),
        ];

        arsort($counts);
        $best = array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }

    /* ------------------------------------------------------------------ */
    /*  XLSX                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<int, array<int, string>>
     */
    private static function readXlsx(string $path, ?int $limit = null): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('That .xlsx file could not be opened — it may be corrupt.');
        }

        try {
            $sheetPath = self::firstSheetPath($zip);
            $strings   = self::sharedStrings($zip);
            $dateStyle = self::dateStyles($zip);

            $xml = $zip->getFromName($sheetPath);

            if ($xml === false) {
                throw new RuntimeException('The workbook has no readable sheet.');
            }

            return self::parseSheet($xml, $strings, $dateStyle, $limit);
        } finally {
            $zip->close();
        }
    }

    private static function firstSheetPath(\ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels     = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook !== false && $rels !== false) {
            $wb = @simplexml_load_string($workbook);
            $rl = @simplexml_load_string($rels);

            if ($wb !== false && $rl !== false) {
                $map = [];
                foreach ($rl->Relationship as $rel) {
                    $map[(string) $rel['Id']] = ltrim((string) $rel['Target'], '/');
                }

                $sheets = $wb->sheets->sheet ?? [];
                foreach ($sheets as $sheet) {
                    // The sheet's relationship id lives in the r: namespace.
                    $id = (string) ($sheet->attributes('r', true)['id'] ?? '');

                    if ($id !== '' && isset($map[$id])) {
                        $target = $map[$id];

                        return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                    }
                }
            }
        }

        // Fall back to the conventional location.
        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @return array<int, string>
     */
    private static function sharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $strings = [];
        $reader  = new XMLReader();
        $reader->XML($xml);

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'si') {
                continue;
            }

            $node = @simplexml_load_string($reader->readOuterXml());

            if ($node === false) {
                $strings[] = '';
                continue;
            }

            // A string can be one <t>, or split across <r><t> runs when parts
            // of it were formatted differently.
            $text = (string) ($node->t ?? '');

            if ($text === '' && isset($node->r)) {
                foreach ($node->r as $run) {
                    $text .= (string) $run->t;
                }
            }

            $strings[] = self::clean($text);
        }

        $reader->close();

        return $strings;
    }

    /**
     * Style indexes whose number format renders as a date.
     *
     * @return array<int, bool>
     */
    private static function dateStyles(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/styles.xml');

        if ($xml === false) {
            return [];
        }

        $styles = @simplexml_load_string($xml);

        if ($styles === false) {
            return [];
        }

        $custom = [];
        if (isset($styles->numFmts->numFmt)) {
            foreach ($styles->numFmts->numFmt as $fmt) {
                $code = (string) $fmt['formatCode'];

                // Strip quoted literals before looking for date tokens, so a
                // currency format like "y"#,##0 is not mistaken for a date.
                $bare = preg_replace('/"[^"]*"/', '', $code) ?? $code;

                $custom[(int) $fmt['numFmtId']] = (bool) preg_match('/[dmyhs]/i', $bare)
                    && !preg_match('/^\[\$?[^\]]*\]$/', trim($bare));
            }
        }

        $out = [];
        $index = 0;

        if (isset($styles->cellXfs->xf)) {
            foreach ($styles->cellXfs->xf as $xf) {
                $id = (int) $xf['numFmtId'];

                $out[$index] = in_array($id, self::BUILTIN_DATE_FORMATS, true)
                    || ($custom[$id] ?? false);

                $index++;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $strings
     * @param  array<int, bool>    $dateStyles
     * @return array<int, array<int, string>>
     */
    private static function parseSheet(string $xml, array $strings, array $dateStyles, ?int $limit): array
    {
        $reader = new XMLReader();
        $reader->XML($xml);

        $rows = [];

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'row') {
                continue;
            }

            $node = @simplexml_load_string($reader->readOuterXml());

            if ($node === false) {
                continue;
            }

            $cells = [];
            $widest = -1;

            foreach ($node->c as $cell) {
                $ref    = (string) $cell['r'];
                $column = self::columnIndex($ref);
                $widest = max($widest, $column);

                $cells[$column] = self::cellValue($cell, $strings, $dateStyles);
            }

            // Excel omits empty cells entirely; rebuild the gaps so every row
            // lines up with its header.
            $row = [];
            for ($i = 0; $i <= $widest; $i++) {
                $row[$i] = $cells[$i] ?? '';
            }

            $rows[] = $row;

            if ($limit !== null && count($rows) >= $limit) {
                break;
            }
        }

        $reader->close();

        return $rows;
    }

    /**
     * @param  array<int, string>  $strings
     * @param  array<int, bool>    $dateStyles
     */
    private static function cellValue(\SimpleXMLElement $cell, array $strings, array $dateStyles): string
    {
        $type = (string) $cell['t'];

        if ($type === 's') {
            $index = (int) $cell->v;

            return $strings[$index] ?? '';
        }

        if ($type === 'inlineStr') {
            $text = (string) ($cell->is->t ?? '');

            if ($text === '' && isset($cell->is->r)) {
                foreach ($cell->is->r as $run) {
                    $text .= (string) $run->t;
                }
            }

            return self::clean($text);
        }

        if ($type === 'b') {
            return ((string) $cell->v) === '1' ? 'TRUE' : 'FALSE';
        }

        if ($type === 'e') {
            return '';
        }

        $raw = (string) ($cell->v ?? '');

        if ($raw === '') {
            return (string) ($cell->is->t ?? '');
        }

        // A date is stored as a serial number; only the style says otherwise.
        $style = (int) ($cell['s'] ?? 0);

        if (($dateStyles[$style] ?? false) && is_numeric($raw)) {
            return self::excelDate((float) $raw);
        }

        return self::clean($raw);
    }

    /**
     * Excel counts days from 1900-01-00 and wrongly believes 1900 was a leap
     * year, which is why the epoch below is 1899-12-30.
     */
    private static function excelDate(float $serial): string
    {
        if ($serial <= 0) {
            return '';
        }

        $days    = (int) floor($serial);
        $seconds = (int) round(($serial - $days) * 86400);

        $date = (new \DateTimeImmutable('1899-12-30'))->modify("+{$days} days");

        if ($seconds > 0) {
            return $date->modify("+{$seconds} seconds")->format('Y-m-d H:i:s');
        }

        return $date->format('Y-m-d');
    }

    /** "BC12" -> 54 (zero-based column index). */
    private static function columnIndex(string $ref): int
    {
        if (!preg_match('/^([A-Z]+)/i', $ref, $m)) {
            return 0;
        }

        $letters = strtoupper($m[1]);
        $index   = 0;

        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    /* ------------------------------------------------------------------ */
    /*  PhpSpreadsheet                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<int, array<int, string>>
     */
    private static function viaPhpSpreadsheet(string $path, ?int $limit): array
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);

        $sheet = $reader->load($path)->getActiveSheet();
        $rows  = [];

        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];

            foreach ($row->getCellIterator() as $cell) {
                $value = $cell->getFormattedValue();
                $cells[] = self::clean((string) $value);
            }

            $rows[] = $cells;

            if ($limit !== null && count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /* ------------------------------------------------------------------ */

    /**
     * @param  array<int, string>  $row
     * @return array<int, string>
     */
    private static function fit(array $row, int $width): array
    {
        $row = array_slice(array_values($row), 0, $width);

        return array_pad($row, $width, '');
    }

    private static function clean(string $value): string
    {
        // Non-breaking spaces travel in from Word and Excel and quietly break
        // every comparison made against the value later on.
        $value = str_replace(["\xc2\xa0", "\xe2\x80\x8b"], ' ', $value);

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
