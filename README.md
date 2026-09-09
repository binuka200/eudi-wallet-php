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
    Claim::AGE_OVER_18,
    Claim::FAMILY_NAME,
], new RequestOptions(purpose: 'Age verification'));

$_SESSION['eudi'] = $challenge->session->toArray();
// Render $challenge->walletUri or $challenge->qrPayload()
```

After the wallet returns:

```php
use EudiWallet\WalletSession;

$session = WalletSession::fromArray($_SESSION['eudi']);
unset($_SESSION['eudi']);

$identity = $wallet->verify($session, $_GET['response_code'] ?? null);

if ($identity->ageOver18()) {
    // Continue. Persist only the attributes you still need.
}
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

`request()` currently understands EU PID attributes in `Claim`. The DCQL query
asks for either mdoc (`eu.europa.ec.eudi.pid.1`) or SD-JWT VC (`urn:eudi:pid:1`)
so national wallets can choose a format.

Same-device flows should set `redirectUriTemplate` with `{RESPONSE_CODE}`.
Cross-device flows omit it and call `poll()` until the wallet submits.

```php
$identity = $wallet->poll($session);
if ($identity === null) {
    // Still waiting
}
```

## Claim reading

`VerifiedIdentity` exposes helpers such as `ageOver18()` and `familyName()`.
Raw `vp_token` data remains available. Compact mdoc/CBOR presentations are not
decoded here; SD-JWT disclosures and JSON presentations are read after the
verifier has already accepted them.

## Failure behavior

Every package failure extends `EudiWalletException`. Important subclasses:

- `UnknownClaim`, `InvalidConfiguration`
- `VerifierUnavailable`, `VerifierRejected`
- `PresentationPending`, `PresentationFailed`
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
