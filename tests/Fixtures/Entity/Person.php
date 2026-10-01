<?php

declare(strict_types=1);

namespace DevExtreme\Data\Symfony\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'people')]
class Person
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(name: 'full_name', type: 'string')]
    public string $fullName;

    #[ORM\Column(name: 'created_at', type: 'string')]
    public string $createdAt;

    #[ORM\ManyToOne(targetEntity: Person::class)]
    #[ORM\JoinColumn(name: 'manager_id', referencedColumnName: 'id', nullable: true)]
    public ?Person $manager = null;
}
