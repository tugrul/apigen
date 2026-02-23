# ProxyRegistry

`ProxyRegistry` groups multiple generated stubs under a single top-level object and makes them accessible as named properties or via `get()`.

This is **entirely optional** — generated stubs work perfectly well on their own and can be registered directly in a DI container. Use `ProxyRegistry` when you want a single `$api` object with named sub-clients.

```php
$api->users->getUser(42);
$api->products->listProducts();
$api->orders->createOrder([/* ... */]);
```

---

## `fromStubs` — Manual Registration

Pass an explicit `name → stub` map:

```php
use Tugrul\ApiGen\Proxy\ProxyRegistry;

$api = ProxyRegistry::fromStubs($client, [
    'users'    => new UserApiStub($client),
    'products' => new ProductApiStub($client),
    'orders'   => new OrderApiStub($client),
]);

// Access by magic property
$users = $api->users->listUsers();

// Access by method
$product = $api->get('products')->getProduct(42);

// Check if registered
if (isset($api->orders)) {
    $api->orders->createOrder([/* ... */]);
}

// List all registered names
$api->registeredNames();  // ['users', 'products', 'orders']
```

### Adding Stubs After Construction

```php
$api = ProxyRegistry::fromStubs($client, [/* initial stubs */]);
$api->register('invoices', new InvoiceApiStub($client));
```

---

## `fromAnnotated` — Auto-discovery via `#[ApiNamespace]`

Decorate your interfaces with `#[ApiNamespace('key')]`, generate stubs as usual, then let `ProxyRegistry` read the attribute automatically:

```php
use Tugrul\ApiGen\Attributes\ApiNamespace;

#[ApiNamespace('users')]
interface UserApi
{
    #[GET('/users')]
    public function listUsers(): array;
}

#[ApiNamespace('products')]
interface ProductApi
{
    #[GET('/products')]
    public function listProducts(): array;
}
```

```php
$api = ProxyRegistry::fromAnnotated($client, [
    UserApiStub::class,
    ProductApiStub::class,
]);

$api->users->listUsers();
$api->products->listProducts();
```

`fromAnnotated` reads `#[ApiNamespace]` from the stub class itself, or falls back to checking the interfaces it implements. If no `#[ApiNamespace]` is found anywhere, it throws `\InvalidArgumentException`.

---

## DI Container Integration

`ProxyRegistry` is a simple final class with no static state. Register it as a singleton in your container:

```php
// Laravel
$this->app->singleton(ProxyRegistry::class, function ($app) {
    return ProxyRegistry::fromAnnotated(
        $app->make(SdkClient::class),
        [UserApiStub::class, ProductApiStub::class],
    );
});

// Symfony (services.yaml)
// App\ApiRegistry:
//   factory: ['Tugrul\ApiGen\Proxy\ProxyRegistry', 'fromAnnotated']
//   arguments: ['@sdk.client', ['App\Generated\UserApiStub', 'App\Generated\ProductApiStub']]
```

---

## API Reference

| Method | Description |
|---|---|
| `::fromStubs(SdkClient $client, array $stubs): self` | Build from explicit `name → stub` map. |
| `::fromAnnotated(SdkClient $client, array $classes): self` | Build from stub class names; reads `#[ApiNamespace]`. |
| `->register(string $name, object $stub): self` | Add a stub after construction. |
| `->get(string $name): object` | Retrieve a stub by name. Throws `\OutOfBoundsException` if not found. |
| `->__get(string $name): object` | Magic property access. Same as `get()`. |
| `->__isset(string $name): bool` | Magic isset check. |
| `->registeredNames(): string[]` | List all registered names. |
