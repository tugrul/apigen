<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Tests\Http;

use Tugrul\ApiGen\Auth\Strategy\BearerTokenAuth;
use Tugrul\ApiGen\Http\CallDescriptor;
use Tugrul\ApiGen\Tests\ApiGenTestCase;

final class CallDescriptorTest extends ApiGenTestCase
{
    public function test_defaults(): void
    {
        $call = CallDescriptor::create();

        self::assertSame('GET',  $call->getMethod());
        self::assertSame('/',    $call->getPath());
        self::assertSame([],     $call->getPathParams());
        self::assertSame([],     $call->getQueryParams());
        self::assertSame([],     $call->getHeaders());
        self::assertNull($call->getBody());
        self::assertSame('json', $call->getBodyEncoding());
        self::assertNull($call->getAuth());
        self::assertFalse($call->isAuthDisabled());
        self::assertNull($call->getReturnType());
        self::assertNull($call->getReturnGenericOf());
        self::assertNull($call->getBaseUrlOverride());
        self::assertNull($call->getAuthClass());
    }

    public function test_method_is_uppercased(): void
    {
        self::assertSame('POST', CallDescriptor::create()->method('post')->getMethod());
        self::assertSame('DELETE', CallDescriptor::create()->method('delete')->getMethod());
    }

    public function test_path_param_stored_as_string(): void
    {
        $call = CallDescriptor::create()->pathParam('id', 42);

        self::assertSame(['id' => '42'], $call->getPathParams());
    }

    public function test_multiple_path_params(): void
    {
        $call = CallDescriptor::create()
            ->pathParam('owner', 'alice')
            ->pathParam('repo', 'my-repo');

        self::assertSame(['owner' => 'alice', 'repo' => 'my-repo'], $call->getPathParams());
    }

    public function test_query_param_null_is_ignored(): void
    {
        $call = CallDescriptor::create()
            ->queryParam('page', null)
            ->queryParam('limit', 10);

        self::assertArrayNotHasKey('page', $call->getQueryParams());
        self::assertSame(10, $call->getQueryParams()['limit']);
    }

    public function test_query_map_merges_array(): void
    {
        $call = CallDescriptor::create()
            ->queryParam('existing', 'yes')
            ->queryMap(['page' => 2, 'sort' => 'asc']);

        self::assertSame('yes', $call->getQueryParams()['existing']);
        self::assertSame(2,     $call->getQueryParams()['page']);
        self::assertSame('asc', $call->getQueryParams()['sort']);
    }

    public function test_query_map_ignores_null_values(): void
    {
        $call = CallDescriptor::create()->queryMap(['a' => 'v', 'b' => null]);

        self::assertArrayHasKey('a', $call->getQueryParams());
        self::assertArrayNotHasKey('b', $call->getQueryParams());
    }

    public function test_header_stored_correctly(): void
    {
        $call = CallDescriptor::create()->header('Accept', 'application/json');

        self::assertSame(['Accept' => 'application/json'], $call->getHeaders());
    }

    public function test_headers_bulk_merge(): void
    {
        $call = CallDescriptor::create()->headers(['A' => '1', 'B' => '2']);

        self::assertSame(['A' => '1', 'B' => '2'], $call->getHeaders());
    }

    public function test_body_and_encoding_stored(): void
    {
        $call = CallDescriptor::create()->body(['name' => 'rex'], 'json');

        self::assertSame(['name' => 'rex'], $call->getBody());
        self::assertSame('json', $call->getBodyEncoding());
    }

    public function test_body_encoding_defaults_to_json(): void
    {
        $call = CallDescriptor::create()->body('raw-data');

        self::assertSame('json', $call->getBodyEncoding());
    }

    public function test_auth_stored(): void
    {
        $auth = new BearerTokenAuth('tok');
        $call = CallDescriptor::create()->auth($auth);

        self::assertSame($auth, $call->getAuth());
    }

    public function test_disable_auth(): void
    {
        $call = CallDescriptor::create()->disableAuth();

        self::assertTrue($call->isAuthDisabled());
    }

    public function test_return_type_with_generic(): void
    {
        $call = CallDescriptor::create()->returnType('array', 'UserDto');

        self::assertSame('array',   $call->getReturnType());
        self::assertSame('UserDto', $call->getReturnGenericOf());
    }

    public function test_return_type_without_generic(): void
    {
        $call = CallDescriptor::create()->returnType('array');

        self::assertSame('array', $call->getReturnType());
        self::assertNull($call->getReturnGenericOf());
    }

    public function test_base_url_override(): void
    {
        $call = CallDescriptor::create()->baseUrl('https://other.example.com');

        self::assertSame('https://other.example.com', $call->getBaseUrlOverride());
    }

    public function test_use_auth_class(): void
    {
        $call = CallDescriptor::create()->useAuthClass('My\\Auth\\Strategy');

        self::assertSame('My\\Auth\\Strategy', $call->getAuthClass());
    }

    public function test_fluent_chaining_returns_same_instance(): void
    {
        $call = CallDescriptor::create();

        self::assertSame($call, $call->method('GET'));
        self::assertSame($call, $call->path('/test'));
        self::assertSame($call, $call->pathParam('id', 1));
        self::assertSame($call, $call->queryParam('q', 'x'));
        self::assertSame($call, $call->header('H', 'V'));
        self::assertSame($call, $call->body(null));
        self::assertSame($call, $call->disableAuth());
        self::assertSame($call, $call->returnType('array'));
        self::assertSame($call, $call->baseUrl('https://x.com'));
        self::assertSame($call, $call->useAuthClass('Foo'));
    }
}
