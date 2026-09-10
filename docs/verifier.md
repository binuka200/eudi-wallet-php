# Verifier backends

This package speaks the Commission verifier's relying-party HTTP API:

- `POST /ui/presentations/v2` — start an OpenID4VP transaction and receive the
  authoritative `authorization_request_uri`
- `GET /ui/presentations/{transactionId}` — read the wallet response

Any backend that exposes the same contract can be wrapped with
`CommissionVerifier`. A different API should implement
`EudiWallet\Contract\Verifier` instead.

## Commission JVM endpoint

Image: `ghcr.io/eu-digital-identity-wallet/eudi-srv-verifier-endpoint:v0.11.0`

The project README states that both APIs must be HTTPS in production, that the
Verifier API must be authorized, and that the current release is a development
tool. Do not treat Docker Compose in this repository as a production deployment.

## Fake verifier

`FakeVerifier` never talks to a wallet. Use it in PHPUnit and local UI work.
Call `complete($transactionId, $claims)` or `fail(...)` from the test.

## What PHP does not do

- OpenID4VP request object signing
- mdoc COSE verification (the package only decodes an accepted DeviceResponse)
- Holder-binding checks
- Trusted-list and revocation lookups
- Relying-party registration certificates, except passing them through to the
  verifier when you already have one
