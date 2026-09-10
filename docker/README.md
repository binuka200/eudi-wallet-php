# Local Commission verifier

The Compose file pins the current `v0.11.0` EU Commission OpenID4VP verifier
endpoint image. The
Commission describes it as a **development tool**, not a production application.
Use it to try wallets locally. Production relying parties should point
`VERIFIER_URL` at a verifier they have independently assessed.

```bash
export VERIFIER_PUBLICURL=https://your-public-host.example
docker compose -f docker/docker-compose.yaml up
```

The wallet on a phone must be able to reach `VERIFIER_PUBLICURL`. A machine
localhost URL is not enough for cross-device QR flows.

Then in PHP:

```php
$verifier = new CommissionVerifier(
    $transport,
    'http://127.0.0.1:8080',
    allowInsecureHttp: true, // local Docker only
    intendedUseId: '1',      // the intended use the dev image ships with
);
```

`GET /ui/intended-uses` lists the intended uses the running verifier knows.
The dev image's client id is the pre-registered value `Verifier`, so the
`haip` profile is refused with `HaipNotSupported.ClientIdPrefixX509HashMustBeUsed`
until the verifier is configured with an x509 client id; use the default
`openid4vp` profile locally.

Pin upgrades by changing the image tag. Do not run `:latest` in production.
