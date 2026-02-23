<?php

declare(strict_types=1);

namespace MyApp\Auth;

use Psr\Http\Message\RequestInterface;
use Tugrul\ApiGen\Contracts\{AuthStrategy, SigningAuth};

/**
 * Example: custom auth that generates a request signature similar to AWS Signature V4.
 * This shows how to implement SigningAuth for APIs that require HMAC over
 * specific request components (method + path + timestamp + body hash).
 */
final class AwsStyleAuth implements AuthStrategy, SigningAuth
{
    public function __construct(
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly string $service,
        private readonly string $region = 'us-east-1',
    ) {}

    public function sign(RequestInterface $request): string
    {
        $timestamp    = gmdate('Ymd\THis\Z');
        $date         = gmdate('Ymd');
        $method       = $request->getMethod();
        $path         = $request->getUri()->getPath();
        $bodyHash     = hash('sha256', (string) $request->getBody());

        $canonicalRequest = implode("\n", [
            $method,
            $path,
            '', // query string (simplified)
            "host:" . $request->getUri()->getHost(),
            "x-amz-date:{$timestamp}",
            '',
            "host;x-amz-date",
            $bodyHash,
        ]);

        $scope        = "{$date}/{$this->region}/{$this->service}/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $timestamp,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = $this->deriveSigningKey($date);

        return hash_hmac('sha256', $stringToSign, $signingKey);
    }

    public function authenticate(RequestInterface $request): RequestInterface
    {
        $timestamp = gmdate('Ymd\THis\Z');
        $date      = gmdate('Ymd');
        $scope     = "{$date}/{$this->region}/{$this->service}/aws4_request";
        $signature = $this->sign($request->withHeader('x-amz-date', $timestamp));

        $authHeader = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=host;x-amz-date, Signature=%s',
            $this->accessKey,
            $scope,
            $signature,
        );

        return $request
            ->withHeader('x-amz-date', $timestamp)
            ->withHeader('Authorization', $authHeader);
    }

    private function deriveSigningKey(string $date): string
    {
        $kDate    = hash_hmac('sha256', $date, "AWS4{$this->secretKey}", true);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $this->service, $kRegion, true);

        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }
}
