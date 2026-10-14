<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\StockAlert\ViewModel;

use Magento\Backend\Model\UrlInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Zero\StockAlert\Model\Alerts;
use Zero\StockAlert\Model\Stock;

class Report implements ArgumentInterface
{
    public function __construct(
        private readonly Alerts $alerts,
        private readonly Stock $stock,
        private readonly CollectionFactory $collectionFactory,
        private readonly UrlInterface $url
    ) {
    }

    public function rows(): array
    {
        $rows = $this->alerts->report();
        if (!$rows) {
            return [];
        }
        $names = [];
        $collection = $this->collectionFactory->create()
            ->addAttributeToSelect('name')
            ->addIdFilter(array_column($rows, 'product_id'));
        foreach ($collection as $product) {
            $names[(int) $product->getId()] = (string) $product->getName();
        }
        foreach ($rows as &$row) {
            $row['name'] = $names[(int) $row['product_id']] ?? $row['sku'];
            $row['in_stock'] = $this->stock->isInStock((int) $row['product_id']);
        }

        return $rows;
    }

    public function editUrl(int $productId): string
    {
        return $this->url->getUrl('catalog/product/edit', ['id' => $productId]);
    }
}
