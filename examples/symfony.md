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
        [Claim::AGE_OVER_18],
        new RequestOptions(
            purpose: 'Age verification',
            redirectUriTemplate: $this->generateUrl('eudi_callback', [], 0).'?response_code={RESPONSE_CODE}',
        ),
    );

    $request->getSession()->set('eudi', $challenge->session->toArray());

    return $this->redirect($challenge->walletUri);
}

public function callback(Request $request, EudiWallet $wallet)
{
    $stored = $request->getSession()->remove('eudi');
    $identity = $wallet->verify(
        WalletSession::fromArray($stored),
        $request->query->get('response_code'),
    );

    if (!$identity->ageOver18()) {
        throw $this->createAccessDeniedException();
    }
}
```

Register `CommissionVerifier` and `Psr18Transport` as services. Symfony
HttpClient can be adapted to PSR-18 with `psr18` from `symfony/http-client`.
