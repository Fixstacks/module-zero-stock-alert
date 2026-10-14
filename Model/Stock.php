<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\StockAlert\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

/**
 * What can be waited for on a product page: the product itself when it can't
 * be bought, a configurable product's variants that can't (named by their
 * options, "M / Black").
 */
class Stock
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * Whether it can be bought now in the store view (default: the current
     * one): Magento's own answer, so with multi-source inventory its
     * reservations count too, as on the product page.
     */
    public function isInStock(int $productId, ?int $storeId = null): bool
    {
        $storeId ??= (int) $this->storeManager->getStore()->getId();
        try {
            return (bool) $this->productRepository->getById($productId, false, $storeId, true)->isSalable();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, string> Product id => what it is ("" for the product itself)
     */
    public function soldOut(Product $product): array
    {
        if ($product->getTypeId() !== Configurable::TYPE_CODE) {
            return in_array($product->getTypeId(), ['simple', 'virtual'], true) && !$this->isInStock((int) $product->getId())
                ? [(int) $product->getId() => '']
                : [];
        }
        $variants = [];
        foreach ($this->variants($product) as $id => $variant) {
            if ($variant['enabled'] && !$this->isInStock($id)) {
                $variants[$id] = $variant['label'];
            }
        }
        asort($variants, SORT_NATURAL);

        return $variants;
    }

    /**
     * A configurable product's variant named by its options ("M / Black").
     */
    public function soldOutLabel(Product $product, int $variantId): ?string
    {
        return $product->getTypeId() === Configurable::TYPE_CODE ? ($this->variants($product)[$variantId]['label'] ?? null) : null;
    }

    /**
     * Every variant, sold out or not (Magento's own list of a configurable
     * product's variants leaves sold-out ones out), named by its options.
     *
     * @return array<int, array{label: string, enabled: bool}>
     */
    private function variants(Product $product): array
    {
        /** @var Configurable $type */
        $type = $product->getTypeInstance();
        $ids = array_map('intval', array_values($type->getChildrenIds((int) $product->getId())[0] ?? []));
        if (!$ids) {
            return [];
        }
        $codes = [];
        foreach ($type->getConfigurableAttributes($product) as $attribute) {
            $codes[] = $attribute->getProductAttribute()->getAttributeCode();
        }
        $collection = $this->collectionFactory->create();
        // On the storefront Magento hides sold-out products from collections: these are wanted.
        $collection->setFlag('has_stock_status_filter', true);
        $collection->addIdFilter($ids)->addAttributeToSelect([...$codes, 'status']);
        $variants = [];
        foreach ($collection as $child) {
            $labels = array_map(static fn ($code) => (string) $child->getAttributeText($code), $codes);
            $variants[(int) $child->getId()] = [
                'label' => implode(' / ', array_filter($labels)),
                'enabled' => (int) $child->getStatus() === 1,
            ];
        }

        return $variants;
    }
}
