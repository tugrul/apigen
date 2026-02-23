<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Auth\Strategy;

use Psr\Http\Message\RequestInterface;
use Tugrul\ApiGen\Contracts\{AuthStrategy, SigningAuth};

// ---------------------------------------------------------------------------
// HMAC Signature Auth (e.g. many payment gateways, webhook verification)
// ---------------------------------------------------------------------------

final class HmacSignatureAuth implements AuthStrategy, SigningAuth
{
    public function __construct(
        private readonly string    $secret,
        private readonly string    $algorithm = 'sha256',
        private readonly string    $headerName = 'X-Signature',
        private readonly string    $prefix = 'hmac ',
        /** Callable(RequestInterface): string — what to sign. Defaults to body. */
        private readonly ?\Closure $payloadExtractor = null,
    )
    {
    }

    public function sign(RequestInterface $request): string
    {
        $payload = $this->payloadExtractor
            ? ($this->payloadExtractor)($request)
            : (string)$request->getBody();

        return hash_hmac($this->algorithm, $payload, $this->secret);
    }

    public function authenticate(RequestInterface $request): RequestInterface
    {
        $signature = $this->sign($request);

        return $request->withHeader($this->headerName, $this->prefix . $signature);
    }
}