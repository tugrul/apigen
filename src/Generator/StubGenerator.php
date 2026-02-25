<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Generator;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionNamedType;
use Tugrul\ApiGen\Attributes\Method\{GET, POST, PUT, PATCH, DELETE, HEAD};
use Tugrul\ApiGen\Attributes\Params\{Path, Query, QueryMap, Header, Body, Field, Part};
use Tugrul\ApiGen\Attributes\Modifiers\{Headers, StaticHeader, StaticQuery, FormUrlEncoded, Multipart};
use Tugrul\ApiGen\Attributes\Modifiers\{Returns, NoAuth, UseAuth, BaseUrl, ApiNamespace};

/**
 * Generates concrete PHP implementation classes from annotated interfaces.
 *
 * Naming is fully controlled via StubNamingStrategy. Built-in options:
 *
 *   DefaultNamingStrategy      — same namespace + same name as interface;
 *                                auto-detects file conflicts → uses "Impl" suffix
 *   SuffixNamingStrategy       — appends a fixed suffix (Stub, Client, Impl, …)
 *   SubNamespaceNamingStrategy — places stubs in a child namespace segment
 *   CallableNamingStrategy     — callable for total custom control
 *   MappedNamingStrategy       — per-interface [namespace, className] map
 *
 * Examples:
 *
 *   // Default: MyApp\Api\UserApi → MyApp\Api\UserApi (UserApiImpl on conflict)
 *   $gen = new StubGenerator('/var/generated');
 *
 *   // Classic suffix: UserApi → MyApp\Api\UserApiStub
 *   $gen = new StubGenerator('/var/generated', new SuffixNamingStrategy('Stub'));
 *
 *   // Sub-namespace: UserApi → MyApp\Api\Generated\UserApi
 *   $gen = new StubGenerator('/var/generated', new SubNamespaceNamingStrategy('Generated'));
 *
 *   // Full custom:
 *   $gen = new StubGenerator('/var/generated', new CallableNamingStrategy(
 *       fn($iface, $dir) => ['My\Clients', $iface->getShortName() . 'HttpClient']
 *   ));
 */
final class StubGenerator
{
    private const HTTP_ATTRS = [
        GET::class    => 'GET',
        POST::class   => 'POST',
        PUT::class    => 'PUT',
        PATCH::class  => 'PATCH',
        DELETE::class => 'DELETE',
        HEAD::class   => 'HEAD',
    ];

    private readonly OutputPathResolver $pathResolver;

    /**
     * @param string                   $outputDir      Root directory for generated files
     * @param StubNamingStrategy       $naming         Controls generated class name + namespace
     * @param OutputPathResolver|null  $pathResolver   Controls file placement strategy.
     *                                                 Pass null to auto-discover composer.json
     *                                                 and use PSR-4 aware path resolution.
     */
    public function __construct(
        private readonly string             $outputDir,
        private readonly StubNamingStrategy $naming       = new DefaultNamingStrategy(),
        ?OutputPathResolver                 $pathResolver = null,
    ) {
        $this->pathResolver = $pathResolver
            ?? OutputPathResolver::withAutoDiscoveredComposer(
                $outputDir,
                // Walk up from the output directory itself to find composer.json
                $outputDir,
            );
    }

    // --- Public API ---

    /**
     * Generate a stub for a single interface.
     *
     * @param  class-string $interfaceClass
     * @return GeneratedStub  Value object describing what was written
     */
    public function generate(string $interfaceClass): GeneratedStub
    {
        $rc = new ReflectionClass($interfaceClass);

        if (!$rc->isInterface()) {
            throw new \InvalidArgumentException("[{$interfaceClass}] must be an interface.");
        }

        [$stubNamespace, $stubClass] = $this->naming->resolve($rc, $this->outputDir);

        $stubClass = self::omitSuffix($stubClass, 'Interface');

        // Conflict check for DefaultNamingStrategy:
        // If the resolved output path already holds a non-generated file,
        // append 'Impl' to the class name to avoid overwriting hand-written code.
        $filePath = $this->pathResolver->resolve($rc, $stubNamespace, $stubClass);
        if (file_exists($filePath) && !$this->isGeneratedByApiGen($filePath)) {
            $stubClass .= 'Impl';
            $filePath   = $this->pathResolver->resolve($rc, $stubNamespace, $stubClass);
        }

        $code = $this->renderClass($rc, $stubClass, $stubNamespace);

        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        file_put_contents($filePath, $code);

        return new GeneratedStub(
            interfaceClass: $interfaceClass,
            stubClass:      ($stubNamespace !== '' ? $stubNamespace . '\\' : '') . $stubClass,
            stubShortName:  $stubClass,
            stubNamespace:  $stubNamespace,
            filePath:       $filePath,
        );
    }

    /**
     * @param string $stubClass
     * @param string $suffix
     * @return string
     */
    public static function omitSuffix(string $stubClass, string $suffix): string
    {
        $length = strlen($suffix);

        // Only remove the suffix if:
        // 1. The class name actually ends with the suffix, and
        // 2. Removing it would not result in an empty string.
        // If the class name is shorter than or equal to the suffix length,
        // we return it unchanged to avoid producing an empty string.
        if (strlen($stubClass) <= $length || !str_ends_with($stubClass, $suffix)) {
            return $stubClass;
        }

        return substr($stubClass, 0, -$length);
    }

    /**
     * Generate stubs for multiple interfaces in one call.
     *
     * @param  class-string[] $interfaceClasses
     * @return GeneratedStub[]
     */
    public function generateAll(array $interfaceClasses): array
    {
        return array_map(fn($c) => $this->generate($c), $interfaceClasses);
    }

    // --- Class rendering ---

    private function renderClass(ReflectionClass $rc, string $stubClass, string $namespace): string
    {
        $interfaceFqn     = '\\' . $rc->getName();
        $classHeaders     = $this->collectClassHeaders($rc);
        $classStaticQuery = $this->collectClassStaticQuery($rc);

        $methods = '';
        foreach ($rc->getMethods() as $method) {
            $methods .= $this->renderMethod($method, $classHeaders, $classStaticQuery);
        }

        $namespaceDecl = $namespace !== '' ? "namespace {$namespace};" : '';

        $useStatements = implode("\n", [
            'use Tugrul\ApiGen\Http\CallDescriptor;',
            'use Tugrul\ApiGen\Contracts\SdkClient;',
        ]);

        return <<<PHP
        <?php

        declare(strict_types=1);

        {$namespaceDecl}

        {$useStatements}

        /**
         * AUTO-GENERATED by Tugrul\ApiGen\Generator\StubGenerator.
         * Do not edit this file manually — re-generate from the source interface.
         *
         * Source interface : {$interfaceFqn}
         * Generated class  : {$stubClass}
         */
        final class {$stubClass} implements {$interfaceFqn}
        {
            public function __construct(private readonly SdkClient \$client) {}

        {$methods}
        }
        PHP;
    }

    // --- Method rendering ---

    private function renderMethod(
        ReflectionMethod $method,
        array $classHeaders,
        array $classStaticQuery,
    ): string {
        $httpAttr = $this->findHttpAttribute($method);

        if ($httpAttr === null) {
            return $this->renderUnimplementedMethod($method);
        }

        [$httpMethod, $path] = $httpAttr;
        $params     = $method->getParameters();
        $signature  = $this->buildSignature($params);
        $body       = $this->buildMethodBody($method, $httpMethod, $path, $params, $classHeaders, $classStaticQuery, $this->isReturnValue($method));
        $returnType = $this->renderReturnType($method);

        return <<<PHP

            public function {$method->getName()}({$signature}){$returnType}
            {
        {$body}
            }

        PHP;
    }

    private function renderUnimplementedMethod(ReflectionMethod $method): string
    {
        $signature  = $this->buildSignature($method->getParameters());
        $returnType = $this->renderReturnType($method);

        return <<<PHP

            public function {$method->getName()}({$signature}){$returnType}
            {
                throw new \LogicException('Method [{$method->getName()}] has no HTTP attribute and was not generated.');
            }

        PHP;
    }

    // --- Method body builder ---

    private function buildMethodBody(
        ReflectionMethod $method,
        string $httpMethod,
        string $path,
        array $params,
        array $classHeaders,
        array $classStaticQuery,
        bool $returns = true
    ): string {
        $lines   = [];
        $lines[] = '        $call = CallDescriptor::create()';
        $lines[] = "            ->method('{$httpMethod}')";

        // #[BaseUrl] override
        $baseUrlAttr = $this->getAttr($method, BaseUrl::class);
        if ($baseUrlAttr) {
            $overrideUrl = $baseUrlAttr->newInstance()->url;
            $lines[] = "            ->baseUrl('{$overrideUrl}')";
        }

        $lines[] = "            ->path('{$path}')";

        // Static headers (class-level merged with method-level; method wins)
        foreach (array_merge($classHeaders, $this->collectMethodHeaders($method)) as [$hName, $hValue]) {
            $lines[] = "            ->header('{$hName}', '{$hValue}')";
        }

        // Static query params
        foreach (array_merge($classStaticQuery, $this->collectMethodStaticQuery($method)) as [$qName, $qValue]) {
            $lines[] = "            ->queryParam('{$qName}', '{$qValue}')";
        }

        // #[Returns]
        $returnsAttr = $this->getAttr($method, Returns::class);
        if ($returnsAttr) {
            $ret         = $returnsAttr->newInstance();
            $genericPart = $ret->genericOf ? ", '{$ret->genericOf}'" : '';
            $lines[] = "            ->returnType('{$ret->type}'{$genericPart})";
        }

        // #[NoAuth]
        if ($this->getAttr($method, NoAuth::class)) {
            $lines[] = '            ->disableAuth()';
        }

        // #[UseAuth(SomeClass::class)]
        $useAuthAttr = $this->getAttr($method, UseAuth::class);
        if ($useAuthAttr) {
            $authClass = $useAuthAttr->newInstance()->authClass;
            $lines[] = "            ->useAuthClass('{$authClass}')";
        }

        // Body encoding
        $encoding = 'json';
        if ($this->getAttr($method, FormUrlEncoded::class)) {
            $encoding = 'form';
        } elseif ($this->getAttr($method, Multipart::class)) {
            $encoding = 'multipart';
        }

        // Parameter binding
        $bodyVar  = null;
        $fieldMap = [];

        foreach ($params as $param) {
            $name = $param->getName();

            if ($a = $this->paramHasAttr($param, Path::class)) {
                $attrName = $a->newInstance()->name ?? $name;
                $lines[] = "            ->pathParam('{$attrName}', \${$name})";
            } elseif ($a = $this->paramHasAttr($param, Query::class)) {
                $attrName = $a->newInstance()->name ?? $name;
                $lines[] = "            ->queryParam('{$attrName}', \${$name})";
            } elseif ($this->paramHasAttr($param, QueryMap::class)) {
                $lines[] = "            ->queryMap(\${$name})";
            } elseif ($a = $this->paramHasAttr($param, Header::class)) {
                $attrName = $a->newInstance()->name;
                $lines[] = "            ->header('{$attrName}', (string) \${$name})";
            } elseif ($a = $this->paramHasAttr($param, Body::class)) {
                $enc     = $a->newInstance()->encoding !== 'json' ? $a->newInstance()->encoding : $encoding;
                $bodyVar = "\${$name}";
                $lines[] = "            ->body(\${$name}, '{$enc}')";
            } elseif ($a = $this->paramHasAttr($param, Field::class)) {
                $attrName           = $a->newInstance()->name ?? $name;
                $fieldMap[$attrName] = "\${$name}";
            } elseif ($a = $this->paramHasAttr($param, Part::class)) {
                $attrName           = $a->newInstance()->name ?? $name;
                $fieldMap[$attrName] = "\${$name}";
            }
        }

        // Bundle #[Field] / #[Part] params as body
        if ($fieldMap && $bodyVar === null) {
            $mapLiteral = '[' . implode(', ', array_map(
                fn($k, $v) => "'{$k}' => {$v}",
                array_keys($fieldMap),
                array_values($fieldMap),
            )) . ']';
            $lines[] = "            ->body({$mapLiteral}, '{$encoding}')";
        }

        $lines[] = '        ;';
        $lines[] = '';
        $lines[] = '        ' . ($returns ? 'return ' : '') . '$this->client->execute($call);';

        return implode("\n", $lines);
    }

    // --- Signature helpers ---

    /**
     * Returns false only when the method's return type is explicitly `void`.
     * Every other case — no type hint, mixed, nullable, or any class type —
     * should still use `return` so the caller can receive the decoded response
     * and static analysers don't complain about void-typed methods returning a value.
     */
    private function isReturnValue(ReflectionMethod $method): bool
    {
        $type = $method->getReturnType();

        if ($type === null || !$type->isBuiltin()) {
            return true;
        }

        return $type->getName() !== 'void';
    }

    private function buildSignature(array $params): string
    {
        return implode(', ', array_map(function (ReflectionParameter $p): string {
            $type    = '';
            $refType = $p->getType();

            if ($refType instanceof ReflectionNamedType) {
                $nullable = $refType->allowsNull() && $refType->getName() !== 'mixed';
                $prefix   = $nullable ? '?' : '';
                $type     = $prefix . ($refType->isBuiltin() ? '' : '\\') . $refType->getName() . ' ';
            }

            $default = '';
            if ($p->isOptional() && $p->isDefaultValueAvailable()) {
                $default = ' = ' . var_export($p->getDefaultValue(), true);
            } elseif ($p->allowsNull() && !$p->isOptional()) {
                $default = ' = null';
            }

            return "{$type}\${$p->getName()}{$default}";
        }, $params));
    }

    private function renderReturnType(ReflectionMethod $method): string
    {
        $type = $method->getReturnType();
        if ($type === null) {
            return '';
        }
        if ($type instanceof ReflectionNamedType) {
            $prefix = $type->allowsNull() ? '?' : '';
            $name   = $type->isBuiltin() ? $type->getName() : '\\' . $type->getName();

            return ": {$prefix}{$name}";
        }

        return '';
    }

    // --- Attribute helpers ---

    private function findHttpAttribute(ReflectionMethod $method): ?array
    {
        foreach (self::HTTP_ATTRS as $attrClass => $verb) {
            $attr = $this->getAttr($method, $attrClass);
            if ($attr !== null) {
                return [$verb, $attr->newInstance()->path];
            }
        }

        return null;
    }

    private function getAttr(ReflectionMethod $method, string $attrClass): ?\ReflectionAttribute
    {
        return $method->getAttributes($attrClass)[0] ?? null;
    }

    private function paramHasAttr(ReflectionParameter $param, string $attrClass): ?\ReflectionAttribute
    {
        return $param->getAttributes($attrClass)[0] ?? null;
    }

    private function collectClassHeaders(ReflectionClass $rc): array
    {
        return $this->collectHeaders($rc->getAttributes(Headers::class), $rc->getAttributes(StaticHeader::class));
    }

    private function collectMethodHeaders(ReflectionMethod $m): array
    {
        return $this->collectHeaders($m->getAttributes(Headers::class), $m->getAttributes(StaticHeader::class));
    }

    private function collectHeaders(array $headersAttrs, array $staticAttrs): array
    {
        $result = [];
        foreach ($headersAttrs as $attr) {
            foreach ($attr->newInstance()->headers as $name => $value) {
                $result[] = [$name, $value];
            }
        }
        foreach ($staticAttrs as $attr) {
            $inst     = $attr->newInstance();
            $result[] = [$inst->name, $inst->value];
        }

        return $result;
    }

    private function collectClassStaticQuery(ReflectionClass $rc): array
    {
        return $this->collectStaticQuery($rc->getAttributes(StaticQuery::class));
    }

    private function collectMethodStaticQuery(ReflectionMethod $m): array
    {
        return $this->collectStaticQuery($m->getAttributes(StaticQuery::class));
    }

    private function collectStaticQuery(array $attrs): array
    {
        return array_map(fn($a) => [$a->newInstance()->name, $a->newInstance()->value], $attrs);
    }

    // ─── Conflict detection ───────────────────────────────────────────────────

    private function isGeneratedByApiGen(string $path): bool
    {
        $contents = @file_get_contents($path);

        return $contents !== false
            && str_contains($contents, 'AUTO-GENERATED by Tugrul\ApiGen');
    }
}
