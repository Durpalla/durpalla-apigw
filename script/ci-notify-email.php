#!/usr/bin/env php
<?php

/**
 * Send a CI deploy status email using MAIL_* from the apigw .env (no Laravel bootstrap).
 *
 * Usage:
 *   php ci-notify-email.php \
 *     --env=/opt/durpalla-apigw/.env \
 *     --to=jewelrana.dev@gmail.com \
 *     --subject="…" \
 *     --body-file=/path/to/body.txt
 */

declare(strict_types=1);

$opts = getopt('', ['env:', 'to:', 'subject:', 'body:', 'body-file:']);
$envFile = $opts['env'] ?? '';
$to = $opts['to'] ?? '';
$subject = $opts['subject'] ?? 'Apigw deploy status';
$body = $opts['body'] ?? '';
if (($opts['body-file'] ?? '') !== '' && is_readable($opts['body-file'])) {
    $body = (string) file_get_contents($opts['body-file']);
}

if ($envFile === '' || ! is_readable($envFile)) {
    fwrite(STDERR, "ERROR: --env file missing or unreadable\n");
    exit(2);
}
if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "ERROR: invalid --to address\n");
    exit(2);
}
if (trim($body) === '') {
    fwrite(STDERR, "ERROR: empty body\n");
    exit(2);
}

$env = loadEnv($envFile);
$host = trim((string) ($env['MAIL_HOST'] ?? ''));
$port = (int) ($env['MAIL_PORT'] ?? 587);
$user = nullIfEmpty($env['MAIL_USERNAME'] ?? null);
$pass = nullIfEmpty($env['MAIL_PASSWORD'] ?? null);
$from = trim((string) ($env['MAIL_FROM_ADDRESS'] ?? $user ?? 'noreply@durpalla.com'), "\"'");
$fromName = trim((string) ($env['MAIL_FROM_NAME'] ?? 'Durpalla API Gateway'), "\"'");
$encryption = strtolower(trim((string) ($env['MAIL_ENCRYPTION'] ?? $env['MAIL_SCHEME'] ?? '')));
$mailer = strtolower(trim((string) ($env['MAIL_MAILER'] ?? 'smtp')));

if (in_array($mailer, ['log', 'array'], true)) {
    fwrite(STDERR, "WARN: MAIL_MAILER={$mailer} — skipping real send (would only log in Laravel).\n");
    exit(0);
}
if ($host === '' || $host === '127.0.0.1' || $host === 'localhost') {
    fwrite(STDERR, "WARN: MAIL_HOST is empty/local — skipping email notify.\n");
    exit(0);
}

// Laravel 11+ often uses MAIL_SCHEME=smtp|smtps instead of MAIL_ENCRYPTION.
if ($encryption === 'null' || $encryption === '') {
    $encryption = ($port === 465) ? 'ssl' : 'tls';
}
if ($encryption === 'smtps') {
    $encryption = 'ssl';
}
if ($encryption === 'smtp') {
    $encryption = ($port === 465) ? 'ssl' : 'tls';
}

try {
    sendSmtp(
        host: $host,
        port: $port,
        encryption: $encryption,
        username: $user,
        password: $pass,
        fromEmail: $from,
        fromName: $fromName,
        toEmail: $to,
        subject: $subject,
        body: $body,
    );
    fwrite(STDOUT, "Email sent to {$to}\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: '.$e->getMessage()."\n");
    exit(1);
}

/** @return array<string, string> */
function loadEnv(string $path): array
{
    $out = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (! str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (
            (str_starts_with($v, '"') && str_ends_with($v, '"'))
            || (str_starts_with($v, "'") && str_ends_with($v, "'"))
        ) {
            $v = substr($v, 1, -1);
        }
        $out[$k] = $v;
    }

    return $out;
}

function nullIfEmpty(mixed $v): ?string
{
    if ($v === null) {
        return null;
    }
    $s = trim((string) $v, " \t\"'");
    if ($s === '' || strtolower($s) === 'null') {
        return null;
    }

    return $s;
}

function sendSmtp(
    string $host,
    int $port,
    string $encryption,
    ?string $username,
    ?string $password,
    string $fromEmail,
    string $fromName,
    string $toEmail,
    string $subject,
    string $body,
): void {
    $remote = ($encryption === 'ssl')
        ? "ssl://{$host}:{$port}"
        : "tcp://{$host}:{$port}";

    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, 30);
    if ($fp === false) {
        throw new RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
    }
    stream_set_timeout($fp, 30);

    expect($fp, [220]);
    $ehloHost = gethostname() ?: 'apigw-deploy';
    command($fp, "EHLO {$ehloHost}", [250]);

    if ($encryption === 'tls') {
        command($fp, 'STARTTLS', [220]);
        if (! stream_socket_enable_crypto($fp, true, smtpCryptoMethod())) {
            throw new RuntimeException('STARTTLS negotiation failed');
        }
        command($fp, "EHLO {$ehloHost}", [250]);
    }

    if ($username !== null && $password !== null) {
        command($fp, 'AUTH LOGIN', [334]);
        command($fp, base64_encode($username), [334]);
        command($fp, base64_encode($password), [235]);
    }

    command($fp, 'MAIL FROM:<'.$fromEmail.'>', [250]);
    command($fp, 'RCPT TO:<'.$toEmail.'>', [250, 251]);
    command($fp, 'DATA', [354]);

    $headers = [
        'Date: '.date('r'),
        'From: '.formatAddress($fromName, $fromEmail),
        'To: '.$toEmail,
        'Subject: '.encodeHeader($subject),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: durpalla-apigw-ci-notify',
    ];
    $payload = implode("\r\n", $headers)."\r\n\r\n".dotStuff($body)."\r\n.";
    fwrite($fp, $payload."\r\n");
    expect($fp, [250]);
    command($fp, 'QUIT', [221]);
    fclose($fp);
}

/** @param resource $fp @param list<int> $ok */
function command($fp, string $line, array $ok): void
{
    fwrite($fp, $line."\r\n");
    expect($fp, $ok);
}

/** @param resource $fp @param list<int> $ok */
function expect($fp, array $ok): void
{
    $response = '';
    while (($line = fgets($fp, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    $code = (int) substr($response, 0, 3);
    if (! in_array($code, $ok, true)) {
        throw new RuntimeException('SMTP unexpected reply: '.trim($response));
    }
}

function smtpCryptoMethod(): int
{
    $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
    if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
        $method = constant('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT');
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $method |= constant('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT');
        }
    }

    return $method;
}

function formatAddress(string $name, string $email): string
{
    $name = trim($name);
    if ($name === '') {
        return $email;
    }

    return encodeHeader($name).' <'.$email.'>';
}

function encodeHeader(string $value): string
{
    if (preg_match('/^[\x20-\x7E]+$/', $value)) {
        return $value;
    }

    return '=?UTF-8?B?'.base64_encode($value).'?=';
}

function dotStuff(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = str_replace("\n", "\r\n", $body);
    $body = preg_replace('/^\./m', '..', $body) ?? $body;

    return $body;
}
