<?php

namespace App\Services;

use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a Blade document to a real, downloadable PDF.
 *
 * The renderer is resolved at runtime so the application keeps working on an
 * install where the PDF package has not been pulled in yet: when neither
 * `barryvdh/laravel-dompdf` nor a bare `dompdf/dompdf` is present the very same
 * document is returned as a stripped-down, print-ready HTML page that opens the
 * browser's print dialog — from which "Save as PDF" produces an identical
 * layout. Nothing in the UI has to know which path was taken.
 *
 * To get true one-click PDF downloads, install the package once:
 *
 *     composer require barryvdh/laravel-dompdf
 */
class PdfExporter
{
    /** Paper the report documents are laid out for. */
    private const PAPER = 'a4';

    /**
     * True when a PDF engine is installed and a real .pdf file can be streamed.
     */
    public static function available(): bool
    {
        return class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)
            || class_exists(\Dompdf\Dompdf::class);
    }

    /**
     * Render `$view` and send it to the browser as a PDF download.
     *
     * @param  array<string, mixed>  $data
     */
    public static function download(
        string $view,
        array $data,
        string $filename,
        string $orientation = 'portrait'
    ): Response {
        $filename = self::sanitiseFilename($filename);

        // --- 1. barryvdh/laravel-dompdf ---------------------------------
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $html = self::render($view, $data, true);

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
                ->setPaper(self::PAPER, $orientation);

            // Older releases of the wrapper do not expose setOption().
            if (method_exists($pdf, 'setOption')) {
                $pdf->setOption('defaultFont', 'DejaVu Sans');
                $pdf->setOption('isPhpEnabled', true);
            }

            return $pdf->download($filename);
        }

        // --- 2. plain dompdf/dompdf -------------------------------------
        if (class_exists(\Dompdf\Dompdf::class)) {
            $html = self::render($view, $data, true);

            $options = new \Dompdf\Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isPhpEnabled', true);
            $options->set('isRemoteEnabled', false);
            $options->set('chroot', base_path());

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper(self::PAPER, $orientation);
            $dompdf->render();

            return response($dompdf->output(), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        // --- 3. no engine: clean print-ready page ------------------------
        return response(self::render($view, $data, false), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * The document header block every report PDF shares: business identity,
     * the exact window the figures cover and when the file was produced.
     *
     * @return array<string, mixed>
     */
    public static function documentMeta(string $title, string $rangeLabel, ?string $filterLabel = null): array
    {
        $settings = Settings::all();

        return [
            'doc' => [
                'title'        => $title,
                'range_label'  => $rangeLabel,
                'filter_label' => $filterLabel,
                'store'        => $settings['store_name'] ?: 'Atelier',
                'tagline'      => $settings['tagline'] ?? '',
                'address'      => $settings['address'] ?? '',
                'phone'        => $settings['phone'] ?? '',
                'email'        => $settings['email'] ?? '',
                'logo'         => self::logoDataUri($settings['logo_path'] ?? ''),
                'currency'     => Settings::currency(),
                'generated_at' => now(Settings::timezone())->format('d M Y, h:i A'),
                'generated_by' => optional(auth()->user())->name ?? 'System',
            ],
        ];
    }

    /* ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>  $data
     */
    private static function render(string $view, array $data, bool $forPdf): string
    {
        /** @var ViewContract $rendered */
        $rendered = View::make($view, array_merge($data, [
            'forPdf'    => $forPdf,
            'autoPrint' => !$forPdf,
        ]));

        return $rendered->render();
    }

    /**
     * Inlines the shop logo as a data URI. dompdf reads data URIs without
     * needing remote access switched on, and an unreadable path simply drops
     * the image rather than breaking the render.
     */
    private static function logoDataUri(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        $candidates = [
            public_path(ltrim($path, '/')),
            public_path('storage/' . ltrim($path, '/')),
            storage_path('app/public/' . ltrim($path, '/')),
            $path,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '' || !is_file($candidate) || !is_readable($candidate)) {
                continue;
            }

            $bytes = @file_get_contents($candidate);

            if ($bytes === false || $bytes === '') {
                continue;
            }

            $mime = match (strtolower(pathinfo($candidate, PATHINFO_EXTENSION))) {
                'png'        => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'gif'        => 'image/gif',
                default      => null,
            };

            // SVG and webp are not rendered reliably by dompdf; skip them.
            if ($mime === null) {
                continue;
            }

            return 'data:' . $mime . ';base64,' . base64_encode($bytes);
        }

        return null;
    }

    private static function sanitiseFilename(string $filename): string
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?? 'report.pdf';

        return trim($filename, '-') ?: 'report.pdf';
    }
}
