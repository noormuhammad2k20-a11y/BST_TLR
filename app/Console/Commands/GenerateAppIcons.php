<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Renders the PWA icons with GD, so no design tool or external asset is needed.
 * The mark matches the sidebar: a slate-900 rounded square with white scissors.
 */
class GenerateAppIcons extends Command
{
    protected $signature = 'atelier:icons';

    protected $description = 'Generate the PWA / desktop app icons into public/icons';

    private const BG = [15, 23, 42];      // #0F172A — slate-900
    private const FG = [255, 255, 255];

    public function handle(): int
    {
        if (!extension_loaded('gd')) {
            $this->error('The GD extension is not enabled. Enable extension=gd in php.ini and try again.');

            return self::FAILURE;
        }

        $dir = public_path('icons');

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->error("Could not create {$dir}");

            return self::FAILURE;
        }

        // "any" icons are edge-to-edge; the maskable icon keeps the mark inside
        // the safe zone so platforms can crop it to a circle or squircle.
        $targets = [
            ['file' => 'icon-192.png',          'size' => 192, 'radius' => 0.22, 'inset' => 0.20],
            ['file' => 'icon-512.png',          'size' => 512, 'radius' => 0.22, 'inset' => 0.20],
            ['file' => 'icon-maskable-512.png', 'size' => 512, 'radius' => 0.00, 'inset' => 0.30],
            ['file' => 'apple-touch-icon.png',  'size' => 180, 'radius' => 0.00, 'inset' => 0.20],
            ['file' => 'favicon-32.png',        'size' => 32,  'radius' => 0.22, 'inset' => 0.18],
        ];

        foreach ($targets as $target) {
            $this->render($dir . DIRECTORY_SEPARATOR . $target['file'], $target['size'], $target['radius'], $target['inset']);
            $this->line("  <fg=green>created</> icons/{$target['file']}");
        }

        $this->newLine();
        $this->info('App icons generated in public/icons.');

        return self::SUCCESS;
    }

    private function render(string $path, int $size, float $radiusRatio, float $inset): void
    {
        // Render at 4x then downsample — cheap anti-aliasing for the curves.
        $scale = 4;
        $canvas = $size * $scale;

        $img = imagecreatetruecolor($canvas, $canvas);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagealphablending($img, true);

        $bg = imagecolorallocate($img, ...self::BG);
        $fg = imagecolorallocate($img, ...self::FG);

        $radius = (int) round($canvas * $radiusRatio);
        $this->roundedRect($img, 0, 0, $canvas - 1, $canvas - 1, $radius, $bg);
        $this->scissors($img, $canvas, $inset, $fg, $bg);

        $out = imagecreatetruecolor($size, $size);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $size, $size, $canvas, $canvas);

        imagepng($out, $path, 9);

        imagedestroy($img);
        imagedestroy($out);
    }

    private function roundedRect($img, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        if ($r <= 0) {
            imagefilledrectangle($img, $x1, $y1, $x2, $y2, $color);

            return;
        }

        imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $color);

        $d = $r * 2;
        imagefilledellipse($img, $x1 + $r, $y1 + $r, $d, $d, $color);
        imagefilledellipse($img, $x2 - $r, $y1 + $r, $d, $d, $color);
        imagefilledellipse($img, $x1 + $r, $y2 - $r, $d, $d, $color);
        imagefilledellipse($img, $x2 - $r, $y2 - $r, $d, $d, $color);
    }

    /**
     * A pair of scissors: two crossing blades and two finger rings.
     */
    private function scissors($img, int $canvas, float $inset, int $fg, int $bg): void
    {
        $pad = $canvas * $inset;
        $box = $canvas - ($pad * 2);
        $stroke = max(1, (int) round($box * 0.085));

        imagesetthickness($img, $stroke);

        $x = fn (float $p) => (int) round($pad + $box * $p);
        $y = fn (float $p) => (int) round($pad + $box * $p);

        // Blades, crossing just below centre.
        imageline($img, $x(0.86), $y(0.06), $x(0.30), $y(0.68), $fg);
        imageline($img, $x(0.14), $y(0.06), $x(0.70), $y(0.68), $fg);

        // Finger rings.
        $ringD = (int) round($box * 0.30);
        $ringStroke = max(1, (int) round($stroke * 0.9));

        foreach ([[0.22, 0.80], [0.78, 0.80]] as [$cx, $cy]) {
            imagefilledellipse($img, $x($cx), $y($cy), $ringD, $ringD, $fg);
            imagefilledellipse($img, $x($cx), $y($cy), $ringD - ($ringStroke * 2), $ringD - ($ringStroke * 2), $bg);
        }

        imagesetthickness($img, 1);
    }
}
