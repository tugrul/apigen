<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\{RequestFactoryInterface, StreamFactoryInterface, UriFactoryInterface};
use Tugrul\ApiGen\Auth\{AuthResolver, TokenCache};
use Tugrul\ApiGen\Auth\Strategy\{ApiKeyAuth, BasicAuth, BearerTokenAuth, HmacSignatureAuth,
    OAuth2ClientCredentialsAuth, StaticTokenAuth};
use Tugrul\ApiGen\Contracts\{AuthStrategy, ResponseDecoder};

/**
 * Fluent builder for DefaultSdkClient.
 *
 * Usage:
 *   $client = ClientBuilder::create('https://api.example.com')
 *       ->withBearerToken('my-token')
 *       ->withPsr18(new GuzzleAdapter(), new Psr17Factory(), new Psr17Factory(), new Psr17Factory())
 *       ->build();
 */
final class ClientBuilder
{
    private ?ClientInterface          $httpClient     = null;
    private ?RequestFactoryInterface  $requestFactory = null;
    private ?StreamFactoryInterface   $streamFactory  = null;
    private ?UriFactoryInterface      $uriFactory     = null;
    private ?AuthStrategy             $auth           = null;
    private ?ResponseDecoder          $decoder        = null;
    private AuthResolver              $authResolver;
    /** @var array<string, string> */
    private array                     $defaultHeaders = [];

    private function __construct(private readonly string $baseUrl)
    {
        $this->authResolver = new AuthResolver();
    }

    public static function create(string $baseUrl): self
    {
        return new self(rtrim($baseUrl, '/'));
    }

    // --- PSR-18 / PSR-17 ---

    public function withHttpClient(ClientInterface $client): self
    {
        $this->httpClient = $client;

        return $this;
    }

    public function withRequestFactory(RequestFactoryInterface $factory): self
    {
        $this->requestFactory = $factory;

        return $this;
    }

    public function withStreamFactory(StreamFactoryInterface $factory): self
    {
        $this->streamFactory = $factory;

        return $this;
    }

    public function withUriFactory(UriFactoryInterface $factory): self
    {
        $this->uriFactory = $factory;

        return $this;
    }

    /**
     * Convenience: provide all PSR-17 factories at once if your package
     * implements multiple interfaces in one class (e.g. nyholm/psr7, guzzle/psr7).
     */
    public function withPsr18(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        UriFactoryInterface $uriFactory,
    ): self {
        $this->httpClient     = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory  = $streamFactory;
        $this->uriFactory     = $uriFactory;

        return $this;
    }

    // --- Standard auth shortcuts ---

    public function withBearerToken(string $token): self
    {
        $this->auth = new BearerTokenAuth($token);

        return $this;
    }

    public function withApiKey(
        string $key,
        string $headerName = 'X-Api-Key',
        string $location = ApiKeyAuth::LOCATION_HEADER,
    ): self {
        $this->auth = new ApiKeyAuth($key, $headerName, $location);

        return $this;
    }

    public function withBasicAuth(string $username, string $password): self
    {
        $this->auth = new BasicAuth($username, $password);

        return $this;
    }

    public function withStaticToken(
        string $token,
        string $headerName = 'Authorization',
        string $prefix = '',
    ): self {
        $this->auth = new StaticTokenAuth($token, $headerName, $prefix);

        return $this;
    }

    public function withHmacSignature(
        string $secret,
        string $algorithm = 'sha256',
        string $headerName = 'X-Signature',
    ): self {
        $this->auth = new HmacSignatureAuth($secret, $algorithm, $headerName);

        return $this;
    }

    /**
     * OAuth 2.0 Client Credentials grant with pluggable token cache.
     *
     * @param TokenCache|null $cache
     *   null                  → InMemoryTokenCache (default, process-scoped)
     *   new SessionTokenCache → PHP $_SESSION
     *   new FileTokenCache(__DIR__.'/tokens')  → filesystem JSON
     *   new Psr16TokenCache($redis)            → Redis / Memcached / APCu / …
     */
    public function withOAuth2ClientCredentials(
        string $tokenUrl,
        string $clientId,
        string $clientSecret,
        string $scope    = '',
        ?TokenCache $cache = null,
        ?string $cacheKey  = null,
    ): self {
        if ($this->httpClient === null || $this->requestFactory === null || $this->streamFactory === null) {
            throw new \LogicException(
                'HTTP client and PSR-17 factories must be set before configuring OAuth2 auth.'
            );
        }

        $this->auth = new OAuth2ClientCredentialsAuth(
            httpClient:     $this->httpClient,
            requestFactory: $this->requestFactory,
            streamFactory:  $this->streamFactory,
            tokenUrl:       $tokenUrl,
            clientId:       $clientId,
            clientSecret:   $clientSecret,
            scope:          $scope,
            cache:          $cache,
            cacheKey:       $cacheKey,
        );

        return $this;
    }

    // --- Custom auth ---

    public function withAuth(AuthStrategy $auth): self
    {
        $this->auth = $auth;

        return $this;
    }

    /**
     * Register named strategies available for #[UseAuth(SomeClass::class)] resolution.
     */
    public function withNamedStrategy(string $classOrKey, AuthStrategy $strategy): self
    {
        $this->authResolver = $this->authResolver->withStrategy($classOrKey, $strategy);

        return $this;
    }

    // --- Default headers ---

    /**
     * Set multiple default headers sent on every request.
     * Merges with any previously set headers; later calls win on duplicate names.
     *
     * @param array<string, string> $headers
     */
    public function withDefaultHeaders(array $headers): self
    {
        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);

        return $this;
    }

    /**
     * Set a single default header sent on every request.
     * Overwrites any previously set header with the same name.
     */
    public function withDefaultHeader(string $name, string $value): self
    {
        $this->defaultHeaders[$name] = $value;

        return $this;
    }

    // --- Response decoder ---

    public function withDecoder(ResponseDecoder $decoder): self
    {
        $this->decoder = $decoder;

        return $this;
    }

    // --- Build ---

    public function build(): DefaultSdkClient
    {
        foreach (['httpClient', 'requestFactory', 'streamFactory', 'uriFactory'] as $prop) {
            if ($this->{$prop} === null) {
                throw new \LogicException("ClientBuilder: [{$prop}] must be provided before calling build().");
            }
        }

        return new DefaultSdkClient(
            httpClient:     $this->httpClient,
            requestFactory: $this->requestFactory,
            streamFactory:  $this->streamFactory,
            uriFactory:     $this->uriFactory,
            baseUrl:        $this->baseUrl,
            defaultAuth:    $this->auth,
            decoder:        $this->decoder,
            authResolver:   $this->authResolver,
            defaultHeaders: $this->defaultHeaders,
        );
    }
}
