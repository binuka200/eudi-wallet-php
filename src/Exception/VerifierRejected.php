<?php

declare(strict_types=1);

namespace EudiWallet\Exception;

/**
 * The verifier answered with a non-success HTTP status.
 *
 * getCode() is the HTTP status. responseBody holds up to 2 KiB of the raw body
 * for operator diagnostics; never forward it to an unauthenticated client.
 */
final class VerifierRejected extends EudiWalletException
{
    public const MAX_BODY_BYTES = 2048;

    public readonly string $responseBody;

    public function __construct(string $message, int $status = 0, string $responseBody = '', ?\Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);
        $this->responseBody = strlen($responseBody) > self::MAX_BODY_BYTES
            ? substr($responseBody, 0, self::MAX_BODY_BYTES)
            : $responseBody;
    }

    public function status(): int
    {
        return $this->getCode();
    }
}
