<?php

declare(strict_types=1);

namespace DevExtreme\Data\Symfony\Tests\Fixtures\Controller;

use DevExtreme\Data\LoadOptions;
use DevExtreme\Data\Symfony\DevExtremeLoader;
use DevExtreme\Data\Symfony\Doctrine\DbalSource;
use DevExtreme\Data\Symfony\Doctrine\EntitySource;
use DevExtreme\Data\Symfony\Tests\Fixtures\Entity\Order;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class ApiController
{
    public function __construct(private readonly DevExtremeLoader $loader)
    {
    }

    #[Route('/table')]
    public function table(Connection $connection): JsonResponse
    {
        return $this->loader->response(DbalSource::forTable($connection, 'orders', primaryKey: ['id']));
    }

    #[Route('/dbal')]
    public function dbal(Connection $connection): JsonResponse
    {
        $query = $connection->createQueryBuilder()
            ->select('o.id', 'o.category', 'o.amount', 'o.shipped')
            ->from('orders', 'o')
            ->where('o.category IN (:categories)')
            ->andWhere('o.qty >= :qty')
            ->setParameter('categories', ['Books', 'Games'])
            ->setParameter('qty', 2);

        return $this->loader->response(DbalSource::forQueryBuilder($connection, $query, primaryKey: ['id']));
    }

    #[Route('/entity')]
    public function entity(EntityManagerInterface $em): JsonResponse
    {
        return $this->loader->response(EntitySource::for($em, Order::class, where: 'shipped IN (0, 1)'));
    }

    #[Route('/array')]
    public function array(): JsonResponse
    {
        return $this->loader->response([['id' => 1], ['id' => 2], ['id' => 3]]);
    }

    #[Route('/resolved')]
    public function resolved(LoadOptions $options): JsonResponse
    {
        return new JsonResponse(['take' => $options->take, 'total' => $options->requireTotalCount]);
    }
}
