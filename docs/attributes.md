# Attributes Reference

All attributes live in the `Tugrul\ApiGen\Attributes` namespace.

## HTTP Verb Attributes

Place exactly one HTTP verb attribute on each interface method. The argument is the URL path, which may contain `{placeholder}` segments matched by [`#[Path]`](#path) parameters.

| Attribute | HTTP Method | Example |
|---|---|---|
| `#[GET('/path')]` | GET | `#[GET('/users/{id}')]` |
| `#[POST('/path')]` | POST | `#[POST('/users')]` |
| `#[PUT('/path')]` | PUT | `#[PUT('/users/{id}')]` |
| `#[PATCH('/path')]` | PATCH | `#[PATCH('/users/{id}')]` |
| `#[DELETE('/path')]` | DELETE | `#[DELETE('/users/{id}')]` |
| `#[HEAD('/path')]` | HEAD | `#[HEAD('/users/{id}')]` |

```php
#[GET('/users')]
public function listUsers(): array;

#[DELETE('/users/{id}')]
public function deleteUser(#[Path] int $id): void;
```

---

## Parameter Binding Attributes

These attributes go on individual **method parameters**. They control how each argument maps to the outgoing HTTP request.

### `#[Path]`

Binds a parameter to a URI path segment. The placeholder name in the path defaults to the PHP parameter name. Override it with an explicit name argument.

```php
#[GET('/users/{id}')]
public function getUser(#[Path] int $id): array;

// Explicit name: PHP param is $userId, URI placeholder is {id}
#[GET('/users/{id}/posts/{postId}')]
public function getUserPost(
    #[Path('id')]     int $userId,
    #[Path('postId')] int $postId,
): array;
```

Path values are automatically URL-encoded.

### `#[Query]`

Binds a parameter to a query string parameter. The query key defaults to the PHP parameter name. `null` values are silently omitted.

```php
#[GET('/users')]
public function listUsers(
    #[Query] int    $page  = 1,
    #[Query] int    $limit = 20,
    #[Query('sort_by')] string $sortBy = 'id',  // custom key name
): array;
// → GET /users?page=1&limit=20&sort_by=id
```

### `#[QueryMap]`

Spreads an associative array into the query string. `null` values in the array are omitted.

```php
#[GET('/search')]
public function search(
    #[Query]    string $q,
    #[QueryMap] array  $filters = [],
): array;

$api->search('cats', filters: ['color' => 'orange', 'age' => null]);
// → GET /search?q=cats&color=orange
```

### `#[Header]`

Binds a parameter to a specific request header. The header name is required.

```php
#[GET('/documents')]
public function getDocuments(
    #[Header('Accept-Language')] string $locale,
    #[Header('X-Idempotency-Key')] string $key,
): array;
```

### `#[Body]`

Binds a parameter as the request body. Default encoding is `json`. The encoding can be overridden but is usually controlled by [`#[FormUrlEncoded]`](#formurlencoded) or [`#[Multipart]`](#multipart) on the method instead.

```php
#[POST('/users')]
public function createUser(
    #[Body] array $user,
): array;
// → POST /users  Content-Type: application/json  body: {"name":"..."}
```

### `#[Field]`

Binds a parameter as a form field. Fields are collected into a single body map and encoded as `application/x-www-form-urlencoded`. Requires [`#[FormUrlEncoded]`](#formurlencoded) on the method. Field name defaults to the PHP parameter name.

```php
#[POST('/login')]
#[FormUrlEncoded]
public function login(
    #[Field] string $username,
    #[Field] string $password,
): array;
// → POST /login  Content-Type: application/x-www-form-urlencoded  body: username=alice&password=...
```

### `#[Part]`

Binds a parameter as a multipart part. Parts are collected into a single body map and encoded as `multipart/form-data`. Requires [`#[Multipart]`](#multipart) on the method. Part name defaults to the PHP parameter name.

```php
#[POST('/avatars')]
#[Multipart]
public function uploadAvatar(
    #[Part] string $file,
    #[Part('content_type')] string $mimeType,
): array;
```

---

## Method and Class Modifier Attributes

These attributes modify how requests are built. Most can be applied at the **class level** (affecting all methods) or the **method level** (affecting only that method).

### `#[Headers]`

Sets multiple static headers at once. Stackable and repeatable. Method-level values override class-level values for the same header name.

```php
#[Headers(['Accept' => 'application/json', 'X-Api-Version' => '2'])]
interface UserApi
{
    #[GET('/users')]
    #[Headers(['X-Scope' => 'admin'])]  // merged with class-level headers
    public function adminList(): array;
}
```

### `#[StaticHeader]`

Sets a single static header. Stackable and repeatable. Useful when you want to set headers one at a time.

```php
#[StaticHeader('Accept', 'application/json')]
#[StaticHeader('X-Client', 'my-app/1.0')]
interface UserApi { /* ... */ }
```

### `#[StaticQuery]`

Appends a static query parameter to every request on the method or interface.

```php
#[StaticQuery('api_version', '2')]
interface UserApi { /* ... */ }
// Every method gets ?api_version=2 appended
```

### `#[FormUrlEncoded]`

Marks the request body as `application/x-www-form-urlencoded`. Use together with `#[Field]` parameters.

```php
#[POST('/tokens')]
#[FormUrlEncoded]
public function requestToken(
    #[Field] string $grant_type,
    #[Field] string $client_id,
    #[Field] string $client_secret,
): array;
```

### `#[Multipart]`

Marks the request body as `multipart/form-data`. Use together with `#[Part]` parameters.

```php
#[POST('/files')]
#[Multipart]
public function uploadFile(
    #[Part] string $file,
    #[Part] string $filename,
): array;
```

### `#[Returns]`

Hints the expected return type for response decoding. Passed to the `ResponseDecoder` (or the default JSON decoder). Two forms:

```php
// Single type
#[Returns('array')]                       // return as-is (array)
#[Returns(UserDto::class)]               // instantiate UserDto from decoded data

// Generic (array of type)
#[Returns('array', UserDto::class)]      // array of UserDto — passed to decoder as $genericOf
```

### `#[BaseUrl]`

Overrides the client's base URL for a specific method. Useful when one API has resources on a different hostname.

```php
#[GET('/records/{id}')]
#[BaseUrl('https://records.internal.example.com')]
public function getRecord(#[Path] int $id): array;
```

### `#[NoAuth]`

Disables authentication for this endpoint. The default client auth strategy is not applied.

```php
#[GET('/public/status')]
#[NoAuth]
public function healthCheck(): array;
```

### `#[UseAuth]`

Specifies a named auth strategy for this endpoint. The strategy must be registered with the client builder using `withNamedStrategy()`.

```php
// Interface
#[DELETE('/admin/users/{id}')]
#[UseAuth(AdminAuth::class)]
public function deleteUser(#[Path] int $id): void;

// Client setup
ClientBuilder::create('...')
    ->withBearerToken('default-token')
    ->withNamedStrategy(AdminAuth::class, new AdminAuth($adminSecret))
    ->build();
```

### `#[ApiNamespace]`

Sets the key used by `ProxyRegistry::fromAnnotated()` to register this stub. Applied at the class level.

```php
#[ApiNamespace('users')]
interface UserApi { /* ... */ }

// Then:
$api = ProxyRegistry::fromAnnotated($client, [UserApiStub::class]);
$api->users->list();
```

See [ProxyRegistry](proxy.md) for details.

---

## Attribute Priority

When the same header is set at multiple levels, the most specific level wins:

```
Method-level #[StaticHeader] / #[Headers]
    > Class-level #[StaticHeader] / #[Headers]
```

Auth resolution has its own priority order, documented in [Authentication — Auth Priority](authentication.md#auth-priority).
