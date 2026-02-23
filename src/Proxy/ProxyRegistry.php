<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Proxy;

use Tugrul\ApiGen\Attributes\Modifiers\ApiNamespace;
use Tugrul\ApiGen\Contracts\SdkClient;

/**
 * ProxyRegistry allows you to group multiple generated stubs under a single
 * top-level client object, accessed as named properties or via get().
 *
 * This is completely OPTIONAL — you can use generated stubs directly.
 *
 * Example structure:
 *
 *   $api = ProxyRegistry::fromStubs($client, [
 *       'users'    => new UserApiStub($client),
 *       'products' => new ProductApiStub($client),
 *   ]);
 *
 *   $api->users->list();
 *   $api->products->get(42);
 *
 * Alternatively, if you decorate interfaces with #[ApiNamespace], you can
 * auto-discover the key:
 *
 *   $api = ProxyRegistry::fromAnnotated($client, [
 *       UserApiStub::class,
 *       ProductApiStub::class,
 *   ]);
 */
final class ProxyRegistry
{
    /** @var array<string, object> */
    private array $stubs = [];

    private function __construct(private readonly SdkClient $client) {}

    // --- Factory methods ---

    /**
     * Build from an explicit name → stub map.
     *
     * @param array<string, object> $stubs
     */
    public static function fromStubs(SdkClient $client, array $stubs): self
    {
        $registry = new self($client);

        foreach ($stubs as $name => $stub) {
            $registry->register($name, $stub);
        }

        return $registry;
    }

    /**
     * Build from stub class names; reads #[ApiNamespace] from each class
     * (or its implemented interfaces) to determine the key.
     *
     * @param string[] $stubClasses
     */
    public static function fromAnnotated(SdkClient $client, array $stubClasses): self
    {
        $registry = new self($client);

        foreach ($stubClasses as $class) {
            $key = self::resolveNamespace($class);

            if ($key === null) {
                throw new \InvalidArgumentException(
                    "[{$class}] has no #[ApiNamespace] attribute on itself or its interfaces. " .
                    "Use ProxyRegistry::fromStubs() to register it manually."
                );
            }

            $registry->register($key, new $class($client));
        }

        return $registry;
    }

    // --- Registration ---

    public function register(string $name, object $stub): self
    {
        $this->stubs[$name] = $stub;

        return $this;
    }

    // --- Access ---

    public function get(string $name): object
    {
        if (!isset($this->stubs[$name])) {
            throw new \OutOfBoundsException("No stub registered under the name [{$name}].");
        }

        return $this->stubs[$name];
    }

    /** Magic property access: $proxy->users->list() */
    public function __get(string $name): object
    {
        return $this->get($name);
    }

    public function __isset(string $name): bool
    {
        return isset($this->stubs[$name]);
    }

    /** @return string[] */
    public function registeredNames(): array
    {
        return array_keys($this->stubs);
    }

    // --- Namespace resolution ---

    private static function resolveNamespace(string $class): ?string
    {
        $rc   = new \ReflectionClass($class);
        $attr = self::findApiNamespaceAttr($rc);

        if ($attr !== null) {
            return $attr->newInstance()->prefix;
        }

        // Also check implemented interfaces
        foreach ($rc->getInterfaces() as $iface) {
            $attr = self::findApiNamespaceAttr($iface);

            if ($attr !== null) {
                return $attr->newInstance()->prefix;
            }
        }

        return null;
    }

    private static function findApiNamespaceAttr(\ReflectionClass $rc): ?\ReflectionAttribute
    {
        $attrs = $rc->getAttributes(ApiNamespace::class);

        return $attrs[0] ?? null;
    }
}
