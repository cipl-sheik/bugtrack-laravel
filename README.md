# BugTrack Laravel package

Reports Laravel exceptions to the **Go BugTrack API** (`POST /api/v1/ingest`).

This package no longer talks to the old Python endpoint at `https://bugtracking.colanapps.in/api/bugtrack/`.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- A running BugTrack Go API (default `http://127.0.0.1:8080`)
- A project API key from the BugTrack dashboard

## Install

Packagist has no tagged releases yet, so require **`dev-main`** from GitHub:

```bash
composer config repositories.bugtrack vcs https://github.com/cipl-sheik/bugtrack-laravel.git
composer require ciplnew/bugtracking:dev-main
```

That is the full install. Laravel package auto-discovery registers `BugTrackServiceProvider`. Exceptions are reported automatically.

**Do not** add any of the following (those were for the old Python package):

- `Ciplnew\BugTracking\BugTrackServiceProvider::class` in `config/app.php` or `bootstrap/providers.php`
- `BugTrackController::bugTrack($e)` in `bootstrap/app.php` or `app/Exceptions/Handler.php`

If auto-discovery is disabled, register the provider only:

Laravel 12 / 11 (`bootstrap/providers.php`):

```php
return [
    App\Providers\AppServiceProvider::class,
    Ciplnew\BugTracking\BugTrackServiceProvider::class,
];
```

Laravel 10 (`config/app.php`):

```php
Ciplnew\BugTracking\BugTrackServiceProvider::class,
```

Publish config (optional):

```bash
php artisan vendor:publish --tag=bugtracking-config
```

## Configure

In the host app `.env`:

```env
BUGTRACK_ENABLED=true
BUGTRACK_DSN=http://YOUR_PROJECT_KEY@127.0.0.1:8080/1
BUGTRACK_ENVIRONMENT=local
```

Or URL + key:

```env
BUGTRACK_URL=http://127.0.0.1:8080/api/v1/ingest
BUGTRACK_KEY=YOUR_PROJECT_KEY
```

`BUG_TRCAK_KEY` is still read as a fallback so existing env files keep working. A key alone is not enough to hit the old Python site — set `BUGTRACK_DSN` or `BUGTRACK_URL` to your **Go** API.

## What is sent

JSON matching the Go ingest contract:

- `exception.type` / `exception.message` / `exception.file` / `exception.line`
- `stacktrace` frames
- `request`, `user`, `runtime`, `sdk`
- `X-Bugtrack-Key` (and `Authorization: Bearer`)

Validation, auth, CSRF, and HTTP 4xx errors are not reported.

Reporting is fire-and-forget. If the Go API is down, the original Laravel error is unchanged.

## Migrating an app that used the Python package

1. `composer require ciplnew/bugtracking:dev-main` (with the GitHub `repositories` entry above).
2. Remove `BugTrackController::bugTrack($e)` from `bootstrap/app.php` / `Handler.php`.
3. Remove the extra provider line if auto-discovery is enabled.
4. Replace the colanapps Python URL with the Go DSN or `BUGTRACK_URL`.
5. Keep `BUG_TRCAK_KEY` or rename it to `BUGTRACK_KEY`.

## Legacy Handler.php

Older installs called:

```php
\Ciplnew\BugTracking\BugTrackController::bugTrack($e);
```

That method still works and now posts to the Go API. Remove it if the service provider is registered, or the same exception may be sent twice (the reporter de-dupes by object id within a request).
