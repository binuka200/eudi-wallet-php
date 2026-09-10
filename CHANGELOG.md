# Changelog

## Unreleased

- Fix Commission verifier polling: HTTP 400 without a response code is now
  pending and HTTP 404 is an unknown transaction, matching the pinned
  `v0.11.0` behaviour. Previously every cross-device poll threw until the
  wallet submitted.
- Add a `headers` option to `CommissionVerifier` for protected verifier APIs.
- `VerifierRejected` now exposes `status()` and a truncated `responseBody`.
- Expose the mdoc portrait as the same `data:` URL shape as SD-JWT VC, and
  require a base64 data URL when validating the portrait.
- Reject presentations whose mdoc and SD-JWT VC values disagree instead of
  silently preferring the mdoc value.
- Report malformed mdoc presentations as `InvalidWalletResponse` with the
  decode reason instead of an empty claim set.
- Decode CBOR 64-bit floats.
- **Breaking:** `PidQueryBuilder::build()` no longer takes the unused purpose
  argument; the signature is `build(array $claims, string $format = 'both')`.
- Align PID attributes with PID Rulebook 1.7, including distinct mdoc and
  SD-JWT VC claim paths and removal of obsolete age-over PID attributes.
- Decode structured SD-JWT disclosures and compact mdoc issuer-signed items.
- Reject partial normalized identities, invalid verifier HTTP 400 responses,
  malformed verifier start responses, and expired local wallet sessions.
- Preserve application session state until a verifier response is successfully
  read, allowing recovery from pending or transient failures.
- Use the Commission verifier v2 initialization API and its authoritative
  authorization request URI.
- Generate presentation nonces internally, restrict request URI methods to the
  OpenID4VP `get` and `post` values, and remove the non-standard DCQL purpose
  member.
- Validate normalized PID value types before returning a verified identity.
- Fix the local Compose syntax and pin the current verifier endpoint v0.11.0
  image.

## 0.1.0 - 2026-09-09

- Framework-neutral PHP façade for requesting and reading EUDI Wallet PID
  attributes through a remote OpenID4VP verifier.
- Commission verifier client for `POST/GET /ui/presentations`.
- In-process fake verifier for tests and local application development.
- PID DCQL builder for mdoc and SD-JWT VC, plus best-effort claim normalisation.
- Docker Compose example pinning the Commission verifier development image.
- PHPStan level 8, lint, and PHP 8.1–8.5 CI.
