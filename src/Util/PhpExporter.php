<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Util;

use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use stdClass;
use UnitEnum;

final class PhpExporter
{
    public static function export(mixed $value): string
    {
        return match (true) {
            is_null($value)   => 'null',
            is_bool($value)   => $value ? 'true' : 'false',
            is_int($value)    => (string) $value,
            is_float($value)  => self::exportFloat($value),
            is_string($value) => var_export($value, true),
            is_array($value)  => self::exportArray($value),
            is_object($value) => self::exportObject($value),
            default           => throw new InvalidArgumentException('Unsupported type: ' . gettype($value)),
        };
    }

    private static function exportFloat(float $value): string
    {
        if (is_nan($value)) {
            return 'NAN';
        }

        if (is_infinite($value)) {
            return $value > 0 ? 'INF' : '-INF';
        }

        $formatted = rtrim(rtrim(number_format($value, 14, '.', ''), '0'), '.');

        return str_contains($formatted, '.') ? $formatted : $formatted . '.0';
    }

    private static function exportArray(array $arr): string
    {
        if (empty($arr)) {
            return '[]';
        }

        $isList   = array_is_list($arr);
        $items    = [];

        foreach ($arr as $key => $val) {
            $exportedVal = self::export($val);
            $items[]     = $isList ? $exportedVal : var_export($key, true) . " => {$exportedVal}";
        }

        return "[" . implode(",", $items) . "]";
    }

    private static function exportObject(object $obj): string
    {
        $class = get_class($obj);

        if ($obj instanceof DateTimeInterface) {
            return self::exportDateTime($obj, $class);
        }

        if ($obj instanceof stdClass) {
            return self::exportStdClass($obj);
        }

        if ($obj instanceof UnitEnum) {
            return self::exportEnum($obj, $class);
        }

        if (method_exists($obj, '__set_state')) {
            return self::exportSetState($obj, $class);
        }

        return self::exportViaReflection($obj, $class);
    }

    private static function exportDateTime(DateTimeInterface $obj, string $class): string
    {
        $date     = $obj->format('Y-m-d H:i:s');
        $timezone = $obj->getTimezone()->getName();

        return "new \\{$class}('{$date}', new \\DateTimeZone('{$timezone}'))";
    }

    private static function exportStdClass(stdClass $obj): string
    {
        $arr = (array) $obj;

        if (empty($arr)) {
            return '(object) []';
        }



        $items    = [];

        foreach ($arr as $key => $val) {
            $items[] = var_export($key, true) . ' => ' . self::export($val);
        }

        return "(object) [" . implode(",", $items) . "]";
    }

    private static function exportEnum(UnitEnum $obj, string $class): string
    {
        return "\\{$class}::{$obj->name}";
    }

    private static function exportSetState(object $obj, string $class): string
    {
        $props   = (array) $obj;
        $cleaned = [];

        foreach ($props as $key => $val) {
            $cleanKey           = preg_replace('/^\x00.+\x00/', '', $key);
            $cleaned[$cleanKey] = $val;
        }

        return "\\{$class}::__set_state(" . self::exportArray($cleaned) . ')';
    }

    private static function exportViaReflection(object $obj, string $class): string
    {
        try {
            $ref  = new ReflectionClass($obj);
            $ctor = $ref->getConstructor();

            if ($ctor === null || $ctor->getNumberOfRequiredParameters() === 0) {
                return "new \\{$class}()";
            }

            $props = [];
            foreach ($ref->getProperties() as $prop) {
                if ($prop->isInitialized($obj)) {
                    $props[$prop->getName()] = $prop->getValue($obj);
                }
            }

            $args = [];
            foreach ($ctor->getParameters() as $param) {
                $name = $param->getName();

                if (array_key_exists($name, $props)) {
                    $args[] = self::export($props[$name]);
                } elseif ($param->isDefaultValueAvailable()) {
                    break;
                } else {
                    return "/* cannot reconstruct {$class}: missing constructor arg \${$name} */ null";
                }
            }

            return "new \\{$class}(" . implode(', ', $args) . ')';

        } catch (ReflectionException) {
            return "/* cannot reconstruct {$class} */ null";
        }
    }

}