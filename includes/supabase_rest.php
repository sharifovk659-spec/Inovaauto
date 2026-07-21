<?php

declare(strict_types=1);

/**
 * Supabase PostgREST — database access over HTTP (Hostinger without pdo_pgsql).
 */

/**
 * @return array{url: string, secret_key: string, anon_key: string, enabled: bool}
 */
function ia_supabase_rest_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $appCfg = function_exists('ia_config') ? ia_config() : [];
    $supabase = is_array($appCfg['supabase'] ?? null) ? $appCfg['supabase'] : [];

    $url = rtrim((string) ($supabase['url'] ?? ia_env('IA_SUPABASE_URL') ?: ''), '/');
    $secret = trim((string) (
        $supabase['secret_key']
        ?? ia_env('IA_SUPABASE_SECRET_KEY')
        ?? ia_env('IA_SUPABASE_SERVICE_KEY')
        ?? ''
    ));
    $anon = trim((string) (
        $supabase['anon_key']
        ?? ia_env('IA_SUPABASE_ANON_KEY')
        ?? ia_env('VITE_SUPABASE_PUBLISHABLE_KEY')
        ?? ''
    ));

    $cfg = [
        'url' => $url,
        'secret_key' => $secret,
        'anon_key' => $anon,
        'enabled' => $url !== '' && $secret !== '',
    ];

    return $cfg;
}

function ia_supabase_rest_configured(): bool
{
    return ia_supabase_rest_config()['enabled'];
}

/**
 * @param array<string, mixed>|list<mixed> $payload
 * @return array{ok: bool, status: int, body: string, error: string, json: mixed}
 */
function ia_supabase_rest_request(string $method, string $path, array $payload = []): array
{
    $cfg = ia_supabase_rest_config();
    if (!$cfg['enabled']) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Supabase REST not configured', 'json' => null];
    }

    $url = $cfg['url'] . '/rest/v1/' . ltrim($path, '/');
    $key = $cfg['secret_key'];
    $headers = [
        'Authorization: Bearer ' . $key,
        'apikey: ' . $key,
        'Content-Type: application/json',
        'Accept: application/json',
        'Prefer: return=representation',
    ];
    $body = $payload !== [] ? json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'curl_init failed', 'json' => null];
        }
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($body !== '') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 20,
        ]);
        $responseBody = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if ($curlErr !== '') {
            return ['ok' => false, 'status' => $status, 'body' => $responseBody, 'error' => $curlErr, 'json' => null];
        }
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => 120,
                'ignore_errors' => true,
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $ctx);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        if ($responseBody === false) {
            return ['ok' => false, 'status' => $status, 'body' => '', 'error' => 'HTTP request failed', 'json' => null];
        }
        $responseBody = (string) $responseBody;
    }

    $decoded = null;
    if ($responseBody !== '') {
        $decoded = json_decode($responseBody, true);
    }

    $ok = $status >= 200 && $status < 300;
    $error = '';
    if (!$ok) {
        if (is_array($decoded) && isset($decoded['message'])) {
            $error = (string) $decoded['message'];
        } elseif (is_array($decoded) && isset($decoded['error'])) {
            $error = (string) $decoded['error'];
        } else {
            $error = 'HTTP ' . $status;
        }
    }

    return ['ok' => $ok, 'status' => $status, 'body' => $responseBody, 'error' => $error, 'json' => $decoded];
}

/**
 * @param list<string> $extraHeaders
 * @return array{ok: bool, status: int, body: string, error: string, json: mixed}
 */
function ia_supabase_rest_request_raw(string $method, string $url, array $extraHeaders = [], string $body = ''): array
{
    $cfg = ia_supabase_rest_config();
    if (!$cfg['enabled']) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Supabase REST not configured', 'json' => null];
    }

    $key = $cfg['secret_key'];
    $headers = array_merge([
        'Authorization: Bearer ' . $key,
        'apikey: ' . $key,
        'Accept: application/json',
    ], $extraHeaders);

    if ($body !== '' && !in_array('Content-Type: application/json', $headers, true)) {
        $headers[] = 'Content-Type: application/json';
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'curl_init failed', 'json' => null];
        }
        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } elseif ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($body !== '') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_CONNECTTIMEOUT => 25,
        ]);
        $responseBody = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if ($curlErr !== '') {
            return ['ok' => false, 'status' => $status, 'body' => $responseBody, 'error' => $curlErr, 'json' => null];
        }
    } else {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'curl required', 'json' => null];
    }

    $decoded = null;
    if ($responseBody !== '') {
        $decoded = json_decode($responseBody, true);
    }
    $ok = $status >= 200 && $status < 300;
    $error = $ok ? '' : ('HTTP ' . $status . (is_array($decoded) && isset($decoded['message']) ? ': ' . $decoded['message'] : ''));

    return ['ok' => $ok, 'status' => $status, 'body' => $responseBody, 'error' => $error, 'json' => $decoded];
}

/**
 * @return list<array<string,mixed>>
 */
function ia_supabase_rest_fetch_table(string $table, int $pageSize = 500): array
{
    $cfg = ia_supabase_rest_config();
    $all = [];
    $offset = 0;
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';

    while (true) {
        $end = $offset + $pageSize - 1;
        $url = $cfg['url'] . '/rest/v1/' . rawurlencode($table) . '?select=*';
        $resp = ia_supabase_rest_request_raw('GET', $url, [
            'Range: ' . $offset . '-' . $end,
            'Prefer: count=exact',
        ]);
        if (!$resp['ok']) {
            if ($resp['status'] === 404 || $resp['status'] === 406) {
                return $all;
            }
            throw new RuntimeException('Supabase REST ' . $table . ': ' . $resp['error']);
        }
        $chunk = is_array($resp['json']) ? $resp['json'] : [];
        if ($chunk === []) {
            break;
        }
        foreach ($chunk as $row) {
            if (is_array($row)) {
                $all[] = $row;
            }
        }
        if (count($chunk) < $pageSize) {
            break;
        }
        $offset += $pageSize;
    }

    return $all;
}

/**
 * @param list<mixed> $bind
 * @return array{ok: bool, rows: list<array<string,mixed>>, affected: int, error: string}
 */
function ia_supabase_rest_execute_sql(string $sql, array $bind = []): array
{
    $args = [];
    foreach ($bind as $value) {
        if ($value === null) {
            $args[] = null;
        } elseif (is_bool($value)) {
            $args[] = $value;
        } elseif (is_int($value) || is_float($value)) {
            $args[] = $value;
        } else {
            $args[] = (string) $value;
        }
    }

    $resp = ia_supabase_rest_request('POST', 'rpc/ia_db_execute', [
        'p_sql' => $sql,
        'p_args' => $args,
    ]);

    if (!$resp['ok']) {
        $hint = str_contains(strtolower($resp['error']), 'ia_db_execute')
            ? ' Run sql/supabase_rpc_proxy.sql in Supabase SQL Editor.'
            : '';

        return ['ok' => false, 'rows' => [], 'affected' => 0, 'error' => $resp['error'] . $hint];
    }

    $json = $resp['json'];
    if (!is_array($json)) {
        return ['ok' => false, 'rows' => [], 'affected' => 0, 'error' => 'Invalid RPC response'];
    }
    if (empty($json['ok'])) {
        return ['ok' => false, 'rows' => [], 'affected' => 0, 'error' => (string) ($json['error'] ?? 'RPC failed')];
    }

    $rows = [];
    if (isset($json['rows']) && is_array($json['rows'])) {
        foreach ($json['rows'] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
    }

    return [
        'ok' => true,
        'rows' => $rows,
        'affected' => (int) ($json['affected'] ?? 0),
        'error' => '',
    ];
}

function ia_supabase_rest_connection_fail(string $message): never
{
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        $detail = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Supabase REST</title></head>';
        echo '<body style="font-family:system-ui,sans-serif;padding:2rem;max-width:46rem;line-height:1.55">';
        echo '<h1>Supabase REST (без pdo_pgsql)</h1>';
        echo '<p style="color:#64748b;white-space:pre-wrap">' . $detail . '</p>';
        echo '<ol>';
        echo '<li>В <code>.env</code>: <code>IA_DB_DRIVER=supabase</code>, <code>IA_SUPABASE_URL</code>, <code>IA_SUPABASE_SECRET_KEY</code></li>';
        echo '<li>Supabase → SQL Editor → выполните <code>sql/supabase_rpc_proxy.sql</code></li>';
        echo '<li>Опционально: hPanel → PHP Configuration → включите <code>pgsql</code> / <code>pdo_pgsql</code> и используйте <code>IA_DB_DRIVER=pgsql</code></li>';
        echo '</ol>';
        echo '</body></html>';
        exit(1);
    }

    throw new RuntimeException($message);
}
