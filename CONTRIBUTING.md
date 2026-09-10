# Contributing

Thanks for helping PHP applications accept EU Digital Identity Wallets. This
guide covers setup, the checks every change must pass, the places where
contributions are most useful, and the rules that keep the package safe.

## Ground rules

- **This package is a façade, not a verifier.** OpenID4VP, SD-JWT and mdoc
  cryptography, holder binding, trust lists and revocation stay in the remote
  verifier. Pull requests that add a native PHP verifier will not be merged
  unless that work is independently conformance-tested. See
  [docs/architecture.md](docs/architecture.md) for the boundary.
- **No framework code in core.** Laravel, Symfony and other adapters belong in
  separate packages; the core stays PSR-only. Examples in `examples/` are fine.
- **Security-sensitive changes need failure tests.** Anything that touches
  parsing, validation, URLs, sessions or exceptions must include a test for
  the rejection path, not only the happy path.
- **Never commit real data.** No real wallet presentations, PID attributes,
  registration certificates or verifier keystores, in code, tests, fixtures
  or issues.
- **Report vulnerabilities privately** through
  [GitHub private vulnerability reporting](https://github.com/binuka200/eudi-wallet-php/security/advisories/new),
  never as a public issue.

By participating you agree to the [Code of Conduct](CODE_OF_CONDUCT.md).

## Setup

Requirements: PHP 8.1 or newer with `ext-json`, Composer 2, and Docker if you
want to run the live check.

```bash
git clone https://github.com/binuka200/eudi-wallet-php.git
cd eudi-wallet-php
composer install
composer cs:install   # optional, isolated code-style toolchain
```

## Checks

`composer check` is what CI runs on PHP 8.1 through 8.5 plus the
lowest-dependency set. Run it before every push.

| Command | What it does |
|---|---|
| `composer test` | PHPUnit suite |
| `composer lint` | Syntax-checks every PHP file |
| `composer analyse` | PHPStan level 8 on `src` and `tests` |
| `composer check` | The three above, in order |
| `composer cs` | Reports code-style violations; `composer cs:fix` fixes them |
| `composer live-check` | Exercises the client against a running verifier |
| `composer audit --locked` | Known-vulnerability check on locked dependencies |

The style toolchain lives in `tools/php-cs-fixer/` with its own `composer.json`
so it never affects the library's dependency resolution or the
lowest-dependency CI job.

### Live check against a real verifier

Unit tests use `FakeVerifier` and recorded HTTP responses. They cannot tell you
whether the Commission verifier still behaves the way the client assumes. The
live check can, for every path that does not need a wallet:

```bash
docker compose -f docker/docker-compose.yaml up -d
composer live-check
docker compose -f docker/docker-compose.yaml down
```

It starts transactions, polls before submission, sends unknown transaction ids
and bogus response codes, and fetches the request object a wallet would fetch.
CI runs it on every push in the `live-verifier` job. Run it whenever you change
`CommissionVerifier`, `RequestOptions`, `PidQueryBuilder`, or bump the image tag
in `docker/docker-compose.yaml`. `VERIFIER_URL`, `VERIFIER_INTENDED_USE`,
`VERIFIER_TOKEN` and `VERIFIER_PROFILE` point it at another deployment.

What it cannot do is complete a presentation. If you have access to a wallet
that can finish the flow against your verifier, a report of the resulting
`vp_token` shape (with all attribute values replaced) is a valuable
contribution in itself.

## Where help is most useful

- **Verifier compatibility.** Reports of how other OpenID4VP verifiers
  (walt.id, vendors, newer Commission releases) differ from the assumptions in
  [docs/verifier.md](docs/verifier.md). Use the "Verifier compatibility" issue
  template.
- **PID Rulebook tracking.** The attribute map in `src/PidAttributeMap.php`
  follows Rulebook 1.7. When a new rulebook version changes a claim name or
  path, update the map, `Claim`, `PidClaimValidator`, and the tests together.
- **Wire samples.** Real-world `vp_token` layouts for mdoc and SD-JWT VC from
  national wallets, anonymised, as fixtures for `ClaimNormalizer` tests.
- **Additional verifier backends.** Implement `EudiWallet\Contract\Verifier`
  for a verifier with a different HTTP API. Keep it in `src/Verifier/`, cover
  every failure path with a recording transport, and document its contract in
  `docs/verifier.md`.
- **Framework adapters** as separate packages that depend on this one.
- **Documentation and examples** in the languages of Member States.

## How to add or change a PID attribute

1. Add the identifier to `Claim` and to `Claim::known()`.
2. Add its mdoc element name and SD-JWT VC path to `PidAttributeMap`. An
   attribute defined for only one format goes in only one map;
   `PidQueryBuilder` handles the rest.
3. Add its value rule to `PidClaimValidator::isValid()` if the Rulebook gives
   it a type other than plain text.
4. Add a helper to `VerifiedIdentity` only if the attribute is common enough to
   deserve one; `claim()` already exposes everything.
5. Extend `PidAttributeMapTest`, `PidClaimValidatorTest` and, when the wire
   shape is unusual, `ClaimNormalizerTest`.
6. Cite the Rulebook section in the pull request.

## Pull requests

- One logical change per pull request, with a `CHANGELOG.md` entry under
  *Unreleased*. Mark breaking changes as **Breaking:**.
- Commit messages: a short imperative subject, then a body that explains why.
  The history is the design record, so say what the verifier or spec does
  and why the change follows from it.
- New behaviour needs tests. Bug fixes need a test that fails without the fix.
- Keep PHPStan at level 8 without suppressions and without widening types to
  silence it.
- Public API is anything in `src/` that is not marked `@internal`. Changing
  it needs a changelog note and, while the package is pre-1.0, a clear reason.
- Fill in the pull request template; it asks for the checks you ran.

## Releasing

Maintainers tag releases from `main`. Before tagging: move the *Unreleased*
section of `CHANGELOG.md` under the new version and date, confirm the Docker
image tag in `docker/docker-compose.yaml` matches `docs/verifier.md`, and
run `composer check`, `composer cs`, `composer audit --locked` and the live
check.
