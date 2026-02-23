# CLI Tool

The `apigen` binary is registered in `composer.json` and available at `vendor/bin/apigen` after installation.

```bash
# Via composer
./vendor/bin/apigen <command> [arguments] [options]

# Or via PATH (if vendor/bin is in your PATH)
apigen <command> [arguments] [options]
```

---

## Commands

### `generate`

Generates stub classes from one or more annotated PHP interfaces.

```bash
apigen generate <InterfaceClass> [InterfaceClass2 ...] [options]
apigen generate --config=apigen.php [options]
```

#### Options

| Option | Short | Default | Description |
|---|---|---|---|
| `--output` | `-o` | `./generated` | Directory where generated files are written. |
| `--naming` | | `default` | Naming strategy. See [strategies](#naming-strategies-via-cli) below. |
| `--composer` | | *(auto-discover)* | Explicit path to `composer.json` for PSR-4 resolution. |
| `--path-mode` | | `flat` | Fallback when PSR-4 root is undetectable: `flat` or `full_namespace`. |
| `--config` | `-c` | | Path to a PHP config file. Overrides all other options. |
| `--dry-run` | | `false` | Preview what would be generated without writing any files. |
| `--force` | `-f` | `false` | Overwrite existing non-generated files without asking. |
| `--verbose` | `-v` | `false` | Print the generated class name and file path for each stub. |

#### Examples

```bash
# Single interface, default naming
apigen generate "MyApp\Api\UserApi" --output=src/Generated

# Multiple interfaces, suffix naming
apigen generate "MyApp\Api\UserApi" "MyApp\Api\ProductApi" \
    --output=src/Generated \
    --naming=suffix:Stub

# Sub-namespace + suffix: MyApp\Api\UserApi → MyApp\Api\Generated\UserApiImpl
apigen generate "MyApp\Api\UserApi" \
    --naming=subns+suffix:Generated:Impl \
    --output=src/Generated

# Dry run — see what would be generated
apigen generate --config=apigen.php --dry-run

# Verbose — also print file paths
apigen generate --config=apigen.php --verbose

# Explicit composer.json (useful in monorepos)
apigen generate "MyApp\Api\UserApi" \
    --composer=/project/composer.json \
    --output=src/Generated
```

#### Naming Strategies via CLI

| Flag value | Strategy | Example output |
|---|---|---|
| `default` | `DefaultNamingStrategy` | `MyApp\Api\UserApi` |
| `suffix:Stub` | `SuffixNamingStrategy('Stub')` | `MyApp\Api\UserApiStub` |
| `subns:Generated` | `SubNamespaceNamingStrategy('Generated')` | `MyApp\Api\Generated\UserApi` |
| `subns+suffix:Generated:Stub` | `SubNamespaceNamingStrategy('Generated', 'Stub')` | `MyApp\Api\Generated\UserApiStub` |

---

### `list`

Scans a directory recursively for PHP files and lists every interface that has at least one HTTP attribute (`#[GET]`, `#[POST]`, etc.). Useful for discovering which interfaces in a codebase are ready for generation.

```bash
apigen list [options]
```

#### Options

| Option | Short | Default | Description |
|---|---|---|---|
| `--dir` | `-d` | `./src` | Root directory to scan recursively. |
| `--verbose` | `-v` | `false` | Show each endpoint (verb + path + method name) under each interface. |

#### Examples

```bash
# List all annotated interfaces under src/
apigen list --dir=src/Api

# Show endpoints too
apigen list --dir=src --verbose
```

**Verbose output example:**

```
Scanning: src/Api

  ✓  MyApp\Api\UserApi
       GET  /users         → listUsers()
       GET  /users/{id}    → getUser()
       POST /users         → createUser()

  ✓  MyApp\Api\ProductApi
       GET  /products      → listProducts()
       POST /products      → createProduct()

2 annotated interface(s) found.
```

---

### `version`

```bash
apigen version
# Tugrul ApiGen v1.0.0
```

### `help`

```bash
apigen help
apigen --help
```

---

## Config File

The config file is the recommended approach for projects with multiple interfaces. It is a plain PHP file that returns an array.

```bash
apigen generate --config=apigen.php
apigen generate --config=apigen.php --dry-run --verbose
```

### Full Example

```php
<?php
// apigen.php — place in project root

use Tugrul\ApiGen\Generator\SubNamespaceNamingStrategy;
use MyApp\Api\{UserApi, ProductApi, OrderApi};

return [
    // Required: autoload your interface classes before generation
    'bootstrap'  => __DIR__ . '/vendor/autoload.php',

    // Interfaces to generate stubs for
    'interfaces' => [
        UserApi::class,
        ProductApi::class,
        OrderApi::class,
    ],

    // Output directory
    'output_dir' => __DIR__ . '/src/Generated',

    // Naming strategy — any StubNamingStrategy instance, or a string
    // using the same format as the --naming CLI flag
    'naming' => new SubNamespaceNamingStrategy('Generated'),

    // Optional: explicit path to composer.json
    // 'composer_json' => __DIR__ . '/composer.json',

    // Optional: fallback when PSR-4 root is undetectable
    // 'path_fallback_mode' => 'flat',   // or 'full_namespace'
];
```

### Config Keys Reference

| Key | Type | Default | Description |
|---|---|---|---|
| `bootstrap` | `string` | | Path to a PHP file to require before resolving interface classes. Usually `vendor/autoload.php`. |
| `interfaces` | `string[]` | | Array of fully-qualified interface class names to generate. |
| `output_dir` | `string` | `./generated` | Directory where generated files are written. |
| `naming` | `StubNamingStrategy\|string` | `DefaultNamingStrategy` | Naming strategy. |
| `composer_json` | `string` | *(auto-discover)* | Explicit path to `composer.json`. |
| `path_fallback_mode` | `'flat'\|'full_namespace'` | `'flat'` | Fallback path mode when PSR-4 root cannot be detected. |

### Using a String for `naming`

The `naming` key also accepts the same string format as the `--naming` CLI flag:

```php
'naming' => 'suffix:Stub',           // SuffixNamingStrategy('Stub')
'naming' => 'subns:Generated',       // SubNamespaceNamingStrategy('Generated')
'naming' => 'subns+suffix:Gen:Stub', // SubNamespaceNamingStrategy('Gen', 'Stub')
```
