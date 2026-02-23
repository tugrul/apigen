<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Contracts;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\{RequestFactoryInterface, StreamFactoryInterface, UriFactoryInterface};

/**
 * The resolved, fully configured client that drives generated stubs.
 */
interface SdkClient
{
    public function getHttpClient(): ClientInterface;

    public function getRequestFactory(): RequestFactoryInterface;

    public function getStreamFactory(): StreamFactoryInterface;

    public function getUriFactory(): UriFactoryInterface;

    public function getBaseUrl(): string;

    public function getDefaultAuth(): ?AuthStrategy;

    /**
     * Execute a prepared endpoint call descriptor.
     * Generated stubs build an EndpointCall and pass it here.
     */
    public function execute(EndpointCall $call): mixed;
}
