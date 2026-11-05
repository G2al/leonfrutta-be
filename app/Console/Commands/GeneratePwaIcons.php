<?php

namespace App\Console\Commands;

use GdImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Draws the LeonFrutta app icons (an orange fruit with a leaf on the brand
 * green) used by the PWA manifest and by iOS "Add to Home Screen".
 *
 * Shapes are drawn at 4x and downscaled, which gives smooth antialiased edges.
 */
class GeneratePwaIcons extends Command
{
    protected $signature = 'pwa:icons';

    protected $description = 'Genera le icone PNG della PWA in public/pwa/icons';

    private const SUPERSAMPLING = 4;

    /** Maskable icons must keep their content inside the central 80% safe zone. */
    private const MASKABLE_SAFE_ZONE = 0.8;

    private const COLORS = [
        'background' => [22, 101, 52],   // #166534
        'fruit' => [249, 115, 22],        // #f97316
        'highlight' => [253, 186, 116],   // #fdba74
        'leaf' => [74, 222, 128],         // #4ade80
        'stem' => [120, 53, 15],          // #78350f
    ];

    /**
     * @var array<string, array{size: int, scale: float}>
     */
    private const ICONS = [
        'icon-192.png' => ['size' => 192, 'scale' => 1.0],
        'icon-512.png' => ['size' => 512, 'scale' => 1.0],
        'maskable-512.png' => ['size' => 512, 'scale' => self::MASKABLE_SAFE_ZONE],
        'apple-touch-icon.png' => ['size' => 180, 'scale' => 0.9],
    ];

    public function handle(): int
    {
        $directory = public_path('pwa/icons');
        File::ensureDirectoryExists($directory);

        foreach (self::ICONS as $fileName => $icon) {
            $image = $this->drawIcon($icon['size'], $icon['scale']);
            imagepng($image, "{$directory}/{$fileName}", 9);

            $this->components->info("Creata public/pwa/icons/{$fileName} ({$icon['size']}x{$icon['size']})");
        }

        return self::SUCCESS;
    }

    private function drawIcon(int $size, float $scale): GdImage
    {
        $canvasSize = $size * self::SUPERSAMPLING;
        $canvas = imagecreatetruecolor($canvasSize, $canvasSize);

        imagefill($canvas, 0, 0, $this->color($canvas, 'background'));

        // All coordinates are fractions of the canvas, shrunk around the centre by $scale.
        $point = fn (float $x, float $y): array => [
            (int) round($canvasSize * (0.5 + ($x - 0.5) * $scale)),
            (int) round($canvasSize * (0.5 + ($y - 0.5) * $scale)),
        ];
        $length = fn (float $fraction): int => (int) round($canvasSize * $fraction * $scale);

        [$stemX, $stemY] = $point(0.49, 0.20);
        imagefilledrectangle($canvas, $stemX, $stemY, $stemX + $length(0.035), $stemY + $length(0.14), $this->color($canvas, 'stem'));

        imagefilledpolygon($canvas, $this->leafPolygon($point, $length), $this->color($canvas, 'leaf'));

        [$fruitX, $fruitY] = $point(0.5, 0.58);
        imagefilledellipse($canvas, $fruitX, $fruitY, $length(0.56), $length(0.54), $this->color($canvas, 'fruit'));

        [$highlightX, $highlightY] = $point(0.39, 0.47);
        imagefilledellipse($canvas, $highlightX, $highlightY, $length(0.11), $length(0.08), $this->color($canvas, 'highlight'));

        $icon = imagecreatetruecolor($size, $size);
        imagecopyresampled($icon, $canvas, 0, 0, 0, 0, $size, $size, $canvasSize, $canvasSize);

        return $icon;
    }

    /**
     * A leaf is an ellipse tilted by 35 degrees, attached to the top of the stem.
     *
     * @param  callable(float, float): array{int, int}  $point
     * @param  callable(float): int  $length
     * @return list<int>
     */
    private function leafPolygon(callable $point, callable $length): array
    {
        [$centerX, $centerY] = $point(0.62, 0.22);
        $radiusX = $length(0.13);
        $radiusY = $length(0.06);
        $tilt = deg2rad(-35);

        $vertices = [];

        foreach (range(0, 359, 6) as $degrees) {
            $angle = deg2rad($degrees);
            $x = $radiusX * cos($angle);
            $y = $radiusY * sin($angle);

            $vertices[] = (int) round($centerX + $x * cos($tilt) - $y * sin($tilt));
            $vertices[] = (int) round($centerY + $x * sin($tilt) + $y * cos($tilt));
        }

        return $vertices;
    }

    private function color(GdImage $image, string $name): int
    {
        [$red, $green, $blue] = self::COLORS[$name];

        return imagecolorallocate($image, $red, $green, $blue);
    }
}
