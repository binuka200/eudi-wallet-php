## What and why

<!-- One paragraph. What changes, and what the verifier, spec or rulebook does that makes this the right change. -->

## Checks

- [ ] `composer check` passes locally
- [ ] `composer cs` passes (run `composer cs:install` once)
- [ ] `composer live-check` run against a verifier, when the change touches `CommissionVerifier`, `RequestOptions`, `PidQueryBuilder` or the Docker image
- [ ] Tests cover the failure path, not only the happy path
- [ ] `CHANGELOG.md` has an entry under *Unreleased* (marked **Breaking:** if the public API changes)
- [ ] No real presentations, attributes, certificates or keystores anywhere in the diff

## Spec references

<!-- Links to the OpenID4VP, HAIP, PID Rulebook or verifier source lines relied on. -->
