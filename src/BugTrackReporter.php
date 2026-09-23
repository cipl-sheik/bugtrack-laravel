<?php

namespace Ciplnew\BugTracking;

use Error;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Illuminate\View\ViewException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Builds the official Go ingest payload and POSTs it to /api/v1/ingest.
 */
class BugTrackReporter
{
    private const MAX_FRAMES = 30;

    private const CONTEXT_LINES = 5;

    /** @var array<int, true> */
    private static $reported = [];

    public static function report(Throwable $e): void
    {
        $id = spl_object_id($e);
        if (isset(self::$reported[$id])) {
            return;
        }
        self::$reported[$id] = true;

        [$url, $key] = self::endpoint();

        if (! config('bugtracking.enabled') || $url === '' || $key === '' || self::isIgnored($e)) {
            return;
        }

        try {
            self::send($url, $key, self::payload(self::unwrap($e)));
        } catch (Throwable $ignored) {
            // Reporting must never replace the original failure.
        }
    }

    private static function unwrap(Throwable $e): Throwable
    {
        return $e instanceof ViewException && $e->getPrevious() !== null
            ? $e->getPrevious()
            : $e;
    }

    private static function isIgnored(Throwable $e): bool
    {
        foreach ((array) config('bugtracking.ignore', []) as $ignored) {
            if (is_string($ignored) && $e instanceof $ignored) {
                return true;
            }
        }

        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode() < 500;
        }

        return $e instanceof ValidationException
            || $e instanceof AuthenticationException
            || $e instanceof TokenMismatchException;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function endpoint(): array
    {
        $dsn = trim((string) config('bugtracking.dsn', ''));
        $url = rtrim(trim((string) config('bugtracking.url', '')), '/');
        $key = trim((string) config('bugtracking.key', ''));

        if ($dsn !== '') {
            $parts = parse_url($dsn);
            if (is_array($parts) && ! empty($parts['user'])) {
                $key = (string) $parts['user'];
            }
            if ($url === '' && is_array($parts) && ! empty($parts['host'])) {
                $scheme = $parts['scheme'] ?? 'http';
                $port = isset($parts['port']) ? ':'.$parts['port'] : '';
                $url = $scheme.'://'.$parts['host'].$port.'/api/v1/ingest';
            }
        }

        if ($url === '') {
            $url = 'http://127.0.0.1:8080/api/v1/ingest';
        } elseif (! preg_match('#/api/#', $url)) {
            $url .= '/api/v1/ingest';
        }

        return [$url, $key];
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(Throwable $e): array
    {
        $culprit = self::culprit($e);
        $file = self::relative(self::sourceFile($e->getFile()));

        return array_filter([
            'event_id' => self::uuid(),
            'level' => $e instanceof Error ? 'fatal' : 'error',
            'environment' => (string) config('bugtracking.environment'),
            'platform' => 'php',
            'timestamp' => gmdate('c'),
            'culprit' => $culprit,
            'server_name' => $_SERVER['SERVER_NAME'] ?? gethostname(),
            'transaction' => self::transaction(),
            'exception' => [
                'type' => get_class($e),
                'message' => $e->getMessage() !== '' ? $e->getMessage() : get_class($e),
                'file' => $file,
                'line' => $e->getLine(),
                'function' => $culprit,
            ],
            'stacktrace' => self::frames($e),
            'request' => self::requestContext(),
            'user' => self::userContext(),
            'runtime' => [
                'name' => 'php',
                'version' => PHP_VERSION,
                'os_name' => php_uname('s'),
                'os_version' => php_uname('r'),
                'laravel' => self::laravelVersion(),
            ],
            'sdk' => [
                'name' => 'bugtrack-laravel',
                'version' => BugTrackServiceProvider::VERSION,
            ],
            'extra' => [
                'error_file_details' => self::errorFileDetails($e, $file),
            ],
        ], static function ($value) {
            return $value !== null;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function frames(Throwable $e): array
    {
        $originAbs = self::sourceFile($e->getFile());
        $origin = self::relative($originAbs);

        $frames = [array_merge([
            'filename' => $origin,
            'function' => self::culprit($e),
            'lineno' => $e->getLine(),
            'in_app' => self::isInApp($origin),
        ], self::sourceSnippet($originAbs, $e->getLine()))];

        foreach (array_slice($e->getTrace(), 0, self::MAX_FRAMES) as $frame) {
            $abs = isset($frame['file']) ? self::sourceFile($frame['file']) : '';
            $file = $abs !== '' ? self::relative($abs) : '';
            $line = (int) ($frame['line'] ?? 0);

            $frames[] = array_merge([
                'filename' => $file,
                'function' => self::frameFunction($frame),
                'class' => $frame['class'] ?? '',
                'lineno' => $line,
                'in_app' => self::isInApp($file),
            ], self::sourceSnippet($abs, $line));
        }

        return $frames;
    }

    private static function culprit(Throwable $e): string
    {
        $frame = $e->getTrace()[0] ?? null;

        return is_array($frame) ? self::frameFunction($frame) : '';
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    private static function frameFunction(array $frame): string
    {
        $function = (string) ($frame['function'] ?? '');

        if (! isset($frame['class'])) {
            return $function;
        }

        return $frame['class'].($frame['type'] ?? '::').$function;
    }

    private static function isInApp(string $file): bool
    {
        return $file !== '' && strpos($file, 'vendor/') !== 0;
    }

    private static function sourceFile(string $file): string
    {
        $compiled = config('view.compiled');

        if (! is_string($compiled) || $compiled === '' || strpos($file, $compiled) !== 0) {
            return $file;
        }

        $contents = @file_get_contents($file);

        if ($contents !== false && preg_match('#/\*\*PATH (.+?) ENDPATH\*\*/#', $contents, $matches) === 1) {
            return $matches[1];
        }

        return $file;
    }

    private static function relative(string $file): string
    {
        if ($file === '') {
            return '';
        }

        $normalized = str_replace('\\', '/', $file);
        $base = rtrim(str_replace('\\', '/', (string) (function_exists('base_path') ? base_path() : '')), '/').'/';

        if ($base !== '/' && strpos($normalized, $base) === 0) {
            return substr($normalized, strlen($base));
        }

        return $normalized;
    }

    /**
     * @return array{
     *     error_file: string,
     *     error_line: int,
     *     pre_context: list<string>,
     *     context_line: string,
     *     post_context: list<string>,
     *     context: list<array{line_number: int, line_content: string, is_error_line: bool}>
     * }
     */
    private static function errorFileDetails(Throwable $e, string $relativeFile): array
    {
        $abs = self::sourceFile($e->getFile());
        $line = $e->getLine();
        $snippet = self::sourceSnippet($abs, $line);

        return [
            'error_file' => $relativeFile,
            'error_line' => $line,
            'pre_context' => $snippet['pre_context'],
            'context_line' => $snippet['context_line'],
            'post_context' => $snippet['post_context'],
            'context' => self::codeContext($abs, $line),
        ];
    }

    /**
     * Sentry-style source window used by the BugTrack issue detail page.
     *
     * @return array{pre_context: list<string>, context_line: string, post_context: list<string>}
     */
    private static function sourceSnippet(string $file, int $line, int $around = self::CONTEXT_LINES): array
    {
        $empty = [
            'pre_context' => [],
            'context_line' => '',
            'post_context' => [],
        ];

        if ($file === '' || $line < 1) {
            return $empty;
        }

        $lines = @file($file);
        if ($lines === false || ! isset($lines[$line - 1])) {
            return $empty;
        }

        $start = max(1, $line - $around);
        $end = min(count($lines), $line + $around);
        $pre = [];
        $post = [];

        for ($n = $start; $n < $line; $n++) {
            $pre[] = rtrim($lines[$n - 1], "\r\n");
        }

        for ($n = $line + 1; $n <= $end; $n++) {
            $post[] = rtrim($lines[$n - 1], "\r\n");
        }

        return [
            'pre_context' => $pre,
            'context_line' => rtrim($lines[$line - 1], "\r\n"),
            'post_context' => $post,
        ];
    }

    /**
     * @return list<array{line_number: int, line_content: string, is_error_line: bool}>
     */
    private static function codeContext(string $file, int $errorLine, int $around = self::CONTEXT_LINES): array
    {
        $snippet = self::sourceSnippet($file, $errorLine, $around);
        $context = [];
        $number = max(1, $errorLine - count($snippet['pre_context']));

        foreach ($snippet['pre_context'] as $text) {
            $context[] = [
                'line_number' => $number,
                'line_content' => $text,
                'is_error_line' => false,
            ];
            $number++;
        }

        if ($snippet['context_line'] !== '' || $errorLine > 0) {
            $context[] = [
                'line_number' => $errorLine,
                'line_content' => $snippet['context_line'],
                'is_error_line' => true,
            ];
            $number = $errorLine + 1;
        }

        foreach ($snippet['post_context'] as $text) {
            $context[] = [
                'line_number' => $number,
                'line_content' => $text,
                'is_error_line' => false,
            ];
            $number++;
        }

        return $context;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function requestContext(): ?array
    {
        if (! function_exists('app') || app()->runningInConsole()) {
            return null;
        }

        $request = request();

        return [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
        ];
    }

    private static function transaction(): string
    {
        if (! function_exists('app') || app()->runningInConsole()) {
            return '';
        }

        $route = request()->route();
        if ($route && method_exists($route, 'uri')) {
                return strtoupper(request()->method()).' /'.ltrim($route->uri(), '/');
        }

        return request()->path();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function userContext(): ?array
    {
        if (! function_exists('app') || app()->runningInConsole() || ! auth()->hasUser()) {
            return null;
        }

        $user = auth()->user();

        return array_filter([
            'id' => (string) $user->getAuthIdentifier(),
            'email' => $user->email ?? null,
        ], static function ($value) {
            return $value !== null && $value !== '';
        });
    }

    private static function laravelVersion(): string
    {
        if (function_exists('app')) {
            return (string) app()->version();
        }

        return '';
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function send(string $url, string $key, array $payload): void
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => (int) config('bugtracking.connect_timeout_ms', 2000),
            CURLOPT_TIMEOUT_MS => (int) config('bugtracking.timeout_ms', 3000),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Bugtrack-Key: '.$key,
                'Authorization: Bearer '.$key,
            ],
        ]);
        curl_exec($curl);
        curl_close($curl);
    }
}
