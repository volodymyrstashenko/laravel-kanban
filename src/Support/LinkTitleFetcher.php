<?php

namespace Thevps\Kanban\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Витягує <title> цільової сторінки для посилання, прикріпленого до картки Канбану. Один раз,
 * синхронно, при збереженні посилання (best-effort — той самий принцип, що фетч фавіконки чи
 * геолокації в хост-застосунку: мережева помилка не повинна ламати збереження).
 *
 * SSRF-захист (посилання додає лише автентифікований член дошки, але все одно): дозволені лише
 * http/https; хост резолвиться в IP, і приватні/loopback/зарезервовані адреси відсіюються ДО
 * запиту. Ланцюжки редіректів по-новому не ре-валідуються — прийнятний ризик для внутрішнього
 * інструмента.
 */
class LinkTitleFetcher
{
    private const TIMEOUT_SECONDS = 5;

    private const MAX_BYTES = 256 * 1024;

    public static function fetch(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        if (! self::hostIsPublic($host)) {
            Log::warning('Kanban link title fetch blocked (non-public host)', ['url' => $url]);

            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'User-Agent' => 'KanbanBot/1.0 (+link-title-preview)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            if ($contentType !== '' && ! str_contains($contentType, 'html')) {
                return null;
            }

            return self::extractTitle(substr($response->body(), 0, self::MAX_BYTES));
        } catch (\Throwable $e) {
            Log::warning('Kanban link title fetch threw', ['url' => $url, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /** Усі IP, у які резолвиться хост, мають бути публічними. */
    private static function hostIsPublic(string $host): bool
    {
        // Літеральний IP у самому хості.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        $lower = strtolower($host);
        if ($lower === 'localhost' || str_ends_with($lower, '.localhost') || str_ends_with($lower, '.local') || str_ends_with($lower, '.internal')) {
            return false;
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false || $records === []) {
            return false;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if ($ip === null) {
                continue;
            }
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    private static function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = self::clean($m[1]);
            if ($title !== '') {
                return $title;
            }
        }

        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\'](.*?)["\']/is', $html, $m)
            || preg_match('/<meta[^>]+content=["\'](.*?)["\'][^>]+property=["\']og:title["\']/is', $html, $m)) {
            $title = self::clean($m[1]);
            if ($title !== '') {
                return $title;
            }
        }

        return null;
    }

    private static function clean(string $raw): string
    {
        $text = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        return mb_substr($text, 0, 255);
    }
}
