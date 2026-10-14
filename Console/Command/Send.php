<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\StockAlert\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zero\StockAlert\Model\Notifier;

/**
 * bin/magento zero:stock-alert:send: what cron does every 15 minutes, now.
 */
class Send extends Command
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly State $state
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('zero:stock-alert:send')->setDescription('Emails everyone waiting for a product that is back in stock');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode(Area::AREA_CRONTAB);
        } catch (\Throwable) {
            // Already set.
        }
        $result = $this->notifier->sendDue();
        $output->writeln(sprintf('Sent: %d, failed: %d', $result['sent'], $result['failed']));

        return Command::SUCCESS;
    }
}
