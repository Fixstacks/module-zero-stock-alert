<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\StockAlert\Cron;

use Zero\StockAlert\Model\Notifier;

class Send
{
    public function __construct(private readonly Notifier $notifier)
    {
    }

    public function execute(): void
    {
        $this->notifier->sendDue();
    }
}
