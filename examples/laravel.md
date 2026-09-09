# Laravel

Keep the façade in a controller or action. Do not add a Laravel service
provider to the core package.

```php
use EudiWallet\Claim;
use EudiWallet\EudiWallet;
use EudiWallet\RequestOptions;
use EudiWallet\WalletSession;
use Illuminate\Http\Request;

public function start(Request $request, EudiWallet $wallet)
{
    $challenge = $wallet->request(
        [Claim::AGE_OVER_18, Claim::FAMILY_NAME],
        new RequestOptions(
            purpose: 'Account opening',
            redirectUriTemplate: url('/wallet/callback').'?response_code={RESPONSE_CODE}',
        ),
    );

    $request->session()->put('eudi', $challenge->session->toArray());

    return redirect()->away($challenge->walletUri);
}

public function callback(Request $request, EudiWallet $wallet)
{
    $stored = $request->session()->pull('eudi');
    $identity = $wallet->verify(
        WalletSession::fromArray($stored),
        $request->query('response_code'),
    );

    // Bind $identity to your own user model. This package does not log users in.
}
```

Bind `EudiWallet` in your application container to a `CommissionVerifier`
that uses Laravel's HTTP stack wrapped as PSR-18, or a dedicated PSR-18 client.

For local feature tests, bind `FakeVerifier` and call `complete()` from the test.
