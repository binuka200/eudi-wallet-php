# Local Commission verifier

The image below is the EU Commission's OpenID4VP verifier endpoint. The
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
);
```

Pin upgrades by changing the image tag. Do not run `:latest` in production.
