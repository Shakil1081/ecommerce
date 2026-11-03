<?php

declare(strict_types=1);

namespace App\Modules\Order\Actions;

use App\Modules\Order\Models\Order;

final class GenerateOrderBagTicketHtmlAction
{
    /**
     * Generate printable Order Bag Tickets HTML with Code128 barcodes.
     *
     * @param  list<Order>|Order  $orders
     */
    public function __invoke(Order|array $orders): string
    {
        $orderList = is_array($orders) ? $orders : [$orders];

        $ticketsHtml = '';
        $totalOrders = count($orderList);

        foreach ($orderList as $index => $order) {
            $isLast = $index === $totalOrders - 1;
            $ticketsHtml .= $this->renderSingleTicket($order, ! $isLast);
        }

        $nowFormatted = now()->format('Y-m-d H:i');

        return <<<HTML
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Bag Tickets ({$totalOrders} Orders) — {$nowFormatted}</title>
    <style>
        @page {
            size: 100mm 150mm;
            margin: 0;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #1e293b;
            color: #ffffff;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .toolbar-info {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 600;
        }
        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-print {
            background: #eb286c;
            color: #ffffff;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }
        .btn-print:hover {
            background: #d41f5e;
        }
        .btn-close {
            background: #475569;
            color: #ffffff;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
        }
        .btn-close:hover {
            background: #334155;
        }

        .tickets-container {
            padding: 15px 0;
        }

        .bag-ticket {
            width: 100mm;
            min-height: 145mm;
            margin: 0 auto 20px auto;
            background: #ffffff;
            border: 2px solid #000000;
            padding: 5mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            font-size: 11px;
            line-height: 1.35;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
        }

        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #000;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .store-brand {
            font-size: 13px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .ticket-badge {
            background: transparent;
            color: #000;
            border: 1.5px solid #000;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: 700;
            border-radius: 3px;
            text-transform: uppercase;
        }
        .ticket-date {
            font-size: 9.5px;
            color: #475569;
            margin-top: 1px;
        }

        .barcode-wrap {
            text-align: center;
            padding: 4px 0 6px 0;
            border-bottom: 1.5px dashed #000;
            margin-bottom: 6px;
        }
        .barcode-svg-container {
            display: flex;
            justify-content: center;
        }
        .barcode-number {
            font-family: "Courier New", Courier, monospace;
            font-size: 13px;
            font-weight: 900;
            letter-spacing: 1.5px;
            margin-top: 2px;
        }

        .recipient-box {
            border: 1.5px solid #000;
            border-radius: 4px;
            padding: 5px 7px;
            margin-bottom: 6px;
            background: transparent;
        }
        .box-title {
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 2px;
            letter-spacing: 0.5px;
        }
        .cust-name {
            font-size: 13px;
            font-weight: 700;
            color: #000;
        }
        .cust-phone {
            font-size: 14px;
            font-weight: normal; /* Optimized printing cost: phone number not bold */
            color: #000;
            letter-spacing: 0.5px;
            margin: 2px 0;
        }
        .cust-addr {
            font-size: 10.5px;
            line-height: 1.3;
            color: #1e293b;
        }
        .zone-tag {
            display: inline-block;
            background: transparent;
            color: #000;
            border: 1px solid #000;
            font-size: 9px;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 3px;
            margin-top: 3px;
            text-transform: uppercase;
        }

        .cod-collection-banner {
            border: 1.5px solid #000;
            background: transparent;
            color: #000000;
            padding: 6px 8px;
            border-radius: 4px;
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cod-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .cod-sub {
            font-size: 8.5px;
            color: #475569;
        }
        .cod-val {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
            margin-bottom: 6px;
        }
        .items-table th {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 3px 4px;
            text-align: left;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 8.5px;
            background: transparent;
        }
        .items-table td {
            border-bottom: 1px dashed #cbd5e1;
            padding: 4px 4px;
            vertical-align: top;
        }
        .item-check {
            width: 14px;
            font-size: 12px;
            text-align: center;
            font-family: monospace;
        }
        .item-title {
            font-weight: 700;
            color: #0f172a;
        }
        .item-attr {
            font-size: 8.5px;
            color: #475569;
        }
        .item-sku {
            font-family: monospace;
            font-size: 8px;
            color: #64748b;
        }
        .item-qty {
            text-align: center;
            font-weight: 700;
            font-size: 11px;
            width: 28px;
        }
        .item-price {
            text-align: right;
            font-weight: 700;
            width: 45px;
        }

        .order-notes-box {
            border: 1px dashed #000;
            background: transparent;
            padding: 4px 6px;
            border-radius: 3px;
            font-size: 9px;
            margin-bottom: 6px;
        }
        .order-notes-title {
            font-weight: 700;
            color: #000;
            text-transform: uppercase;
            font-size: 8px;
        }

        .summary-grid {
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #000;
            padding-top: 4px;
            margin-bottom: 6px;
            font-size: 9.5px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .checklist-footer {
            border-top: 1.5px solid #000;
            padding-top: 4px;
            font-size: 8px;
            color: #334155;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .sign-line {
            border-bottom: 1px solid #94a3b8;
            display: inline-block;
            width: 45px;
            height: 10px;
        }

        @media print {
            body {
                background: #ffffff !important;
            }
            .no-print {
                display: none !important;
            }
            .tickets-container {
                padding: 0;
            }
            .bag-ticket {
                margin: 0;
                border: 1.5px solid #000;
                box-shadow: none;
                background: #ffffff !important;
            }
            .ticket-badge, .recipient-box, .zone-tag, .cod-collection-banner, .items-table th, .order-notes-box {
                background: transparent !important;
                -webkit-print-color-adjust: economy;
                print-color-adjust: economy;
            }
            .page-break {
                page-break-after: always;
                break-after: page;
            }
        }
    </style>
</head>
<body>

<div class="toolbar no-print">
    <div class="toolbar-info">
        <span>📦 <strong>Order Bag Tickets</strong> (ব্যাগ টিকিট ও প্যাকিং চালান)</span>
        <span>•</span>
        <span>{$totalOrders} Ticket(s) Ready</span>
    </div>
    <div class="toolbar-actions">
        <button onclick="window.print()" class="btn-print">
            🖨️ Print Tickets (Ctrl + P)
        </button>
        <button onclick="window.close()" class="btn-close">
            ✕ Close
        </button>
    </div>
</div>

<div class="tickets-container">
    {$ticketsHtml}
</div>

</body>
</html>
HTML;
    }

    /**
     * Render single 100mm x 150mm Bag Ticket HTML.
     */
    private function renderSingleTicket(Order $order, bool $hasPageBreak): string
    {
        $order->loadMissing(['items', 'district']);

        $barcodeSvg = $this->generateCode128Svg($order->order_number, 36);

        $customerName = htmlspecialchars($order->customer_name, ENT_QUOTES, 'UTF-8');
        $customerPhone = htmlspecialchars($order->customer_phone, ENT_QUOTES, 'UTF-8');
        $deliveryAddress = htmlspecialchars($order->delivery_address, ENT_QUOTES, 'UTF-8');

        $zoneLabels = [
            'inside_dhaka' => 'ঢাকা সিটি (Inside Dhaka)',
            'dhaka_suburbs' => 'ঢাকা উপশহর (Suburbs)',
            'outside_dhaka' => 'ঢাকার বাইরে (Outside Dhaka)',
        ];
        $zoneLabel = $zoneLabels[$order->delivery_zone] ?? strtoupper($order->delivery_zone);

        $payableCod = (float) ($order->payable_cod_amount_bdt ?? $order->total_bdt);
        $isPaid = $order->payment_status === Order::PAYMENT_PAID || $payableCod <= 0;

        $codBanner = '';
        if ($isPaid) {
            $codBanner = <<<'HTML'
            <div class="cod-collection-banner" style="background: transparent; border: 1.5px solid #000; color: #000;">
                <div>
                    <div class="cod-label">PAID ONLINE / অগ্রিম পরিশোধিত</div>
                    <div class="cod-sub">কাস্টমার থেকে কোনো টাকা নেওয়া যাবে না (COD ৳0)</div>
                </div>
                <div class="cod-val">PAID ৳0</div>
            </div>
HTML;
        } else {
            $formattedCod = number_format($payableCod, 0);
            $codBanner = <<<HTML
            <div class="cod-collection-banner" style="background: transparent; border: 1.5px solid #000; color: #000;">
                <div>
                    <div class="cod-label">CASH TO COLLECT (ক্যাশ অন ডেলিভারি)</div>
                    <div class="cod-sub">ডেলিভারির সময় এই টাকা সংগ্রহ করুন</div>
                </div>
                <div class="cod-val">৳{$formattedCod}</div>
            </div>
HTML;
        }

        $itemsRows = '';
        foreach ($order->items as $item) {
            $pName = htmlspecialchars($item->product_name, ENT_QUOTES, 'UTF-8');
            $pSku = htmlspecialchars($item->sku ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $pQty = (int) $item->quantity;
            $pPrice = number_format((float) $item->subtotal_bdt, 0);

            $attrsStr = '';
            if (! empty($item->attributes) && is_array($item->attributes)) {
                $pairs = [];
                foreach ($item->attributes as $k => $v) {
                    $pairs[] = htmlspecialchars("{$k}: {$v}", ENT_QUOTES, 'UTF-8');
                }
                $attrsStr = '<div class="item-attr">'.implode(' | ', $pairs).'</div>';
            }

            $itemsRows .= <<<HTML
            <tr>
                <td class="item-check">▢</td>
                <td>
                    <div class="item-title">{$pName}</div>
                    {$attrsStr}
                    <div class="item-sku">SKU: {$pSku}</div>
                </td>
                <td class="item-qty">{$pQty}x</td>
                <td class="item-price">৳{$pPrice}</td>
            </tr>
HTML;
        }

        $notesSection = '';
        if (! empty($order->customer_notes)) {
            $notes = htmlspecialchars($order->customer_notes, ENT_QUOTES, 'UTF-8');
            $notesSection = <<<HTML
            <div class="order-notes-box">
                <div class="order-notes-title">⚠️ Special Customer Note / বিশেষ নির্দেশ:</div>
                <div>{$notes}</div>
            </div>
HTML;
        }

        $subtotal = number_format((float) $order->subtotal_bdt, 0);
        $shipping = number_format((float) $order->shipping_fee_bdt, 0);
        $total = number_format((float) $order->total_bdt, 0);
        $orderDate = $order->created_at ? $order->created_at->format('d M Y, h:i A') : 'N/A';
        $pageBreakClass = $hasPageBreak ? 'page-break' : '';

        $printCount = (int) $order->ticket_print_count;
        $printBadge = $printCount > 1
            ? "<span style='background:#dc2626; color:#ffffff; font-size:8.5px; font-weight:800; padding:1px 5px; border-radius:3px; margin-left:4px;'>REPRINT #{$printCount}</span>"
            : "<span style='background:#e2e8f0; color:#334155; font-size:8.5px; font-weight:700; padding:1px 4px; border-radius:2px; margin-left:4px;'>Print #1</span>";

        return <<<HTML
    <div class="bag-ticket {$pageBreakClass}">
        <div>
            <!-- Header -->
            <div class="ticket-header">
                <div>
                    <div class="store-brand">E-Commerce Dispatch Hub</div>
                    <div class="ticket-date">Date: {$orderDate} • {$printBadge}</div>
                </div>
                <div>
                    <span class="ticket-badge">BAG TICKET #{$order->id}</span>
                </div>
            </div>

            <!-- Primary Order Number Barcode -->
            <div class="barcode-wrap">
                <div class="barcode-svg-container">
                    {$barcodeSvg}
                </div>
                <div class="barcode-number">{$order->order_number}</div>
            </div>

            <!-- Recipient & Delivery Box -->
            <div class="recipient-box">
                <div class="box-title">DELIVER TO (গ্রাহকের ঠিকানা)</div>
                <div class="cust-name">{$customerName}</div>
                <div class="cust-phone">📞 {$customerPhone}</div>
                <div class="cust-addr">{$deliveryAddress}</div>
                <div>
                    <span class="zone-tag">{$zoneLabel}</span>
                </div>
            </div>

            <!-- COD Collection Banner -->
            {$codBanner}

            <!-- Items In Bag Checklist -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="item-check">✓</th>
                        <th>Packed Item Details</th>
                        <th style="text-align:center;">Qty</th>
                        <th style="text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    {$itemsRows}
                </tbody>
            </table>

            <!-- Customer Notes if any -->
            {$notesSection}
        </div>

        <div>
            <!-- Financial Summary -->
            <div class="summary-grid">
                <div>Subtotal: ৳{$subtotal} • Delivery: ৳{$shipping}</div>
                <div><strong>Total: ৳{$total}</strong></div>
            </div>

            <!-- Verification Footer -->
            <div class="checklist-footer">
                <div>Packed By: <span class="sign-line"></span></div>
                <div>Verified By: <span class="sign-line"></span></div>
                <div>Mushak 6.3 Registered</div>
            </div>
        </div>
    </div>
HTML;
    }

    /**
     * Pure PHP Code128-B SVG Barcode Generator.
     */
    private function generateCode128Svg(string $text, int $barHeight = 36): string
    {
        $code128Patterns = [
            '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
            '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
            '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
            '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
            '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
            '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
            '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
            '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
            '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
            '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
            '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
        ];

        // Code128 Start B = 104
        $chars = [104];
        $sum = 104;

        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $ascii = ord($text[$i]);
            $val = $ascii - 32;
            if ($val >= 0 && $val <= 95) {
                $chars[] = $val;
                $sum += ($val * ($i + 1));
            }
        }

        // Checksum
        $checksum = $sum % 103;
        $chars[] = $checksum;

        // Stop code = 106
        $chars[] = 106;

        $modules = '';
        foreach ($chars as $val) {
            $pattern = $code128Patterns[$val] ?? '212222';
            $bar = true;
            for ($j = 0; $j < strlen($pattern); $j++) {
                $width = (int) $pattern[$j];
                $modules .= str_repeat($bar ? '1' : '0', $width);
                $bar = ! $bar;
            }
        }

        $totalModules = strlen($modules);
        $svgWidth = $totalModules * 1.5;

        $rects = '';
        $x = 0;
        for ($i = 0; $i < $totalModules; $i++) {
            if ($modules[$i] === '1') {
                $rects .= "<rect x='{$x}' y='0' width='1.5' height='{$barHeight}' fill='#000'/>";
            }
            $x += 1.5;
        }

        return "<svg width='{$svgWidth}' height='{$barHeight}' viewBox='0 0 {$svgWidth} {$barHeight}' style='max-width:100%; height:auto;' xmlns='http://www.w3.org/2000/svg'>{$rects}</svg>";
    }
}
