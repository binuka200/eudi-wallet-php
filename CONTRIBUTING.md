# Contributing

Security-sensitive changes need tests for their failure behavior. Please run
`composer test` and `composer lint` before opening a pull request. Avoid adding
framework-specific behavior to the core package; adapters can live in separate
packages.

Do not implement a native PHP OpenID4VP/mdoc verifier in this repository unless
that work is independently conformance-tested. The supported product is a façade
over an existing verifier.

Report suspected vulnerabilities through
[GitHub private vulnerability reporting](https://github.com/binuka200/eudi-wallet-php/security/advisories/new)
instead of opening a public issue.
