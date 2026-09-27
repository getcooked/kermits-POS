<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Normalizes menu pictures so the dish is clearly visible in small cards:
 * trims plain borders, squares the picture with a small margin, resizes it
 * and re-encodes it as a compact WebP file.
 */
class ProductImageProcessor
{
    public const SIZE = 800;

    private const MARGIN = 0.06;

    private const BACKGROUND_TOLERANCE = 28;

    private const QUALITY = 82;

    public function store(UploadedFile $file, string $directory = 'products'): string
    {
        $processed = $this->processContents((string) file_get_contents($file->getRealPath()));

        if ($processed === null) {
            return $file->store($directory, 'public');
        }

        $path = trim($directory, '/').'/'.Str::random(40).'.webp';
        Storage::disk('public')->put($path, $processed);

        return $path;
    }

    /**
     * Returns the optimized WebP bytes, or null when GD is unavailable or the image cannot be read.
     */
    public function processContents(string $contents): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return null;
        }

        $source = @imagecreatefromstring($contents);

        if (! $source instanceof GdImage) {
            return null;
        }

        imagepalettetotruecolor($source);
        $background = $this->backgroundColor($source);
        [$x, $y, $width, $height] = $background !== null
            ? $this->contentBox($source, $background)
            : $this->centerSquare($source);

        $side = $background !== null
            ? (int) ceil(max($width, $height) / (1 - 2 * self::MARGIN))
            : max($width, $height);
        $scale = min(1, self::SIZE / $side);
        $canvasSide = max(1, (int) round($side * $scale));

        $canvas = imagecreatetruecolor($canvasSide, $canvasSide);
        [$red, $green, $blue] = $background ?? [255, 255, 255];
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, $red, $green, $blue));

        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        imagecopyresampled(
            $canvas,
            $source,
            intdiv($canvasSide - $targetWidth, 2),
            intdiv($canvasSide - $targetHeight, 2),
            $x,
            $y,
            $targetWidth,
            $targetHeight,
            $width,
            $height,
        );

        ob_start();
        imagewebp($canvas, null, self::QUALITY);

        return ob_get_clean() ?: null;
    }

    /**
     * Detects a plain studio background from the four corners. Transparent corners count as white.
     *
     * @return array{int, int, int}|null
     */
    private function backgroundColor(GdImage $image): ?array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $corners = array_map(
            fn (array $point): array => $this->rgb($image, ...$point),
            [[0, 0], [$width - 1, 0], [0, $height - 1], [$width - 1, $height - 1]],
        );

        foreach ($corners as $corner) {
            if ($this->distance($corner, $corners[0]) > self::BACKGROUND_TOLERANCE || min($corner) < 200) {
                return null;
            }
        }

        return [
            (int) round(array_sum(array_column($corners, 0)) / 4),
            (int) round(array_sum(array_column($corners, 1)) / 4),
            (int) round(array_sum(array_column($corners, 2)) / 4),
        ];
    }

    /**
     * Finds the bounding box of everything that differs from the background, using a small preview for speed.
     *
     * @param  array{int, int, int}  $background
     * @return array{int, int, int, int}
     */
    private function contentBox(GdImage $image, array $background): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = min(1, 300 / max($width, $height));
        $previewWidth = max(1, (int) round($width * $ratio));
        $previewHeight = max(1, (int) round($height * $ratio));
        $preview = imagecreatetruecolor($previewWidth, $previewHeight);
        imagefill($preview, 0, 0, imagecolorallocate($preview, 255, 255, 255));
        imagecopyresampled($preview, $image, 0, 0, 0, 0, $previewWidth, $previewHeight, $width, $height);

        $minX = $previewWidth;
        $minY = $previewHeight;
        $maxX = -1;
        $maxY = -1;

        for ($py = 0; $py < $previewHeight; $py++) {
            for ($px = 0; $px < $previewWidth; $px++) {
                if ($this->distance($this->rgb($preview, $px, $py), $background) > self::BACKGROUND_TOLERANCE) {
                    $minX = min($minX, $px);
                    $maxX = max($maxX, $px);
                    $minY = min($minY, $py);
                    $maxY = max($maxY, $py);
                }
            }
        }

        if ($maxX < 0) {
            return [0, 0, $width, $height];
        }

        // Map back to full size with a one-preview-pixel safety border.
        $x = max(0, (int) floor(($minX - 1) / $ratio));
        $y = max(0, (int) floor(($minY - 1) / $ratio));

        return [
            $x,
            $y,
            min($width, (int) ceil(($maxX + 2) / $ratio)) - $x,
            min($height, (int) ceil(($maxY + 2) / $ratio)) - $y,
        ];
    }

    /**
     * Photos on a busy background (tables, posters) are cropped to their centered square instead.
     *
     * @return array{int, int, int, int}
     */
    private function centerSquare(GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);

        return [intdiv($width - $side, 2), intdiv($height - $side, 2), $side, $side];
    }

    /**
     * @return array{int, int, int}
     */
    private function rgb(GdImage $image, int $x, int $y): array
    {
        $color = imagecolorat($image, $x, $y);
        $alpha = (($color >> 24) & 127) / 127;
        $blend = fn (int $channel): int => (int) round($channel * (1 - $alpha) + 255 * $alpha);

        return [$blend(($color >> 16) & 255), $blend(($color >> 8) & 255), $blend($color & 255)];
    }

    /**
     * @param  array{int, int, int}  $a
     * @param  array{int, int, int}  $b
     */
    private function distance(array $a, array $b): int
    {
        return max(abs($a[0] - $b[0]), abs($a[1] - $b[1]), abs($a[2] - $b[2]));
    }
}
