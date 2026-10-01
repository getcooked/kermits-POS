<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Brings every menu picture to one house standard so they look uniform side by side:
 * an exact 800x800 square on pure white, with the dish centered and its longest side
 * filling the same share of the frame. Photos on busy backgrounds cannot be cleaned
 * up automatically, so they are cropped to their centered square instead.
 */
class ProductImageProcessor
{
    public const SIZE = 800;

    /** Share of the frame the dish's longest side fills, so every plate sits at the same scale. */
    public const FILL = 0.8;

    private const BACKGROUND_TOLERANCE = 28;

    private const QUALITY = 82;

    private const BRAND_LOGO = 'kermits-logo.jpg';

    /** Maximum differing bits (out of 256) for a picture to count as the brand logo. */
    private const LOGO_DISTANCE = 32;

    private static ?string $brandLogoFingerprint = null;

    /**
     * @param  bool  $framed  The admin already framed the picture in the cropper, so keep their framing as-is.
     */
    public function store(UploadedFile $file, string $directory = 'products', bool $framed = false): string
    {
        $processed = $this->processContents((string) file_get_contents($file->getRealPath()), $framed);

        if ($processed === null) {
            return $file->store($directory, 'public');
        }

        return $this->put($processed, $directory);
    }

    /**
     * Saves already-processed WebP bytes under a fresh name, so picture URLs change and caches refresh.
     */
    public function put(string $processed, string $directory = 'products'): string
    {
        $path = trim($directory, '/').'/'.Str::random(40).'.webp';
        Storage::disk('public')->put($path, $processed);

        return $path;
    }

    /**
     * Returns the standardized WebP bytes, or null when GD is unavailable or the image cannot be read.
     */
    public function processContents(string $contents, bool $framed = false): ?string
    {
        $image = $this->normalize($contents, $framed);

        if ($image === null) {
            return null;
        }

        ob_start();
        imagewebp($image, null, self::QUALITY);

        return ob_get_clean() ?: null;
    }

    /**
     * Detects the Kermit's logo used as a stand-in picture, even after it was trimmed or re-encoded.
     */
    public function isBrandLogo(string $contents): bool
    {
        $reference = $this->brandLogoFingerprint();
        $fingerprint = $this->fingerprint($contents);

        return $reference !== null
            && $fingerprint !== null
            && $this->bitDistance($fingerprint, $reference) <= self::LOGO_DISTANCE;
    }

    /**
     * A 256-bit average hash of the standardized picture, compared bit by bit to spot near-duplicates.
     */
    public function fingerprint(string $contents): ?string
    {
        $image = $this->normalize($contents);

        if ($image === null) {
            return null;
        }

        $small = imagecreatetruecolor(16, 16);
        imagecopyresampled($small, $image, 0, 0, 0, 0, 16, 16, self::SIZE, self::SIZE);
        $luma = [];

        for ($y = 0; $y < 16; $y++) {
            for ($x = 0; $x < 16; $x++) {
                [$red, $green, $blue] = $this->rgb($small, $x, $y);
                $luma[] = 0.299 * $red + 0.587 * $green + 0.114 * $blue;
            }
        }

        $mean = array_sum($luma) / count($luma);

        return implode('', array_map(fn (float $value): string => $value < $mean ? '1' : '0', $luma));
    }

    public function bitDistance(string $a, string $b): int
    {
        return count(array_diff_assoc(str_split($a), str_split($b)));
    }

    private function normalize(string $contents, bool $framed = false): ?GdImage
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return null;
        }

        $source = @imagecreatefromstring($contents);

        if (! $source instanceof GdImage) {
            return null;
        }

        imagepalettetotruecolor($source);
        $source = $this->applyExifOrientation($source, $contents);
        $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        $background = $framed ? null : $this->backgroundColor($source);

        if ($background === null) {
            [$x, $y, $side] = $this->centerSquare($source);
            imagecopyresampled($canvas, $source, 0, 0, $x, $y, self::SIZE, self::SIZE, $side, $side);

            return $canvas;
        }

        [$x, $y, $width, $height] = $this->contentBox($source, $background);
        $scale = self::SIZE * self::FILL / max($width, $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $left = intdiv(self::SIZE - $targetWidth, 2);
        $top = intdiv(self::SIZE - $targetHeight, 2);
        imagecopyresampled($canvas, $source, $left, $top, $x, $y, $targetWidth, $targetHeight, $width, $height);
        $this->whiten($canvas, $background, $left, $top, $targetWidth, $targetHeight);

        return $canvas;
    }

    /**
     * Shifts an off-white studio background to pure white with a per-channel levels curve,
     * so the pasted photo blends into the white frame without a visible edge.
     *
     * @param  array{int, int, int}  $background
     */
    private function whiten(GdImage $image, array $background, int $left, int $top, int $width, int $height): void
    {
        if (min($background) === 255) {
            return;
        }

        [$red, $green, $blue] = array_map(
            fn (int $channel): array => array_map(fn (int $value): int => min(255, (int) round($value * 255 / $channel)), range(0, 255)),
            $background,
        );

        for ($y = $top; $y < $top + $height; $y++) {
            for ($x = $left; $x < $left + $width; $x++) {
                $color = imagecolorat($image, $x, $y);
                imagesetpixel($image, $x, $y, ($red[($color >> 16) & 255] << 16) | ($green[($color >> 8) & 255] << 8) | $blue[$color & 255]);
            }
        }
    }

    /**
     * Phone photos are often stored sideways with an EXIF note saying how to turn them; GD ignores that note.
     */
    private function applyExifOrientation(GdImage $image, string $contents): GdImage
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($contents, "\xFF\xD8")) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($contents));
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    private function brandLogoFingerprint(): ?string
    {
        if (self::$brandLogoFingerprint === null && is_file($logo = public_path(self::BRAND_LOGO))) {
            self::$brandLogoFingerprint = $this->fingerprint((string) file_get_contents($logo));
        }

        return self::$brandLogoFingerprint;
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
     * @return array{int, int, int}
     */
    private function centerSquare(GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);

        return [intdiv($width - $side, 2), intdiv($height - $side, 2), $side];
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
