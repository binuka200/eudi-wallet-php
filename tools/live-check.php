<?php

declare(strict_types=1);

/**
 * Live check against a running Commission verifier endpoint.
 *
 * Exercises every HTTP path the package uses that does not need a wallet:
 * starting a transaction, reading the authoritative authorization request
 * URI, polling before submission, unknown transactions, bogus response codes,
 * and the request object a wallet would fetch. It cannot complete a
 * presentation; that needs a wallet.
 *
 *   VERIFIER_URL=http://127.0.0.1:8080 VERIFIER_INTENDED_USE=1 composer live-check
 *
 * Environment:
 *   VERIFIER_URL           Base URL of the verifier (default http://127.0.0.1:8080).
 *   VERIFIER_INTENDED_USE  Configured intended use id (default 1, the dev image default).
 *   VERIFIER_TOKEN         Optional bearer token sent as Authorization.
 *   VERIFIER_PROFILE       openid4vp (default) or haip.
 */

require __DIR__.'/../vendor/autoload.php';

use EudiWallet\Claim;
use EudiWallet\Contract\HttpResponse;
use EudiWallet\Contract\Transport;
use EudiWallet\EudiWallet;
use EudiWallet\Exception\VerifierRejected;
use EudiWallet\RequestOptions;
use EudiWallet\Verifier\CommissionVerifier;
use EudiWallet\WalletSession;

/** Stream-wrapper transport so the tool needs no HTTP client or extension. */
final class StreamTransport implements Transport
{
    /** @var list<string> */
    public array $log = [];

    public function send(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'content' => $body ?? '',
            'ignore_errors' => true,
            'timeout' => 30,
        ]]);
        $stream = @fopen($url, 'r', false, $context);
        if ($stream === false) {
            throw new RuntimeException('Connection to '.$url.' failed: '.(error_get_last()['message'] ?? 'unknown error'));
        }
        $responseBody = (string) stream_get_contents($stream);
        /** @var list<string> $rawHeaders wrapper_data works on every supported PHP; $http_response_header is deprecated in 8.5 */
        $rawHeaders = stream_get_meta_data($stream)['wrapper_data'] ?? [];
        fclose($stream);
        $status = 0;
        $responseHeaders = [];
        foreach ($rawHeaders as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match) === 1) {
                $status = (int) $match[1];
            } elseif (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $responseHeaders[trim($name)] = trim($value);
            }
        }
        $this->log[] = sprintf('%s %s -> %d (%d bytes)', $method, $url, $status, strlen($responseBody));

        return new HttpResponse($status, $responseBody, $responseHeaders);
    }
}

$baseUrl = getenv('VERIFIER_URL') ?: 'http://127.0.0.1:8080';
$intendedUse = getenv('VERIFIER_INTENDED_USE') ?: '1';
$profile = getenv('VERIFIER_PROFILE') ?: RequestOptions::PROFILE_OPENID4VP;
$token = getenv('VERIFIER_TOKEN') ?: null;
$headers = $token !== null ? ['Authorization' => 'Bearer '.$token] : [];
$insecure = str_starts_with($baseUrl, 'http://');

$transport = new StreamTransport();
$verifier = new CommissionVerifier($transport, $baseUrl, allowInsecureHttp: $insecure, headers: $headers, intendedUseId: $intendedUse);
$wallet = new EudiWallet($verifier);

$passed = 0;
$failed = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$passed, &$failed): void {
    $ok ? $passed++ : $failed++;
    echo ($ok ? 'PASS' : 'FAIL').'  '.$label.($detail !== '' ? '  ['.$detail.']' : '')."\n";
};
$failure = static fn (Throwable $e): string => (new ReflectionClass($e))->getShortName().': '.$e->getMessage();

echo "Verifier: $baseUrl (intended use $intendedUse, profile $profile)\n\n";

// 1. Cross-device start.
try {
    $challenge = $wallet->request([Claim::FAMILY_NAME, Claim::GIVEN_NAME, Claim::BIRTH_DATE], new RequestOptions(purpose: 'Live check', profile: $profile));
} catch (Throwable $e) {
    $check('start transaction', false, $failure($e));
    echo "\nCannot continue without a transaction.\n";
    exit(1);
}
$check('start returns a transaction id', $challenge->transactionId() !== '');
$check('walletUri is the verifier authorization_request_uri', preg_match('/^[a-z][a-z0-9+.-]*:/', $challenge->walletUri) === 1, $challenge->walletUri);
$check('client_id present', $challenge->clientId !== '', $challenge->clientId);
$check('WalletSession round-trips through toArray/fromArray', WalletSession::fromArray($challenge->session->toArray())->transactionId === $challenge->transactionId());

// 2. Polling before any wallet acts must be null, never an exception.
try {
    $check('poll() before the wallet acts returns null', $wallet->poll($challenge->session) === null);
} catch (Throwable $e) {
    $check('poll() before the wallet acts returns null', false, $failure($e));
}

// 3. Unknown transaction is terminal.
try {
    $verifier->fetch('live-check-unknown-transaction');
    $check('unknown transaction is rejected', false, 'no exception');
} catch (VerifierRejected $e) {
    $check('unknown transaction is rejected with 404', $e->status() === 404, $e->getMessage());
}

// 4. Bogus same-device response code fails closed.
try {
    $wallet->poll($challenge->session, 'live-check-bogus-code');
    $check('bogus response_code fails closed', false, 'returned without exception');
} catch (VerifierRejected $e) {
    $check('bogus response_code fails closed with 400', $e->status() === 400, $e->getMessage());
} catch (Throwable $e) {
    $check('bogus response_code fails closed', false, $failure($e));
}

// 5. Same-device start with redirect template and a single format.
try {
    $wallet->request([Claim::FAMILY_NAME], new RequestOptions(purpose: 'Same device', format: RequestOptions::FORMAT_MDOC, profile: $profile, redirectUriTemplate: 'https://app.example/callback?response_code={RESPONSE_CODE}'));
    $check('same-device mdoc-only start accepted', true);
} catch (Throwable $e) {
    $check('same-device mdoc-only start accepted', false, $failure($e));
}

// 6. Without any registration default the verifier must say why.
try {
    (new EudiWallet(new CommissionVerifier($transport, $baseUrl, allowInsecureHttp: $insecure, headers: $headers)))->request([Claim::FAMILY_NAME]);
    $check('start without registration is rejected', false, 'accepted; this verifier does not require a registration certificate');
} catch (VerifierRejected $e) {
    $check('start without registration is rejected with the verifier error code', str_contains($e->getMessage(), ': '), $e->getMessage());
}

// 7. Fetch the request object as a wallet would, to confirm the DCQL query and nonce survived unchanged.
if ($challenge->requestUri !== null) {
    $jwt = $transport->send('POST', $challenge->requestUri, ['Content-Type' => 'application/x-www-form-urlencoded'], '');
    $parts = explode('.', $jwt->body);
    $payload = count($parts) === 3 ? json_decode(base64_decode(strtr($parts[1], '-_', '+/')) ?: '', true) : null;
    $check('wallet can retrieve the request object', $jwt->status === 200 && is_array($payload), 'status '.$jwt->status);
    if (is_array($payload)) {
        $ids = array_column($payload['dcql_query']['credentials'] ?? [], 'id');
        $check('request object carries the DCQL query', $ids === ['pid_mdoc', 'pid_sd_jwt'], implode(',', $ids));
        $check('request object nonce equals the session nonce', ($payload['nonce'] ?? null) === $challenge->session->nonce);
        $check('response_mode is direct_post.jwt', ($payload['response_mode'] ?? null) === 'direct_post.jwt', (string) ($payload['response_mode'] ?? 'missing'));
    }
    try {
        $check('poll() after request retrieval still returns null', $wallet->poll($challenge->session) === null);
    } catch (Throwable $e) {
        $check('poll() after request retrieval still returns null', false, $failure($e));
    }
}

echo "\n".implode("\n", $transport->log)."\n\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
