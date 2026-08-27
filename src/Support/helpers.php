<?php
declare(strict_types=1);

use App\Core\Url;

if (!function_exists('e')) {
    /** HTML-escape for templates. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = '', array $query = []): string
    {
        return Url::to($path, $query);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return Url::asset($path);
    }
}

if (!function_exists('num')) {
    /** 2721 -> "2,721" */
    function num(int|float|string|null $value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        return number_format((float) $value, $decimals);
    }
}

if (!function_exists('pct')) {
    /** Progress percentage; returns null when the denominator is unusable. */
    function pct(int|float|null $done, int|float|null $target, int $decimals = 2): ?float
    {
        $target = (float) $target;
        if ($target <= 0) {
            return null;
        }
        return round(((float) $done / $target) * 100, $decimals);
    }
}

if (!function_exists('thai_date')) {
    /** 2026-05-12 -> "12/05/2569" (Buddhist Era, as government forms require). */
    function thai_date(?string $date, bool $withTime = false): string
    {
        if ($date === null || $date === '' || str_starts_with($date, '0000')) {
            return '—';
        }
        try {
            $dt = new DateTimeImmutable($date);
        } catch (Throwable) {
            return '—';
        }
        $be = (int) $dt->format('Y') + 543;
        return $dt->format('d/m/') . $be . ($withTime ? $dt->format(' H:i') : '');
    }
}

if (!function_exists('be_year')) {
    function be_year(?int $christianYear = null): string
    {
        return (string) (($christianYear ?? (int) date('Y')) + 543);
    }
}

if (!function_exists('file_size_human')) {
    function file_size_human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return ($i === 0 ? (string) (int) $size : number_format($size, 1)) . ' ' . $units[$i];
    }
}

if (!function_exists('str_excerpt')) {
    function str_excerpt(?string $text, int $limit = 120): string
    {
        $text = trim((string) $text);
        return mb_strlen($text) <= $limit ? $text : mb_substr($text, 0, $limit) . '…';
    }
}

if (!function_exists('array_get')) {
    function array_get(array $array, string $key, mixed $default = null): mixed
    {
        return $array[$key] ?? $default;
    }
}
