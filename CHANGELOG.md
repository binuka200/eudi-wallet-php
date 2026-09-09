# Changelog

## Unreleased

- Align PID attributes with PID Rulebook 1.7, including distinct mdoc and
  SD-JWT VC claim paths and removal of obsolete age-over PID attributes.
- Decode structured SD-JWT disclosures and compact mdoc issuer-signed items.
- Reject partial normalized identities, invalid verifier HTTP 400 responses,
  malformed verifier start responses, and expired local wallet sessions.
- Preserve application session state until a verifier response is successfully
  read, allowing recovery from pending or transient failures.

## 0.1.0 - 2026-09-09

- Framework-neutral PHP façade for requesting and reading EUDI Wallet PID
  attributes through a remote OpenID4VP verifier.
- Commission verifier client for `POST/GET /ui/presentations`.
- In-process fake verifier for tests and local application development.
- PID DCQL builder for mdoc and SD-JWT VC, plus best-effort claim normalisation.
- Docker Compose example pinning the Commission verifier development image.
- PHPStan level 8, lint, and PHP 8.1–8.5 CI.
