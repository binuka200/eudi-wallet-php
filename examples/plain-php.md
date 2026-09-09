# Plain PHP

Store the wallet session server-side. Do not put the nonce in the browser.

```php
use EudiWallet\Claim;
use EudiWallet\EudiWallet;
use EudiWallet\RequestOptions;
use EudiWallet\Verifier\CommissionVerifier;
use EudiWallet\Verifier\Psr18Transport;
use EudiWallet\WalletSession;

$wallet = new EudiWallet(new CommissionVerifier(
    new Psr18Transport($client, $requestFactory, $streamFactory),
    getenv('VERIFIER_URL') ?: 'https://verifier.example',
));

$challenge = $wallet->request(
    [Claim::FAMILY_NAME, Claim::GIVEN_NAME],
    new RequestOptions(
        purpose: 'Identify the account holder',
        redirectUriTemplate: 'https://example.com/wallet/callback?response_code={RESPONSE_CODE}',
    ),
);

$_SESSION['eudi'] = $challenge->session->toArray();
header('Location: '.$challenge->walletUri);
```

Callback:

```php
$stored = $_SESSION['eudi'] ?? null;
if (!is_array($stored)) {
    throw new RuntimeException('EUDI session expired.');
}

$identity = $wallet->verify(
    WalletSession::fromArray($stored),
    isset($_GET['response_code']) && is_string($_GET['response_code']) ? $_GET['response_code'] : null,
);
unset($_SESSION['eudi']);

// Bind the verified attributes to your user, then retain only what you need.
```
