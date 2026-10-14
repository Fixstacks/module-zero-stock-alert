<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\StockAlert\Model;

use Magento\Framework\App\ResourceConnection;

/**
 * zero_stock_alert: who waits for what.
 */
class Alerts
{
    public const WAITING = 'waiting';
    public const SENT = 'sent';
    public const CANCELLED = 'cancelled';
    /** One address can wait for this many products at most (a guard against floods). */
    public const MAX_PER_EMAIL = 50;

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * Adds a waiting alert; false when this address already waits for it.
     */
    public function add(int $productId, ?int $parentId, int $storeId, string $email, ?int $customerId): bool
    {
        $connection = $this->resource->getConnection();
        $email = mb_strtolower(trim($email));
        $exists = $connection->fetchOne($connection->select()
            ->from($this->table(), ['alert_id'])
            ->where('product_id = ?', $productId)
            ->where('store_id = ?', $storeId)
            ->where('email = ?', $email)
            ->where('status = ?', self::WAITING));
        if ($exists) {
            return false;
        }
        $connection->insert($this->table(), [
            'product_id' => $productId,
            'parent_id' => $parentId,
            'store_id' => $storeId,
            'email' => $email,
            'customer_id' => $customerId,
            'token' => bin2hex(random_bytes(16)),
        ]);

        return true;
    }

    public function waitingCount(string $email): int
    {
        $connection = $this->resource->getConnection();

        return (int) $connection->fetchOne($connection->select()
            ->from($this->table(), ['COUNT(*)'])
            ->where('email = ?', mb_strtolower(trim($email)))
            ->where('status = ?', self::WAITING));
    }

    /**
     * Waiting alerts, by the product waited for.
     *
     * @return array<int, array[]>
     */
    public function waitingByProduct(int $limit = 2000): array
    {
        $connection = $this->resource->getConnection();
        $byProduct = [];
        foreach ($connection->fetchAll($connection->select()
            ->from($this->table())
            ->where('status = ?', self::WAITING)
            ->order('alert_id ASC')
            ->limit($limit)) as $row) {
            $byProduct[(int) $row['product_id']][] = $row;
        }

        return $byProduct;
    }

    public function markSent(int $alertId): void
    {
        $this->resource->getConnection()->update(
            $this->table(),
            ['status' => self::SENT, 'sent_at' => gmdate('Y-m-d H:i:s')],
            ['alert_id = ?' => $alertId]
        );
    }

    /**
     * Cancels a waiting alert by its token; the alert, or null.
     */
    public function cancel(string $token): ?array
    {
        $connection = $this->resource->getConnection();
        $row = $connection->fetchRow($connection->select()->from($this->table())->where('token = ?', $token));
        if (!$row) {
            return null;
        }
        if ($row['status'] === self::WAITING) {
            $connection->update($this->table(), ['status' => self::CANCELLED], ['alert_id = ?' => (int) $row['alert_id']]);
        }

        return $row;
    }

    /**
     * Per product: waiting, sent, the oldest wait; most waited for first.
     */
    public function report(int $limit = 200): array
    {
        $connection = $this->resource->getConnection();

        return $connection->fetchAll($connection->select()
            ->from(['a' => $this->table()], [
                'product_id',
                'waiting' => new \Zend_Db_Expr("SUM(a.status = 'waiting')"),
                'sent' => new \Zend_Db_Expr("SUM(a.status = 'sent')"),
                'since' => new \Zend_Db_Expr("MIN(IF(a.status = 'waiting', a.created_at, NULL))"),
            ])
            ->join(['p' => $this->resource->getTableName('catalog_product_entity')], 'p.entity_id = a.product_id', ['sku'])
            ->group(['a.product_id', 'p.sku'])
            ->order(['waiting DESC', 'sent DESC'])
            ->limit($limit));
    }

    private function table(): string
    {
        return $this->resource->getTableName('zero_stock_alert');
    }
}
