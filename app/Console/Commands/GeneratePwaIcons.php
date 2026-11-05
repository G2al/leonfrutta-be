<?php

namespace App\Console\Commands;

use GdImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Builds the PWA app icons from the round LeonFrutta logo
 * (resources/images/leonfrutta-logo.png): the logo is cut out along its
 * circle and centred on the dark background of the Filament panel.
 */
class GeneratePwaIcons extends Command
{
    protected $signature = 'pwa:icons';

    protected $description = 'Genera le icone PNG della PWA dal logo LeonFrutta';

    private const SOURCE_LOGO = 'images/leonfrutta-logo.png';

    /** Dark background of the Filament panel (gray-950, Zinc palette). */
    private const BACKGROUND = [9, 9, 11]; // #09090b

    /**
     * Circle of the logo inside the source image, in source pixels. The radius
     * stays just inside the blue ring so no white corner pixel survives.
     */
    private const LOGO_CENTER = 256;

    private const LOGO_RADIUS = 221;

    /**
     * logo: diameter of the logo as a fraction of the icon side.
     * Maskable icons keep it inside the central 80% safe zone that Android may crop.
     *
     * @var array<string, array{size: int, logo: float}>
     */
    private const ICONS = [
        'icon-192.png' => ['size' => 192, 'logo' => 0.88],
        'icon-512.png' => ['size' => 512, 'logo' => 0.88],
        'maskable-512.png' => ['size' => 512, 'logo' => 0.76],
        'apple-touch-icon.png' => ['size' => 180, 'logo' => 0.84],
    ];

    public function handle(): int
    {
        $source = imagecreatefrompng(resource_path(self::SOURCE_LOGO));

        $directory = public_path('pwa/icons');
        File::ensureDirectoryExists($directory);

        foreach (self::ICONS as $fileName => $icon) {
            imagepng($this->drawIcon($source, $icon['size'], $icon['logo']), "{$directory}/{$fileName}", 9);

            $this->components->info("Creata public/pwa/icons/{$fileName} ({$icon['size']}x{$icon['size']})");
        }

        return self::SUCCESS;
    }

    private function drawIcon(GdImage $source, int $size, float $logoFraction): GdImage
    {
        $icon = imagecreatetruecolor($size, $size);
        imagefill($icon, 0, 0, imagecolorallocate($icon, ...self::BACKGROUND));

        $logoSize = (int) round($size * $logoFraction);
        $logo = $this->roundLogo($source, $logoSize);

        $offset = intdiv($size - $logoSize, 2);
        imagealphablending($icon, true);
        imagecopy($icon, $logo, $offset, $offset, 0, 0, $logoSize, $logoSize);

        return $icon;
    }

    /**
     * The logo resized to $diameter, transparent outside its circle with a
     * one pixel soft edge so the border is not jagged.
     */
    private function roundLogo(GdImage $source, int $diameter): GdImage
    {
        $logo = imagecreatetruecolor($diameter, $diameter);
        $cropOrigin = self::LOGO_CENTER - self::LOGO_RADIUS;
        $cropSize = self::LOGO_RADIUS * 2;

        imagecopyresampled($logo, $source, 0, 0, $cropOrigin, $cropOrigin, $diameter, $diameter, $cropSize, $cropSize);

        imagealphablending($logo, false);
        imagesavealpha($logo, true);

        $radius = $diameter / 2;

        for ($y = 0; $y < $diameter; $y++) {
            for ($x = 0; $x < $diameter; $x++) {
                $distanceFromEdge = $radius - hypot($x + 0.5 - $radius, $y + 0.5 - $radius);
                $coverage = max(0.0, min(1.0, $distanceFromEdge + 0.5));

                if ($coverage >= 1.0) {
                    continue;
                }

                $rgb = imagecolorat($logo, $x, $y);
                $transparency = (int) round(127 * (1 - $coverage));

                imagesetpixel($logo, $x, $y, imagecolorallocatealpha(
                    $logo,
                    ($rgb >> 16) & 0xFF,
                    ($rgb >> 8) & 0xFF,
                    $rgb & 0xFF,
                    $transparency,
                ));
            }
        }

        return $logo;
    }
}
