# EUDI Wallet for PHP

A framework-neutral PHP façade for accepting EU Digital Identity Wallets.

It does **not** implement OpenID4VP, SD-JWT verification, or mdoc/COSE. Those
stay in a remote verifier. This package turns that verifier into ordinary PHP:
request claims, show a wallet link or QR payload, then read a typed identity.
It does not create application sessions, users, routes, or database records.

```php
use EudiWallet\Claim;
use EudiWallet\EudiWallet;
use EudiWallet\RequestOptions;
use EudiWallet\Verifier\FakeVerifier;

$wallet = new EudiWallet(new FakeVerifier()); // tests / local stubs only

$challenge = $wallet->request([
    Claim::FAMILY_NAME,
    Claim::GIVEN_NAME,
], new RequestOptions(purpose: 'Account identification'));

$_SESSION['eudi'] = $challenge->session->toArray(); // Keep server-side or integrity-protected.
// Render $challenge->walletUri or $challenge->qrPayload()
```

After the wallet returns:

```php
use EudiWallet\WalletSession;

$session = WalletSession::fromArray($_SESSION['eudi']);

$identity = $wallet->verify($session, $_GET['response_code'] ?? null);
unset($_SESSION['eudi']); // Clear only after a terminal, successfully read response.

// Continue. Persist only the attributes you still need.
```

## What this is for

EU Member States must make at least one EUDI Wallet available by
24 December 2026. Many covered private relying parties must *accept* a wallet,
on the user's request, around 24 December 2027 where strong authentication is
already required. Server location is not the legal trigger.

PHP is common in European public-sector portals, regulated services, and CMS
installations. Those apps need a stable client, not a second cryptography stack.

## Install

```bash
composer require binuka200/eudi-wallet
```

Bring a PSR-18 HTTP client and PSR-17 factories when talking to a real verifier.
`FakeVerifier` needs no HTTP client.

## Production shape

```
PHP app  --this package-->  OpenID4VP verifier  <-->  EUDI Wallet
```

| You run | Cryptographic verification |
|---|---|
| This Composer package | Commission JVM verifier, walt.id, or a vendor |

```php
use EudiWallet\EudiWallet;
use EudiWallet\Verifier\CommissionVerifier;
use EudiWallet\Verifier\Psr18Transport;

$transport = new Psr18Transport($psr18Client, $requestFactory, $streamFactory);
$verifier = new CommissionVerifier($transport, $_ENV['VERIFIER_URL'], intendedUseId: $_ENV['VERIFIER_INTENDED_USE']);
$wallet = new EudiWallet($verifier);
```

Verifier `v0.11.0` refuses to start a transaction unless it carries either a
configured `intended_use_id` or a relying-party `registration_certificate`.
Set one as the default on `CommissionVerifier`, or per request through
`RequestOptions`; a per-request value wins. The Docker image below ships one
intended use with id `1`. Without either, the start fails with
`VerifierRejected: ... MissingRegistrationCertificate`.

`VERIFIER_URL` must be HTTPS unless you pass `allowInsecureHttp: true` for local
Docker. When the verifier API is protected, pass the credential as a header
sent with every request:

```php
$verifier = new CommissionVerifier($transport, $_ENV['VERIFIER_URL'], headers: [
    'Authorization' => 'Bearer '.$_ENV['VERIFIER_TOKEN'],
]);
```

The Commission
[verifier endpoint](https://github.com/eu-digital-identity-wallet/eudi-srv-verifier-endpoint)
is a development tool; assess any backend before production use.

Local Compose file: [docker/README.md](docker/README.md).

The challenge uses the complete `authorization_request_uri` returned by version
2 of the Commission verifier API. The package does not reconstruct or override
that URI. Presentation nonces are always generated internally from 32
cryptographically random bytes; they cannot be supplied by browser input or
application code. The verifier binds the wallet response to that nonce; this
package does not compare it again afterwards, so it lives in `WalletSession`
only as opaque server-side state.

## Requested claims

`request()` understands the encoding-independent EU PID identifiers in `Claim`.
It maps each identifier to the correct mdoc (`eu.europa.ec.eudi.pid.1`) and
SD-JWT VC (`urn:eudi:pid:1`) wire path, so national wallets can choose a format.
For example, `Claim::BIRTH_DATE` maps to mdoc `birth_date` and SD-JWT
`birthdate`; address fields map into the SD-JWT `address` object. If an
attribute is defined for only one format (currently `resident_house_number`),
the default `both` mode requests the format that can carry it; explicitly
requesting an incompatible format fails before contacting the verifier.

The current PID Rulebook does not define `age_over_18` or other age-over claims.
For an age-only use case, use a suitable age attestation rather than requesting
more identifying PID data. `ageOver18()` and `ageOver21()` remain convenience
helpers when your lawful use case already requires `Claim::BIRTH_DATE`.

Same-device flows should set `redirectUriTemplate` with `{RESPONSE_CODE}`.
Cross-device flows omit it and call `poll()` until the wallet submits.

`purpose` is application context, not a DCQL property. Display it to the user
before redirecting to the Wallet; it is retained in `WalletSession` but is not
inserted into the standards-facing DCQL query.

```php
$identity = $wallet->poll($session);
if ($identity === null) {
    // Still waiting
}
```

`poll()` returns `null` only while the wallet has not submitted. Every
exception is terminal for that session: `PresentationExpired` when the local
session lifetime passed, `VerifierRejected` when the verifier no longer knows
the transaction or refuses a same-device `response_code`, `PresentationFailed`
when the wallet declined, and `InvalidWalletResponse` when the accepted
presentation cannot be read.

## Claim reading

`VerifiedIdentity` exposes helpers such as `ageOver18()`, `familyName()`, and
`nationalities()`. Raw `vp_token` data remains available. Compact mdoc/CBOR and
SD-JWT disclosures are decoded only after the verifier has accepted them;
signature, holder-binding, validity, revocation, and trust checks remain the
verifier's responsibility. Requested PID values are then checked against the
Rulebook's format-independent types before a `VerifiedIdentity` is returned.

Values are exposed in one shape regardless of wire format: the mdoc portrait
byte string becomes the same `data:` URL the SD-JWT VC `picture` claim uses.
If a wallet returns both an mdoc and an SD-JWT VC and they disagree on an
attribute, the presentation is rejected with `InvalidWalletResponse` rather
than silently preferring one.

## Failure behavior

Verifier and presentation failures extend `EudiWalletException`. Invalid
constructor or method arguments throw `InvalidArgumentException`. Important
package exceptions include:

- `UnknownClaim`, `InvalidConfiguration`
- `VerifierUnavailable`, `VerifierRejected` (HTTP status in `status()`, up to
  2 KiB of the verifier body in `responseBody` for operator logs only)
- `PresentationPending`, `PresentationExpired`, `PresentationFailed`
- `InvalidWalletResponse`

Map detailed failures to a generic error at the public boundary. Never return
wallet payloads, PID attributes, or verifier errors to an unauthenticated client.

Implement `Observer` for events such as `presentation.started` and
`presentation.verified`. Context never includes names, dates of birth, tokens,
or presentations.

## Framework examples

- [Plain PHP](examples/plain-php.md)
- [Laravel](examples/laravel.md)
- [Symfony](examples/symfony.md)

## Development

```bash
composer install
composer check          # lint + PHPStan level 8 + PHPUnit, what CI runs
composer audit --locked
```

To exercise the client against the real Commission verifier without a wallet:

```bash
docker compose -f docker/docker-compose.yaml up -d
composer live-check
```

CI covers PHP 8.1 through 8.5, the lowest supported dependency set, code
style, and the live check against the pinned verifier image.

## Contributing

Verifier compatibility reports, PID Rulebook updates, anonymised wire samples
and additional verifier backends are the most useful contributions. Read
[CONTRIBUTING.md](CONTRIBUTING.md) for setup and the checks every change must
pass, and [docs/architecture.md](docs/architecture.md) for the design boundary
that keeps cryptography out of PHP. This project follows the
[Contributor Covenant](CODE_OF_CONDUCT.md).

## License

MIT
