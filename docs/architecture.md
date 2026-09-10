# Architecture

This document is for people changing the package. For usage, read the
[README](../README.md); for the verifier HTTP contract, read
[verifier.md](verifier.md).

## The boundary

```
Browser / phone                 PHP application                 Remote verifier
      |                               |                               |
      |  1. user asks to identify     |                               |
      |------------------------------>|  2. request()                 |
      |                               |------ POST /ui/presentations/v2 -->
      |                               |<----- authorization_request_uri ---|
      |  3. link / QR (walletUri)     |                               |
      |<------------------------------|                               |
      |  4. wallet fetches request object, user consents, wallet posts VP
      |------------------------------------------------------------->|
      |                               |  5. poll() / verify()         |
      |                               |------ GET /ui/presentations/{id} -->
      |                               |<----- vp_token (already verified) --|
      |                               |  6. decode, normalise, validate
      |                               |  7. VerifiedIdentity
```

Everything cryptographic happens in step 4 and inside the verifier: request
object signing, SD-JWT and mdoc signature checks, holder binding, nonce
binding, validity, revocation and trust. The PHP side only ever sees a
`vp_token` the verifier has already accepted. That is the whole point of the
design: a PHP shop gets a stable client without a second crypto stack to
audit.

Consequences for contributors:

- The package **decodes** presentations (base64url, CBOR, SD-JWT disclosure
  digests) but never **verifies** them. `SdJwtDisclosureParser` matches
  disclosure digests so only disclosures that belong to the accepted token are
  exposed; that is integrity plumbing, not authentication.
- Anything that would require trusting PHP-side crypto is out of scope. See
  [CONTRIBUTING.md](../CONTRIBUTING.md).
- The verifier response is trusted only because the channel to the verifier
  is. Deployments must authenticate that channel (`headers` on
  `CommissionVerifier`, mTLS, or a private network).

## Modules

| Namespace | Responsibility | Extension point |
|---|---|---|
| `EudiWallet` (root) | Façade, value objects, option validation | `EudiWallet` is final; compose, do not extend |
| `EudiWallet\Contract` | Interfaces and DTOs between layers | `Verifier`, `Transport`, `Observer` |
| `EudiWallet\Verifier` | Verifier clients and the in-process fake | Add a class per backend |
| `EudiWallet\Dcql` | Builds the DCQL query for PID | One builder per credential family |
| `EudiWallet\Identity` | Decoding, normalisation, validation of accepted presentations | `ClaimNormalizer` is injectable |
| `EudiWallet\Exception` | Typed failures | All extend `EudiWalletException` |

### Flow inside `EudiWallet::request()`

1. `RequestOptions` validates every option in its constructor, so invalid
   input fails before any network call.
2. `PidQueryBuilder` maps `Claim` identifiers to mdoc and SD-JWT VC paths via
   `PidAttributeMap` and emits a DCQL query with an either-or credential set.
3. A 32-byte random nonce is generated. It is never accepted from outside.
4. `Verifier::start()` returns a `StartedPresentation`; its constructor
   validates what the verifier sent back.
5. `WalletSession` captures what the app must store server-side: transaction
   id, nonce, requested claims, purpose, creation time. Its constructor and
   `fromArray()` validate every field, so a tampered session is rejected on
   restore.

### Flow inside `EudiWallet::poll()`

1. Local session expiry is checked first, so an expired session never reaches
   the verifier.
2. `Verifier::fetch()` maps HTTP outcomes to `WalletResponse::PENDING`,
   `SUBMITTED` or `FAILED`, or throws.
3. `ClaimNormalizer` turns each presentation into `Claim`-keyed values: compact
   mdoc through `MdocDisclosureParser` and `CborDecoder`, compact SD-JWT through
   `SdJwtDisclosureParser`, already-decoded JSON directly. Presentations that
   disagree on a value are rejected.
4. Every requested claim must be present; extra claims are dropped.
5. `PidClaimValidator` checks value types against the Rulebook.
6. `Observer` events carry counts and codes only, never attribute values.

## Adding a verifier backend

Implement `EudiWallet\Contract\Verifier`:

- `start()` receives the DCQL query and `StartOptions`. Return a
  `StartedPresentation`. Prefer an authoritative wallet URI from the backend
  over reconstructing one; the fallback in `StartedPresentation::walletUri()`
  exists for backends that do not provide one.
- `fetch()` must return `WalletResponse::pending()` only when the backend
  really means "not yet". Anything terminal should throw a typed exception so
  applications can stop polling. Verify the status-code semantics against the
  backend's source, not its README; see the table in
  [verifier.md](verifier.md) for how that went with the Commission verifier.
- Wrap transport failures in `VerifierUnavailable` and non-success statuses
  in `VerifierRejected`, keeping raw bodies out of messages.
- Test every branch with `tests/RecordingTransport.php`, then extend
  `tools/live-check.php` or add a sibling for the real backend.

## Adding a PID attribute

The checklist is in [CONTRIBUTING.md](../CONTRIBUTING.md#how-to-add-or-change-a-pid-attribute).
The invariant to preserve: `Claim` identifiers are encoding-independent, and
only `PidAttributeMap` knows wire names. Nothing else in the package may
mention `birthdate`, `nationalities` or another wire name.

## Decoders

`CborDecoder` is deliberately small: definite-length only (ISO/IEC 18013-5
mandates deterministic encoding), bounded input size, depth and item count.
Do not add features it does not need to read an accepted `DeviceResponse`.

`SdJwtDisclosureParser` supports `sha-256`, `sha-384` and `sha-512`
digests, object and array disclosures, and ignores duplicate or unreferenced
disclosures. It never evaluates the JWT signature or key binding.

## Testing strategy

- **Unit tests** (`tests/`) run without network, using `FakeVerifier` and
  `RecordingTransport`. Every exception path has a test.
- **Live check** (`tools/live-check.php`) runs against a real verifier in CI
  and locally through Docker. It covers everything short of a wallet
  submission.
- **Static analysis** at PHPStan level 8 with no baseline and no suppressions.
- There is no fixture corpus of real presentations yet. Anonymised samples
  from national wallets are welcome; see CONTRIBUTING.md.
