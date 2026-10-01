<?php

declare(strict_types=1);

namespace DevExtreme\Data\Symfony\Doctrine;

/**
 * A trusted raw SQL expression for the `columns` whitelist of {@see DbalSource}:
 * `['total' => new Raw('amount * qty')]`. Never build it from user input.
 */
final class Raw
{
    public function __construct(public readonly string $sql)
    {
    }
}
