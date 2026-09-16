# BugTrack Laravel package

Reports Laravel exceptions to the **Go BugTrack API** (`POST /api/v1/ingest`).

This package no longer talks to the old Python endpoint at `https://bugtracking.colanapps.in/api/bugtrack/`.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- A running BugTrack Go API (default `http://127.0.0.1:8080`)
- A project API key from the BugTrack dashboard

## Install

```bash
composer require ciplnew/bugtracking
```

Laravel package auto-discovery registers `BugTrackServiceProvider`. Exceptions are reported automatically — you do **not** need to call the handler yourself.

If auto-discovery is disabled, add the provider in `config/app.php`:

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

`BUG_TRCAK_KEY` is still read as a fallback so existing env files keep working.

## What is sent

JSON matching the Go ingest contract:

- `exception.type` / `exception.message` / `exception.file` / `exception.line`
- `stacktrace` frames
- `request`, `user`, `runtime`, `sdk`
- `X-Bugtrack-Key` (and `Authorization: Bearer`)

Validation, auth, CSRF, and HTTP 4xx errors are not reported.

Reporting is fire-and-forget. If the Go API is down, the original Laravel error is unchanged.

## Legacy Handler.php

Older installs called:

```php
\Ciplnew\BugTracking\BugTrackController::bugTrack($e);
```

That method still works and now posts to the Go API. Remove it if the service provider is registered, or the same exception may be sent twice (the reporter de-dupes by object id within a request).
