<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Modules\Customer\Models\CustomerFraudList;

final class RemoveCustomerFromFraudListAction
{
    /**
     * Remove an entry from the fraud list.
     */
    public function __invoke(int|CustomerFraudList $entry): bool
    {
        $model = $entry instanceof CustomerFraudList ? $entry : CustomerFraudList::findOrFail($entry);

        return (bool) $model->delete();
    }
}
