<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\LogAuditTrailAction;
use App\Modules\Admin\Models\AuditLog;
use App\Modules\Customer\Actions\AddCustomerToFraudListAction;
use App\Modules\Customer\Actions\RemoveCustomerFromFraudListAction;
use App\Modules\Customer\Models\CustomerFraudList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class FraudDirectoryAdminController extends Controller
{
    /**
     * Display the merchant fraud directory with Blacklist and Whitelist tabs.
     */
    public function index(Request $request): Response
    {
        $type = $request->query('type', CustomerFraudList::TYPE_BLACKLIST);
        if (! in_array($type, [CustomerFraudList::TYPE_BLACKLIST, CustomerFraudList::TYPE_WHITELIST], true)) {
            $type = CustomerFraudList::TYPE_BLACKLIST;
        }

        $search = $request->query('search');

        $query = CustomerFraudList::query()
            ->with('blockedBy:id,name,email')
            ->where('type', $type);

        if (! empty($search)) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('phone_number', 'LIKE', "%{$search}%")
                    ->orWhere('ip_address', 'LIKE', "%{$search}%")
                    ->orWhere('device_fingerprint', 'LIKE', "%{$search}%")
                    ->orWhere('customer_name', 'LIKE', "%{$search}%")
                    ->orWhere('reason', 'LIKE', "%{$search}%");
            });
        }

        $entries = $query->latest('id')->paginate(15)->withQueryString();

        $blacklistCount = CustomerFraudList::query()->blacklist()->count();
        $whitelistCount = CustomerFraudList::query()->whitelist()->count();

        return Inertia::render('Admin/Fraud/FraudDirectory', [
            'entries' => $entries,
            'counts' => [
                'blacklist' => $blacklistCount,
                'whitelist' => $whitelistCount,
                'total' => $blacklistCount + $whitelistCount,
            ],
            'filters' => [
                'type' => $type,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Add a customer identifier to the blacklist or whitelist.
     */
    public function store(Request $request, AddCustomerToFraudListAction $addAction): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:blacklist,whitelist'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'device_fingerprint' => ['nullable', 'string', 'max:100'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['blocked_by_user_id'] = $request->user()?->id;

        $entry = $addAction($validated);

        app(LogAuditTrailAction::class)(
            event: AuditLog::EVENT_FRAUD_LIST_UPDATED,
            description: "Added {$validated['type']} entry for ".($validated['phone_number'] ?? $validated['ip_address'] ?? $validated['customer_name'] ?? 'identifier').'. Reason: '.($validated['reason'] ?? 'Manual addition'),
            auditable: $entry,
            oldValues: null,
            newValues: $validated,
            options: ['user' => $request->user()]
        );

        return back()->with('success', ucfirst($validated['type']).' entry added successfully.');
    }

    /**
     * Remove an entry from the fraud directory.
     */
    public function destroy(
        Request $request,
        CustomerFraudList $customerFraudList,
        RemoveCustomerFromFraudListAction $removeAction
    ): RedirectResponse {
        app(LogAuditTrailAction::class)(
            event: AuditLog::EVENT_FRAUD_LIST_UPDATED,
            description: "Removed {$customerFraudList->type} entry for ".($customerFraudList->phone_number ?? $customerFraudList->ip_address ?? $customerFraudList->customer_name ?? 'identifier'),
            auditable: $customerFraudList,
            oldValues: $customerFraudList->toArray(),
            newValues: null,
            options: ['user' => $request->user()]
        );

        $removeAction($customerFraudList);

        return back()->with('success', 'Fraud directory entry removed successfully.');
    }
}
