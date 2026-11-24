<?php

namespace Kanboard\Plugin\Harbor\Test\Support;

use InvalidArgumentException;

final class Contrast
{
    private const NUMBER = '([+-]?(?:\d+(?:\.\d+)?|\.\d+))';

    public static function ratio(string $foreground, string $background): float
    {
        $foregroundLuminance = self::luminance($foreground);
        $backgroundLuminance = self::luminance($background);

        return (max($foregroundLuminance, $backgroundLuminance) + 0.05)
            / (min($foregroundLuminance, $backgroundLuminance) + 0.05);
    }

    private static function luminance(string $color): float
    {
        [$red, $green, $blue] = self::linearSrgb($color);

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    private static function linearSrgb(string $color): array
    {
        $number = self::NUMBER;
        if (preg_match("/^hsl\\({$number}\\s+{$number}%\\s+{$number}%\\)$/", $color, $match)) {
            return self::hslToLinearSrgb((float) $match[1], $match[2] / 100, $match[3] / 100);
        }
        if (preg_match("/^oklch\\({$number}\\s+{$number}\\s+{$number}\\)$/", $color, $match)) {
            return self::oklchToLinearSrgb((float) $match[1], (float) $match[2], (float) $match[3]);
        }

        throw new InvalidArgumentException("Expected an opaque hsl() or oklch() color, got $color");
    }

    private static function hslToLinearSrgb(float $hue, float $saturation, float $lightness): array
    {
        $hue = fmod(fmod($hue, 360) + 360, 360);
        $amplitude = $saturation * min($lightness, 1 - $lightness);

        return array_map(static function (int $offset) use ($hue, $lightness, $amplitude): float {
            $sector = fmod($offset + $hue / 30, 12);
            $channel = $lightness - $amplitude * max(-1, min($sector - 3, 9 - $sector, 1));

            return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        }, [0, 8, 4]);
    }

    private static function oklchToLinearSrgb(float $lightness, float $chroma, float $hue): array
    {
        $radians = deg2rad($hue);
        $a = $chroma * cos($radians);
        $b = $chroma * sin($radians);

        // Convert OKLab to LMS, then to linear sRGB.
        $l = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        return array_map(static fn (float $channel): float => max(0, min(1, $channel)), [
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ]);
    }
}
