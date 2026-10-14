<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 *
 * Zero_StockAlert: "Tell me when it's back" on out-of-stock products (any
 * visitor, by email; a configurable product's sold-out variants too), one
 * email when it is back. See README.md.
 */
use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Zero_StockAlert', __DIR__);
