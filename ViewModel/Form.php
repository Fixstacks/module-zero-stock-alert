<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\StockAlert\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Zero\StockAlert\Model\Stock;

/**
 * The product page's "Tell me when it's back": for the product, or for a
 * configurable product's sold-out variants.
 */
class Form implements ArgumentInterface
{
    public function __construct(
        private readonly Registry $registry,
        private readonly Stock $stock,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $url
    ) {
    }

    public function product(): ?Product
    {
        $product = $this->registry->registry('current_product');

        return $product instanceof Product ? $product : null;
    }

    /**
     * @return array<int, string> What can be waited for: product id => variant name ("" for the product itself)
     */
    public function soldOut(): array
    {
        $product = $this->product();
        if (!$product || !$this->scopeConfig->isSetFlag('zero_stock_alert/general/enabled', ScopeInterface::SCOPE_STORE)) {
            return [];
        }

        return $this->stock->soldOut($product);
    }

    public function actionUrl(): string
    {
        return $this->url->getUrl('stockalert/alert/subscribe');
    }
}
