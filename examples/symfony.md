# Symfony

Use the package from a controller. A Symfony Security authenticator can wrap
`EudiWallet` in your application; it does not belong in this repository yet.

```php
use EudiWallet\Claim;
use EudiWallet\EudiWallet;
use EudiWallet\RequestOptions;
use EudiWallet\WalletSession;
use Symfony\Component\HttpFoundation\Request;

public function start(Request $request, EudiWallet $wallet)
{
    $challenge = $wallet->request(
        [Claim::FAMILY_NAME, Claim::GIVEN_NAME],
        new RequestOptions(
            purpose: 'Identify the account holder',
            redirectUriTemplate: $this->generateUrl('eudi_callback', [], 0).'?response_code={RESPONSE_CODE}',
        ),
    );

    $request->getSession()->set('eudi', $challenge->session->toArray());

    return $this->redirect($challenge->walletUri);
}

public function callback(Request $request, EudiWallet $wallet)
{
    $stored = $request->getSession()->get('eudi');
    $identity = $wallet->verify(
        WalletSession::fromArray($stored),
        $request->query->get('response_code'),
    );
    $request->getSession()->remove('eudi');

    // Bind the verified attributes to your user.
}
```

Register `CommissionVerifier` and `Psr18Transport` as services. Symfony
HttpClient can be adapted to PSR-18 with `psr18` from `symfony/http-client`.
