<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Commands;

use App\Modules\Marketing\Actions\ProcessCartRecoverySequencesAction;
use Illuminate\Console\Command;

class ProcessCartRecoveryCommand extends Command
{
    protected $signature = 'cart:process-recovery';

    protected $description = 'Scan abandoned shopping carts and dispatch multi-step recovery messages via WhatsApp and SMS';

    public function handle(ProcessCartRecoverySequencesAction $action): int
    {
        $this->info('Scanning abandoned shopping carts...');

        $stats = $action();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Step 1 (1 hour) Dispatched', $stats['step_1_count']],
                ['Step 2 (6 hours) Dispatched', $stats['step_2_count']],
                ['Step 3 (24 hours) Dispatched', $stats['step_3_count']],
                ['Total Recovery Messages Sent', $stats['total_dispatched']],
            ]
        );

        $this->info('Abandoned cart recovery scan completed successfully.');

        return Command::SUCCESS;
    }
}
