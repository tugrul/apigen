# Quick Start

Get a working API client in five minutes.

## 1. Define an Interface

Create a PHP interface and annotate each method. Every method needs one HTTP verb attribute. Parameters are bound with parameter-level attributes.

```php
<?php

use Tugrul\ApiGen\Attributes\{
    GET, POST, PUT, DELETE,
    Path, Query, Body, Returns,
    StaticHeader, NoAuth,
};

#[StaticHeader('Accept', 'application/json')]
interface PetStoreApi
{
    #[GET('/pets')]
    #[Returns('array')]
    #[NoAuth]
    public function listPets(
        #[Query] int $page  = 1,
        #[Query] int $limit = 20,
    ): array;

    #[POST('/pets')]
    #[Returns('array')]
    public function createPet(
        #[Body] array $pet,
    ): array;

    #[GET('/pets/{petId}')]
    #[Returns('array')]
    public function getPet(
        #[Path] int $petId,
    ): array;

    #[PUT('/pets/{petId}')]
    #[Returns('array')]
    public function updatePet(
        #[Path] int $petId,
        #[Body] array $data,
    ): array;

    #[DELETE('/pets/{petId}')]
    public function deletePet(
        #[Path] int $petId,
    ): void;
}
```

See [Attributes Reference](attributes.md) for every available attribute.

## 2. Generate the Stub

### Option A — Programmatic

```php
use Tugrul\ApiGen\Generator\{StubGenerator, SuffixNamingStrategy};

$gen  = new StubGenerator(__DIR__ . '/src/Generated', new SuffixNamingStrategy('Stub'));
$stub = $gen->generate(PetStoreApi::class);

// $stub is a GeneratedStub value object
echo $stub->stubClass;  // e.g. MyApp\Api\PetStoreApiStub
echo $stub->filePath;   // e.g. /var/www/src/Generated/Api/PetStoreApiStub.php
```

### Option B — CLI

```bash
apigen generate "MyApp\Api\PetStoreApi" --output=src/Generated --naming=suffix:Stub
```

Or with a config file (recommended for projects with multiple interfaces):

```bash
apigen generate --config=apigen.php
```

See [CLI Tool](cli.md) and [Code Generation](generation.md) for details.

## 3. Build the Client and Use the Stub

```php
use Tugrul\ApiGen\Http\ClientBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

$guzzle = new Client();
$psr17  = new HttpFactory();   // implements RequestFactory + StreamFactory + UriFactory

$client = ClientBuilder::create('https://petstore.example.com/v2')
    ->withPsr18($guzzle, $psr17, $psr17, $psr17)
    ->withBearerToken('my-api-token')
    ->build();

// Instantiate the generated class with the client
$api = new PetStoreApiStub($client);

// Use it — normal PHP method calls
$pets = $api->listPets(page: 1, limit: 5);
$pet  = $api->createPet(['name' => 'Rex', 'status' => 'available']);
$one  = $api->getPet(42);
$api->deletePet(42);
```

The generated stub is a regular PHP class. Inject it via a DI container like any other service.

## Next Steps

- [Attributes Reference](attributes.md) — every attribute explained
- [Authentication](authentication.md) — OAuth 2, API keys, custom strategies
- [Code Generation](generation.md) — naming strategies, PSR-4 path resolution
- [Client Configuration](client.md) — middleware, custom decoders
