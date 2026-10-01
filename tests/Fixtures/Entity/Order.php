<?php

declare(strict_types=1);

namespace DevExtreme\Data\Symfony\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Property names equal the column names so the shared parity matrix applies unchanged.
 */
#[ORM\Entity]
#[ORM\Table(name: 'orders')]
class Order
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string')]
    public string $customer;

    #[ORM\Column(type: 'string')]
    public string $category;

    #[ORM\Column(type: 'float', nullable: true)]
    public ?float $amount = null;

    #[ORM\Column(type: 'integer')]
    public int $qty;

    #[ORM\Column(type: 'string')]
    public string $ordered_at;

    #[ORM\Column(type: 'integer')]
    public int $shipped;

    #[ORM\Column(type: 'string', nullable: true)]
    public ?string $note = null;
}
