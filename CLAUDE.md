# SendKit PHP SDK

## Project Overview

Pure PHP SDK for the SendKit email API. No framework dependencies — just Guzzle.

## Architecture

```
src/
├── SendKit.php              # Static factory: SendKit::client('key')
├── Client.php               # Main client, holds Guzzle, exposes ->emails()
├── Emails.php               # Email service (send, sendMime)
└── Exceptions/
    └── SendKitException.php # API error wrapper (message + HTTP status)
```

- `SendKit::client()` creates a `Client` with configured Guzzle instance
- `Client` accepts optional `ClientInterface` for testing
- `Emails::send()` → `POST /v1/emails` (structured params)
- `Emails::sendMime()` → `POST /v1/emails/mime` (envelope + raw MIME)

## Dependencies

- PHP ^8.2
- guzzlehttp/guzzle ^7.5
- pestphp/pest ^3.0 (dev)

## PHP Conventions

- Always use `declare(strict_types=1)` at the top of every PHP file
- Always use explicit return type declarations
- Use PHP 8 constructor property promotion
- Use `readonly` properties where appropriate
- Prefer PHPDoc blocks over inline comments

## Testing

- Tests use Pest 3
- Run tests: `vendor/bin/pest`
- Use Guzzle's `MockHandler` + `Middleware::history()` for HTTP mocking
- Inject mock Guzzle via `Client` constructor: `new Client('key', http: $mockGuzzle)`

## Releasing

- Tags use numeric format: `0.1.0`, `0.2.0`, `1.0.0` (no `v` prefix)
- CI runs tests on PHP 8.2, 8.3, 8.4
- Pushing a tag triggers tests → Packagist update

## Git

- NEVER add `Co-Authored-By` lines to commit messages
