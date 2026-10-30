<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogAdminController extends Controller
{
    /**
     * Display enterprise audit log trail with event filtering.
     */
    public function index(Request $request): Response
    {
        $query = AuditLog::with('user')->orderByDesc('created_at');

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('user_email', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('auditable_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to').' 23:59:59');
        }

        $logs = $query->paginate(20)->withQueryString();

        $eventsList = [
            AuditLog::EVENT_PRICE_UPDATED,
            AuditLog::EVENT_STOCK_ADJUSTED,
            AuditLog::EVENT_ORDER_STATUS_CHANGED,
            AuditLog::EVENT_MUSHAK_ISSUED,
            AuditLog::EVENT_FRAUD_LIST_UPDATED,
            AuditLog::EVENT_SECURITY_SETTING_CHANGED,
            AuditLog::EVENT_ROLE_UPDATED,
        ];

        return Inertia::render('Admin/Audit/AuditLogIndex', [
            'logs' => $logs,
            'eventsList' => $eventsList,
            'filters' => $request->only(['event', 'search', 'date_from', 'date_to']),
        ]);
    }
}
