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
    ) {
        if (trim($transactionId) === '') {
            throw new \InvalidArgumentException('Verifier did not return a transaction id.');
        }
        if (trim($clientId) === '') {
            throw new \InvalidArgumentException('Verifier did not return a client id.');
        }
        if (($requestUri === null || trim($requestUri) === '') && ($request === null || trim($request) === '')) {
            throw new \InvalidArgumentException('Verifier did not return a request_uri or request.');
        }
        if (preg_match('/^[a-z][a-z0-9+.-]*$/D', $authorizationRequestScheme) !== 1) {
            throw new \InvalidArgumentException('Verifier returned an invalid authorization request scheme.');
        }
    }

    public function walletUri(): string
    {
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
