<?php

declare(strict_types=1);

namespace Superroute\SApp;

/** Theme tokens from the service provider's branding (the bootstrap's `client.settings.branding`). */
final class Theme
{
    /**
     * @param array<string, mixed> $branding
     * @return array<string, mixed>
     */
    public static function fromBranding(array $branding = []): array
    {
        $raw = is_string($branding['primary_color'] ?? null) ? $branding['primary_color'] : '';
        $primary = preg_match('/^#[0-9a-fA-F]{6}$/', $raw) === 1 ? strtoupper($raw) : '#E02020';

        return [
            'displayName' => is_string($branding['display_name'] ?? null) && $branding['display_name'] !== '' ? $branding['display_name'] : null,
            'logoUrl' => is_string($branding['logo_url'] ?? null) && $branding['logo_url'] !== '' ? $branding['logo_url'] : null,
            'color' => [
                'primary' => $primary,
                'onPrimary' => self::luminance($primary) > 0.4 ? '#111111' : '#FFFFFF',
                'primaryContainer' => self::mix($primary, 255, 0.85),
                'onPrimaryContainer' => self::mix($primary, 0, 0.55),
                'surface' => '#FFFFFF',
                'onSurface' => '#1C1C1E',
                'muted' => '#6B7280',
                'success' => '#16A34A',
                'warning' => '#FD7E14',
                'danger' => '#DC2626',
            ],
            'radius' => ['small' => 6, 'medium' => 10, 'large' => 16],
            'spacing' => ['xs' => 4, 's' => 8, 'm' => 12, 'l' => 16, 'xl' => 24],
            'type' => ['body' => 15, 'small' => 13, 'title' => 20, 'headline' => 26],
        ];
    }

    private static function channel(string $hex, int $i): int
    {
        return (int) hexdec(substr($hex, 1 + $i * 2, 2));
    }

    private static function luminance(string $hex): float
    {
        $c = [];
        for ($i = 0; $i < 3; $i++) {
            $v = self::channel($hex, $i) / 255;
            $c[] = $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    private static function mix(string $hex, int $target, float $amount): string
    {
        $out = '#';
        for ($i = 0; $i < 3; $i++) {
            $v = self::channel($hex, $i);
            // Math.round in JavaScript rounds .5 up; PHP's round() rounds half away from zero, the same for positives.
            $out .= sprintf('%02X', (int) floor($v + ($target - $v) * $amount + 0.5));
        }

        return $out;
    }
}
