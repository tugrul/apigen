<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Contracts;

/**
 * Value object that carries everything needed to make one HTTP call.
 * Generated stub code populates this and hands it to SdkClient::execute().
 */
interface EndpointCall
{
    public function getMethod(): string;

    public function getPath(): string;

    /** @return array<string, string> */
    public function getPathParams(): array;

    /** @return array<string, mixed> */
    public function getQueryParams(): array;

    /** @return array<string, string> */
    public function getHeaders(): array;

    public function getBody(): mixed;

    public function getBodyEncoding(): string; // 'json' | 'form' | 'multipart' | 'raw'

    public function getAuth(): ?AuthStrategy;

    public function isAuthDisabled(): bool;

    public function getReturnType(): ?string;

    public function getReturnGenericOf(): ?string;
}
