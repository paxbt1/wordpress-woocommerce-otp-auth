<?php
namespace WCSA\Support;

defined('ABSPATH') || exit;

use WCSA\Options;

final class Logger {
    const KEY = 'wcsa_logs';
    const MAX_CONTEXT_CHARS = 12000;

    public static function add(string $level, string $msg, array $ctx = [], bool $force = false, bool $revealSensitive = false): void {
        if (!Options::yes('logging_enabled') && !$force) return;

        $logs = get_option(self::KEY, []);
        if (!is_array($logs)) $logs = [];

        $ctx = self::sanitizeContext($ctx, $revealSensitive || Options::yes('debug_sensitive_logging'));

        $logs[] = [
            'time' => current_time('mysql'),
            'level' => sanitize_key($level),
            'message' => sanitize_text_field($msg),
            'context' => $ctx,
        ];

        $limit = max(20, min(1000, (int) Options::get('log_retention', 100)));
        if (count($logs) > $limit) $logs = array_slice($logs, -$limit);

        update_option(self::KEY, $logs, false);
    }

    public static function http(string $level, string $msg, array $request, array $response = [], bool $force = false, bool $revealSensitive = false): void {
        self::add($level, $msg, [
            'request' => $request,
            'response' => $response,
        ], $force, $revealSensitive);
    }

    public static function all(): array {
        $l = get_option(self::KEY, []);
        return is_array($l) ? array_reverse($l) : [];
    }

    public static function latest(int $limit = 5): array {
        return array_slice(self::all(), 0, max(1, min(20, $limit)));
    }

    public static function byRequestId(string $requestId, int $limit = 20): array {
        $requestId = (string) $requestId;
        $out = [];
        foreach (self::all() as $row) {
            if (self::contextHasValue($row['context'] ?? [], 'request_id', $requestId)) {
                $out[] = $row;
                if (count($out) >= max(1, min(50, $limit))) break;
            }
        }
        return $out;
    }

    private static function contextHasValue($value, string $key, string $expected): bool {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                if ((string)$k === $key && (string)$v === $expected) return true;
                if (is_array($v) && self::contextHasValue($v, $key, $expected)) return true;
            }
        }
        return false;
    }


    public static function simplifyRows(array $rows): array {
        return array_map([__CLASS__, 'simplifyRow'], $rows);
    }

    public static function simplifyRow(array $row): array {
        $ctx = $row['context'] ?? [];
        $req = is_array($ctx) ? ($ctx['request'] ?? []) : [];
        $res = is_array($ctx) ? ($ctx['response'] ?? []) : [];
        $requestBody = is_array($req) ? ($req['body'] ?? []) : [];
        $responseBody = is_array($res) ? ($res['body'] ?? null) : null;
        $parsed = is_array($res) ? ($res['parsed'] ?? null) : null;

        $provider = self::firstValue($ctx, ['provider']);
        if (!$provider && is_array($req)) $provider = $req['provider'] ?? null;
        if (!$provider && is_array($requestBody)) $provider = $requestBody['provider'] ?? null;

        return [
            'time' => $row['time'] ?? '',
            'level' => $row['level'] ?? '',
            'message' => $row['message'] ?? '',
            'provider' => $provider ?: '',
            'method' => is_array($req) ? ($req['method'] ?? ($req['format'] ?? '')) : '',
            'mobile' => self::firstValue($ctx, ['mobile', 'to', 'receptor']) ?: (is_array($requestBody) ? ($requestBody['to'] ?? $requestBody['mobile'] ?? '') : ''),
            'sender' => self::firstValue($ctx, ['sender', 'from', 'From']) ?: (is_array($requestBody) ? ($requestBody['sender'] ?? $requestBody['from'] ?? $requestBody['From'] ?? '') : ''),
            'http' => is_array($res) ? ($res['http'] ?? '') : '',
            'ok' => is_array($ctx) && array_key_exists('ok', $ctx) ? $ctx['ok'] : (is_array($res) && array_key_exists('ok', $res) ? $res['ok'] : ''),
            'result' => is_array($parsed) ? ($parsed['message'] ?? '') : (is_array($responseBody) ? ($responseBody['StrRetStatus'] ?? $responseBody['Value'] ?? '') : (is_scalar($responseBody) ? (string)$responseBody : '')),
        ];
    }

    private static function firstValue($value, array $keys) {
        if (!is_array($value)) return null;
        foreach ($keys as $key) {
            if (array_key_exists($key, $value) && $value[$key] !== '') return $value[$key];
        }
        foreach ($value as $v) {
            if (is_array($v)) {
                $found = self::firstValue($v, $keys);
                if ($found !== null && $found !== '') return $found;
            }
        }
        return null;
    }

    public static function clear(): void { delete_option(self::KEY); }

    private static function sanitizeContext($value, bool $revealSensitive = false) {
        $sensitiveKeys = ['mobile','to','receptor','recipient','national_code','token','otp','code','password','secret','secret_key','api_key','apikey','authorization','auth','custom_auth_value'];

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $lk = strtolower((string)$k);
                $isSensitive = false;
                foreach ($sensitiveKeys as $needle) {
                    if ($lk === $needle || strpos($lk, $needle) !== false) { $isSensitive = true; break; }
                }
                $out[$k] = ($isSensitive && !$revealSensitive) ? self::mask($v) : self::sanitizeContext($v, $revealSensitive);
            }
            return $out;
        }

        if (is_object($value)) {
            return self::sanitizeContext(json_decode(wp_json_encode($value), true), $revealSensitive);
        }

        if (is_string($value)) {
            if (!$revealSensitive) $value = self::maskSecretsInString($value);
            if (strlen($value) > self::MAX_CONTEXT_CHARS) {
                $value = substr($value, 0, self::MAX_CONTEXT_CHARS) . '... [truncated]';
            }
            return $value;
        }

        return $value;
    }

    private static function mask($value): string {
        if (is_array($value) || is_object($value)) return '***';
        $value = (string) $value;
        if ($value === '') return '';
        $len = strlen($value);
        if ($len <= 6) return '***';
        return substr($value, 0, 2) . str_repeat('*', max(3, $len - 6)) . substr($value, -4);
    }

    private static function maskSecretsInString(string $value): string {
        $patterns = [
            '/(Authorization:\s*Bearer\s+)[^\s"\']+/i',
            '/([?&](?:api[_-]?key|apikey|token|password|secret)[=])([^&\s]+)/i',
            '/("(?:api[_-]?key|apikey|token|password|secret|authorization)"\s*:\s*")[^"]+(")/i',
        ];
        $replacements = ['$1***', '$1***', '$1***$2'];
        return preg_replace($patterns, $replacements, $value) ?: $value;
    }
}
