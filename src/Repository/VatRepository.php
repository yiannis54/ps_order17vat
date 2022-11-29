<?php

namespace PrestaShop\Module\Order17Vat\Repository;

use Doctrine\DBAL\Connection;
use PDO;

class VatRepository
{
    private $connection;
    private $dbPrefix;

    public function __construct(Connection $connection, $dbPrefix)
    {
        $this->connection = $connection;
        $this->dbPrefix = $dbPrefix;
    }

    /**
     * Gets vat status by order.
     *
     * @param int $orderId
     *
     * @return int
     */
    public function findIdByOrder(int $orderId): int
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        $queryBuilder
            ->select('`id_vat17`')
            ->from($this->dbPrefix . 'order17vat')
            ->where('`id_order` = :order_id')
        ;

        $queryBuilder->setParameter('order_id', $orderId);

        return (int) $queryBuilder->execute()->fetch(PDO::FETCH_COLUMN);
    }

    /**
     * Gets vat status by order.
     *
     * @param int $orderId
     *
     * @return bool
     */
    public function getVat17Status(int $orderId): bool
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        $queryBuilder
            ->select('`is_vat_17`')
            ->from($this->dbPrefix . 'order17vat')
            ->where('`id_order` = :order_id')
        ;

        $queryBuilder->setParameter('order_id', $orderId);

        return (bool) $queryBuilder->execute()->fetch(PDO::FETCH_COLUMN);
    }
}
