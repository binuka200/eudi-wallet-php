<?php

declare(strict_types=1);

namespace EudiWallet\Verifier;

use EudiWallet\Contract\HttpResponse;
use EudiWallet\Contract\StartOptions;
use EudiWallet\Contract\StartedPresentation;
use EudiWallet\Contract\Transport;
use EudiWallet\Contract\Verifier;
use EudiWallet\Contract\WalletResponse;
use EudiWallet\Exception\InvalidConfiguration;
use EudiWallet\Exception\InvalidWalletResponse;
use EudiWallet\Exception\VerifierRejected;
use EudiWallet\Exception\VerifierUnavailable;

/**
 * Client for the EU Commission's verifier endpoint.
 *
 * @see https://github.com/eu-digital-identity-wallet/eudi-srv-verifier-endpoint
 */
final class CommissionVerifier implements Verifier
{
    private const RESERVED_HEADERS = ['accept', 'content-type', 'content-length', 'host'];

    private readonly string $baseUrl;

    /** @var array<string, string> */
    private readonly array $headers;

    /**
     * @param array<string, string> $headers Sent with every verifier request, for example an
     *                                       Authorization header when the verifier API is protected.
     */
    public function __construct(
        private readonly Transport $transport,
        string $baseUrl,
        private readonly bool $allowInsecureHttp = false,
        array $headers = [],
    ) {
        $baseUrl = rtrim($baseUrl, '/');
        if ($baseUrl === '') {
            throw new InvalidConfiguration('Verifier base URL cannot be empty.');
        }
        $parts = parse_url($baseUrl);
        if ($parts === false) {
            throw new InvalidConfiguration('Verifier URL is invalid.');
        }
        $scheme = $parts['scheme'] ?? null;
        if ($scheme === 'http' && !$this->allowInsecureHttp) {
            throw new InvalidConfiguration('Verifier URL must use HTTPS. Set allowInsecureHttp only for local Docker.');
        }
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new InvalidConfiguration('Verifier URL must be an absolute http(s) URL.');
        }
        if (!isset($parts['host']) || $parts['host'] === '' || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidConfiguration('Verifier URL must contain a host and no credentials, query, or fragment.');
        }
        $this->baseUrl = $baseUrl;

        foreach ($headers as $name => $value) {
            if (preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/D', $name) !== 1) {
                throw new InvalidConfiguration('Verifier header names must be valid HTTP tokens.');
            }
            if (in_array(strtolower($name), self::RESERVED_HEADERS, true)) {
                throw new InvalidConfiguration('Verifier header '.$name.' is managed by the client and cannot be overridden.');
            }
            if (preg_match('/[\x00-\x08\x0a-\x1f\x7f]/', $value) === 1) {
                throw new InvalidConfiguration('Verifier header values must not contain control characters.');
            }
        }
        $this->headers = $headers;
    }

    public function start(array $dcqlQuery, StartOptions $options): StartedPresentation
    {
        $payload = [
            'dcql_query' => $dcqlQuery,
            'nonce' => $options->nonce,
            'response_mode' => $options->responseMode,
            'jar_mode' => $options->jarMode,
            'profile' => $options->profile,
        ];
        if ($options->jarMode === 'by_reference') {
            $payload['request_uri_method'] = $options->requestUriMethod;
        }
        if ($options->redirectUriTemplate !== null) {
            $payload['wallet_response_redirect_uri_template'] = $options->redirectUriTemplate;
        }
        if ($options->issuerChain !== null) {
            $payload['issuer_chain'] = $options->issuerChain;
        }
        if ($options->intendedUseId !== null) {
            $payload['intended_use_id'] = $options->intendedUseId;
        }
        if ($options->registrationCertificate !== null) {
            $payload['registration_certificate'] = $options->registrationCertificate;
        }
        $payload['authorization_request_scheme'] = $options->authorizationRequestScheme;

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $response = $this->call('POST', $this->baseUrl.'/ui/presentations/v2', [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $body);

        if ($response->status < 200 || $response->status >= 300) {
            throw new VerifierRejected('Verifier rejected the presentation request.', $response->status, $response->body);
        }

        $data = $this->decode($response->body);
        $transactionId = $data['transaction_id'] ?? null;
        $clientId = $data['client_id'] ?? null;
        $requestUri = $data['request_uri'] ?? null;
        $request = $data['request'] ?? null;
        $authorizationRequestUri = $data['authorization_request_uri'] ?? null;
        $requestUriMethod = $data['request_uri_method'] ?? null;

        if (!is_string($transactionId) || $transactionId === '') {
            throw new InvalidWalletResponse('Verifier start response is missing transaction_id.');
        }
        if (!is_string($clientId) || $clientId === '') {
            throw new InvalidWalletResponse('Verifier start response is missing client_id.');
        }
        if (!is_string($authorizationRequestUri) || $authorizationRequestUri === '') {
            throw new InvalidWalletResponse('Verifier start response is missing authorization_request_uri.');
        }

        try {
            return new StartedPresentation(
                transactionId: $transactionId,
                clientId: $clientId,
                requestUri: is_string($requestUri) && $requestUri !== '' ? $requestUri : null,
                requestUriMethod: is_string($requestUriMethod) && $requestUriMethod !== '' ? $requestUriMethod : null,
                request: is_string($request) && $request !== '' ? $request : null,
                authorizationRequestScheme: $options->authorizationRequestScheme,
                authorizationRequestUri: $authorizationRequestUri,
            );
        } catch (\InvalidArgumentException $exception) {
            throw new InvalidWalletResponse('Verifier returned an invalid presentation transaction.', 0, $exception);
        }
    }

    /**
     * Reads the wallet response.
     *
     * The Commission verifier answers 404 only for an unknown transaction and
     * 400 (empty body) both while the wallet has not submitted yet and when a
     * supplied response_code does not match. Without a response code a 400 is
     * therefore reported as pending; with one it is a terminal rejection.
     */
    public function fetch(string $transactionId, ?string $responseCode = null): WalletResponse
    {
        if ($transactionId === '') {
            throw new \InvalidArgumentException('Transaction id cannot be empty.');
        }
        $url = $this->baseUrl.'/ui/presentations/'.rawurlencode($transactionId);
        if ($responseCode !== null && $responseCode !== '') {
            $url .= '?response_code='.rawurlencode($responseCode);
        }

        $response = $this->call('GET', $url, ['Accept' => 'application/json']);
        if ($response->status === 404) {
            throw new VerifierRejected('Verifier does not know the presentation transaction.', 404, $response->body);
        }
        if ($response->status === 400) {
            if ($responseCode === null || $responseCode === '') {
                return WalletResponse::pending();
            }
            throw new VerifierRejected('Verifier refused the response code, or the wallet has not submitted yet.', 400, $response->body);
        }
        if ($response->status < 200 || $response->status >= 300) {
            throw new VerifierRejected('Verifier refused to return the wallet response.', $response->status, $response->body);
        }

        return WalletResponse::fromVerifierPayload($this->decode($response->body));
    }

    /**
     * @param array<string, string> $headers
     */
    private function call(string $method, string $url, array $headers, ?string $body = null): HttpResponse
    {
        try {
            return $this->transport->send($method, $url, array_merge($this->headers, $headers), $body);
        } catch (VerifierUnavailable $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new VerifierUnavailable('Verifier transport failed.', 0, $exception);
        }
    }

    /** @return array<string, mixed> */
    private function decode(string $body): array
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidWalletResponse('Verifier returned invalid JSON.', 0, $exception);
        }
        if (!is_array($data) || array_is_list($data)) {
            throw new InvalidWalletResponse('Verifier returned a non-object JSON payload.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
