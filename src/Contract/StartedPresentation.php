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
        if ($transactionId === '') {
            throw new \InvalidArgumentException('Verifier did not return a transaction id.');
        }
        if ($requestUri === null && $request === null) {
            throw new \InvalidArgumentException('Verifier did not return a request_uri or request.');
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
