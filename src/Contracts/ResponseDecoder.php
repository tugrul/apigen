<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Contracts;

/**
 * Pluggable response decoder.
 * Replace the default JSON decoder with e.g. XML, MessagePack, etc.
 */
interface ResponseDecoder
{
    public function decode(string $body, ?string $type, ?string $genericOf): mixed;
}