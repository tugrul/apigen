<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Tests\Auth;

use Tugrul\ApiGen\Auth\Strategy\{ApiKeyAuth, BasicAuth, BearerTokenAuth, HmacSignatureAuth, NoAuth, StaticTokenAuth};
use Tugrul\ApiGen\Tests\ApiGenTestCase;

final class AuthStrategiesTest extends ApiGenTestCase
{
    // ── NoAuth ───────────────────────────────────────────────────────────────

    public function test_no_auth_returns_request_unchanged(): void
    {
        $request = $this->makeRequest();
        $result  = (new NoAuth())->authenticate($request);

        self::assertSame($request, $result);
    }

    // ── BearerTokenAuth ───────────────────────────────────────────────────────

    public function test_bearer_token_sets_authorization_header(): void
    {
        $request = $this->makeRequest();
        $result  = (new BearerTokenAuth('my-token'))->authenticate($request);

        $this->assertRequestHeader($result, 'Authorization', 'Bearer my-token');
    }

    public function test_bearer_token_with_token_returns_new_instance(): void
    {
        $auth  = new BearerTokenAuth('original');
        $auth2 = $auth->withToken('updated');

        self::assertNotSame($auth, $auth2);

        $result = $auth2->authenticate($this->makeRequest());
        $this->assertRequestHeader($result, 'Authorization', 'Bearer updated');
    }

    // ── StaticTokenAuth ───────────────────────────────────────────────────────

    public function test_static_token_with_no_prefix(): void
    {
        $result = (new StaticTokenAuth('raw-token', 'X-Token'))->authenticate($this->makeRequest());

        $this->assertRequestHeader($result, 'X-Token', 'raw-token');
    }

    public function test_static_token_with_prefix(): void
    {
        $result = (new StaticTokenAuth('secret', 'Authorization', 'Token'))->authenticate($this->makeRequest());

        $this->assertRequestHeader($result, 'Authorization', 'Token secret');
    }

    // ── ApiKeyAuth ────────────────────────────────────────────────────────────

    public function test_api_key_in_header(): void
    {
        $result = (new ApiKeyAuth('key123', 'X-Api-Key', ApiKeyAuth::LOCATION_HEADER))
            ->authenticate($this->makeRequest());

        $this->assertRequestHeader($result, 'X-Api-Key', 'key123');
    }

    public function test_api_key_in_query(): void
    {
        $result = (new ApiKeyAuth('key123', 'api_key', ApiKeyAuth::LOCATION_QUERY))
            ->authenticate($this->makeRequest('GET', 'https://api.example.com/test'));

        $this->assertQueryParam($result, 'api_key', 'key123');
    }

    public function test_api_key_in_query_appends_to_existing_query(): void
    {
        $request = $this->makeRequest('GET', 'https://api.example.com/test?page=1');
        $result  = (new ApiKeyAuth('k', 'api_key', ApiKeyAuth::LOCATION_QUERY))->authenticate($request);

        parse_str($result->getUri()->getQuery(), $params);
        self::assertSame('1', $params['page']);
        self::assertSame('k', $params['api_key']);
    }

    // ── BasicAuth ─────────────────────────────────────────────────────────────

    public function test_basic_auth_sets_base64_credentials(): void
    {
        $result = (new BasicAuth('user', 'pass'))->authenticate($this->makeRequest());

        $this->assertRequestHeader($result, 'Authorization', 'Basic ' . base64_encode('user:pass'));
    }

    public function test_basic_auth_with_special_characters(): void
    {
        $result = (new BasicAuth('user@domain.com', 'p@$$w0rd!'))->authenticate($this->makeRequest());
        $header = $result->getHeaderLine('Authorization');

        self::assertStringStartsWith('Basic ', $header);
        self::assertSame('user@domain.com:p@$$w0rd!', base64_decode(substr($header, 6)));
    }

    // ── HmacSignatureAuth ─────────────────────────────────────────────────────

    public function test_hmac_signs_body_and_sets_header(): void
    {
        $body    = '{"name":"rex"}';
        $secret  = 'my-secret';
        $request = $this->makeRequest('POST', 'https://api.example.com', $body);

        $auth   = new HmacSignatureAuth($secret, 'sha256', 'X-Signature', 'hmac ');
        $result = $auth->authenticate($request);

        $expectedSig = hash_hmac('sha256', $body, $secret);
        $this->assertRequestHeader($result, 'X-Signature', 'hmac ' . $expectedSig);
    }

    public function test_hmac_sign_matches_authenticate_signature(): void
    {
        $body    = 'payload';
        $request = $this->makeRequest('POST', 'https://api.example.com', $body);
        $auth    = new HmacSignatureAuth('secret', 'sha256', 'X-Sig', '');

        $sig    = $auth->sign($request);
        $result = $auth->authenticate($request);

        $this->assertRequestHeader($result, 'X-Sig', $sig);
    }

    public function test_hmac_with_custom_payload_extractor(): void
    {
        $request = $this->makeRequest('POST', 'https://api.example.com/resource');

        $auth = new HmacSignatureAuth(
            secret: 'secret',
            headerName: 'X-Sig',
            prefix: '',
            payloadExtractor: fn($r) => $r->getUri()->getPath(),
        );

        $result      = $auth->authenticate($request);
        $expectedSig = hash_hmac('sha256', '/resource', 'secret');

        $this->assertRequestHeader($result, 'X-Sig', $expectedSig);
    }

    public function test_hmac_supports_different_algorithms(): void
    {
        $body    = 'data';
        $request = $this->makeRequest('POST', 'https://api.example.com', $body);

        $auth = new HmacSignatureAuth('s', 'sha512', 'X-Sig', '');
        $auth->authenticate($request);

        $expected = hash_hmac('sha512', $body, 's');
        self::assertSame($expected, $auth->sign($request));
    }
}
