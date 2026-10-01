<?php

declare(strict_types=1);

namespace DevExtreme\Data\Symfony\Doctrine;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * Converts a DBAL query (named or positional parameters) to positional `?` placeholders.
 *
 * @internal
 */
final class SqlParameters
{
    /**
     * @param array<int|string, mixed> $parameters
     *
     * @return array{0: string, 1: list<mixed>}
     */
    public static function positional(string $sql, array $parameters): array
    {
        if ($parameters === []) {
            return [$sql, []];
        }

        if (array_is_list($parameters) || self::allIntegerKeys($parameters)) {
            ksort($parameters);

            $values = [];
            foreach ($parameters as $value) {
                if (is_array($value)) {
                    throw new InvalidArgumentException('Array parameters are only supported with named placeholders (:name).');
                }
                $values[] = self::scalar($value);
            }

            return [$sql, $values];
        }

        $values = [];

        // skip string literals and quoted identifiers, "::" casts; rewrite ":name"
        $rewritten = preg_replace_callback(
            '/\'(?:[^\'\\\\]|\\\\.|\'\')*\'|"(?:[^"]|"")*"|`[^`]*`|::|:([A-Za-z_][A-Za-z0-9_]*)/',
            static function (array $match) use ($parameters, &$values): string {
                if (!isset($match[1])) {
                    return $match[0];
                }

                $name = $match[1];
                if (!array_key_exists($name, $parameters) && !array_key_exists(':' . $name, $parameters)) {
                    throw new InvalidArgumentException(sprintf('No value bound for the named parameter ":%s".', $name));
                }

                $value = $parameters[$name] ?? $parameters[':' . $name];

                if (is_array($value)) {
                    if ($value === []) {
                        throw new InvalidArgumentException(sprintf('The array parameter ":%s" is empty.', $name));
                    }

                    foreach ($value as $item) {
                        $values[] = self::scalar($item);
                    }

                    return implode(', ', array_fill(0, count($value), '?'));
                }

                $values[] = self::scalar($value);

                return '?';
            },
            $sql,
        );

        return [$rewritten ?? $sql, $values];
    }

    /**
     * @param array<int|string, mixed> $parameters
     */
    private static function allIntegerKeys(array $parameters): bool
    {
        foreach (array_keys($parameters) as $key) {
            if (!is_int($key)) {
                return false;
            }
        }

        return true;
    }

    private static function scalar(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        throw new InvalidArgumentException('Unsupported query parameter type: ' . get_debug_type($value));
    }
}
