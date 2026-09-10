<?php

declare(strict_types=1);

namespace EudiWallet\Contract;

final class StartedPresentation
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $clientId,
        public readonly ?string $requestUri,
        public readonly ?string $requestUriMethod,
        public readonly ?string $request,
        public readonly string $authorizationRequestScheme,
        public readonly ?string $authorizationRequestUri = null,
    ) {
        if (trim($transactionId) === '') {
            throw new \InvalidArgumentException('Verifier did not return a transaction id.');
        }
        if (trim($clientId) === '') {
            throw new \InvalidArgumentException('Verifier did not return a client id.');
        }
        if (($requestUri === null || trim($requestUri) === '') && ($request === null || trim($request) === '') && ($authorizationRequestUri === null || trim($authorizationRequestUri) === '')) {
            throw new \InvalidArgumentException('Verifier did not return an authorization_request_uri, request_uri, or request.');
        }
        if ($requestUriMethod !== null && !in_array($requestUriMethod, ['get', 'post'], true)) {
            throw new \InvalidArgumentException('Verifier returned an invalid request_uri_method.');
        }
        if (preg_match('/^[a-z][a-z0-9+.-]*$/D', $authorizationRequestScheme) !== 1) {
            throw new \InvalidArgumentException('Verifier returned an invalid authorization request scheme.');
        }
        if ($authorizationRequestUri !== null && (preg_match('/^[a-z][a-z0-9+.-]*:/', $authorizationRequestUri) !== 1 || preg_match('/[\x00-\x20\x7f]/', $authorizationRequestUri) === 1)) {
            throw new \InvalidArgumentException('Verifier returned an invalid authorization request URI.');
        }
    }

    public function walletUri(): string
    {
        if ($this->authorizationRequestUri !== null) {
            return $this->authorizationRequestUri;
        }

        $params = ['client_id' => $this->clientId];
        if ($this->requestUri !== null) {
            $params['request_uri'] = $this->requestUri;
            if ($this->requestUriMethod !== null && $this->requestUriMethod !== '') {
                $params['request_uri_method'] = $this->requestUriMethod;
            }
        } elseif ($this->request !== null) {
            $params['request'] = $this->request;
        }

        return $this->authorizationRequestScheme.'://?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
