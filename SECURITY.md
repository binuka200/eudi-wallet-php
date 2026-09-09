# Security policy

Only the latest release receives security fixes.

Report vulnerabilities through
[GitHub private vulnerability reporting](https://github.com/binuka200/eudi-wallet-php/security/advisories/new).
Do not open a public issue until a fix and disclosure plan are ready.

Never include real wallet presentations, PID attributes, relying-party
certificates, or verifier keystore passwords in a report.

This package is a PHP client. Cryptographic verification belongs in the
configured verifier backend. Treat verifier responses as trusted only over
an authenticated private channel.
