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
$verifier = new CommissionVerifier($transport, $_ENV['VERIFIER_URL']);
$wallet = new EudiWallet($verifier);
```

`VERIFIER_URL` must be HTTPS unless you pass `allowInsecureHttp: true` for local
Docker. The Commission
[verifier endpoint](https://github.com/eu-digital-identity-wallet/eudi-srv-verifier-endpoint)
is a development tool; assess any backend before production use.

Local Compose file: [docker/README.md](docker/README.md).

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

```php
$identity = $wallet->poll($session);
if ($identity === null) {
    // Still waiting
}
```

## Claim reading

`VerifiedIdentity` exposes helpers such as `ageOver18()`, `familyName()`, and
`nationalities()`. Raw `vp_token` data remains available. Compact mdoc/CBOR and
SD-JWT disclosures are decoded only after the verifier has accepted them;
signature, holder-binding, validity, revocation, and trust checks remain the
verifier's responsibility.

## Failure behavior

Verifier and presentation failures extend `EudiWalletException`. Invalid
constructor or method arguments throw `InvalidArgumentException`. Important
package exceptions include:

- `UnknownClaim`, `InvalidConfiguration`
- `VerifierUnavailable`, `VerifierRejected`
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
composer check
composer audit --locked
```

CI covers PHP 8.1 through 8.5, including the lowest supported dependency set.
The codebase is checked at PHPStan level 8.

## License

MIT
