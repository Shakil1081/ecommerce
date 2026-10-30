<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\CalculateTradingPnLAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TradingPnLAdminController extends Controller
{
    /**
     * Display the Executive Trading Profit & Loss (P&L) Analytics dashboard.
     */
    public function index(Request $request, CalculateTradingPnLAction $calculateAction): Response
    {
        $period = $request->input('period', 'this_month');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $pnl = $calculateAction($startDate, $endDate, [
            'period' => $period,
        ]);

        return Inertia::render('Admin/Analytics/TradingPnL', [
            'pnl' => $pnl,
            'filters' => [
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }
}
