<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Actions;

use App\Modules\Logistics\Models\CourierGatewaySetting;
use App\Modules\Order\Models\Order;

class GenerateShippingLabelHtmlAction
{
    /**
     * Generate 4x6 inch (100mm x 150mm) thermal shipping label HTML.
     *
     * @param  list<Order>|Order  $orders
     */
    public function __invoke(Order|array $orders): string
    {
        $orderList = is_array($orders) ? $orders : [$orders];

        $labelsHtml = '';
        foreach ($orderList as $index => $order) {
            $isLast = $index === count($orderList) - 1;
            $labelsHtml .= $this->renderSingleLabel($order, ! $isLast);
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shipping Labels (4x6 Thermal)</title>
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
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
        }
        .page {
            width: 100mm;
            height: 150mm;
            max-height: 150mm;
            padding: 6mm;
            background: #ffffff;
            margin: 10px auto;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: 1px solid #cbd5e1;
        }
        @media print {
            body {
                background: none;
            }
            .page {
                margin: 0;
                border: none;
            }
            .page-break {
                page-break-after: always;
            }
            .no-print {
                display: none !important;
            }
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 4px;
        }
        .merchant-title {
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .merchant-sub {
            font-size: 9px;
            color: #475569;
        }
        .courier-badge {
            background: #000;
            color: #fff;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 2px;
        }
        .barcode-section {
            text-align: center;
            margin: 4px 0;
        }
        .barcode-text {
            font-family: monospace;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-top: 2px;
        }
        .section-box {
            border: 1px solid #000;
            border-radius: 4px;
            padding: 5px;
            margin-bottom: 4px;
        }
        .section-title {
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 2px;
        }
        .recipient-name {
            font-size: 14px;
            font-weight: 800;
            line-height: 1.2;
        }
        .recipient-phone {
            font-size: 15px;
            font-weight: 900;
            letter-spacing: 0.5px;
            margin: 2px 0;
        }
        .recipient-address {
            font-size: 10px;
            line-height: 1.3;
        }
        .zone-pill {
            display: inline-block;
            background: #e2e8f0;
            color: #0f172a;
            padding: 1px 4px;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 2px;
            margin-top: 2px;
        }
        .cod-banner {
            border: 2px dashed #000;
            background: #f8fafc;
            padding: 6px;
            text-align: center;
            margin: 4px 0;
        }
        .cod-label {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .cod-amount {
            font-size: 18px;
            font-weight: 900;
        }
        .prepaid-banner {
            border: 2px solid #000;
            background: #000;
            color: #fff;
            padding: 6px;
            text-align: center;
            margin: 4px 0;
        }
        .prepaid-text {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin-bottom: 4px;
        }
        .items-table th, .items-table td {
            border-bottom: 1px solid #cbd5e1;
            padding: 2px 4px;
            text-align: left;
        }
        .items-table th {
            font-weight: 700;
            text-transform: uppercase;
        }
        .footer {
            border-top: 1px solid #000;
            padding-top: 4px;
            display: flex;
            justify-content: space-between;
            font-size: 8px;
            color: #475569;
        }
        .print-btn-bar {
            text-align: center;
            padding: 15px;
        }
        .print-btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="no-print print-btn-bar">
        <button class="print-btn" onclick="window.print()">🖨️ Print 4x6 Thermal Label</button>
    </div>
    {$labelsHtml}
</body>
</html>
HTML;
    }

    protected function renderSingleLabel(Order $order, bool $hasPageBreak = false): string
    {
        $breakClass = $hasPageBreak ? 'page-break' : '';
        $courierName = strtoupper($order->courier_code
            ? (CourierGatewaySetting::SUPPORTED_COURIERS[$order->courier_code] ?? $order->courier_code)
            : 'STANDARD COURIER');

        $trackingBarcodeCode = $order->courier_tracking_code ?: $order->order_number;
        $trackingBarcodeSvg = $this->generateCode128Svg($trackingBarcodeCode);
        $orderBarcodeSvg = $this->generateCode128Svg($order->order_number);

        $isCod = $order->payment_method === 'cash_on_delivery' && (float) $order->payable_cod_amount_bdt > 0;
        $codAmountFormatted = number_format((float) $order->payable_cod_amount_bdt, 2);

        $itemsRows = '';
        foreach ($order->items()->take(4)->get() as $item) {
            $name = htmlspecialchars(substr($item->product_name ?? 'Product Item', 0, 28));
            $sku = htmlspecialchars($item->sku ?? '');
            $qty = $item->quantity;
            $itemsRows .= "<tr><td>{$name}<br><span style='color:#64748b;'>{$sku}</span></td><td style='text-align:right;'>x{$qty}</td></tr>";
        }

        $customerName = htmlspecialchars($order->customer_name);
        $customerPhone = htmlspecialchars($order->customer_phone);
        $deliveryAddress = htmlspecialchars($order->delivery_address);
        $districtName = htmlspecialchars($order->district?->name_en ?? 'Bangladesh');
        $zoneLabel = htmlspecialchars(strtoupper(str_replace('_', ' ', $order->delivery_zone)));

        $paymentBanner = $isCod
            ? <<<HTML
            <div class="cod-banner">
                <div class="cod-label">Cash on Delivery (COD) Amount</div>
                <div class="cod-amount">৳ {$codAmountFormatted}</div>
            </div>
HTML
            : <<<'HTML'
            <div class="prepaid-banner">
                <div class="prepaid-text">✓ PREPAID - DO NOT COLLECT CASH</div>
            </div>
HTML;

        return <<<HTML
    <div class="page {$breakClass}">
        <div>
            <!-- Header -->
            <div class="header">
                <div>
                    <div class="merchant-title">E-Commerce BD</div>
                    <div class="merchant-sub">Direct Nationwide Dispatch</div>
                </div>
                <div class="courier-badge">{$courierName}</div>
            </div>

            <!-- Primary Tracking Barcode -->
            <div class="barcode-section">
                {$trackingBarcodeSvg}
                <div class="barcode-text">{$trackingBarcodeCode}</div>
            </div>

            <!-- Deliver To Section -->
            <div class="section-box">
                <div class="section-title">DELIVER TO (RECIPIENT)</div>
                <div class="recipient-name">{$customerName}</div>
                <div class="recipient-phone">📞 {$customerPhone}</div>
                <div class="recipient-address">{$deliveryAddress}</div>
                <div>
                    <span class="zone-pill">{$districtName}</span>
                    <span class="zone-pill">{$zoneLabel}</span>
                </div>
            </div>

            <!-- Payment Collection Banner -->
            {$paymentBanner}

            <!-- Items Brief -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item Details</th>
                        <th style="text-align:right;">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    {$itemsRows}
                </tbody>
            </table>
        </div>

        <!-- Footer & Order Barcode -->
        <div>
            <div style="text-align: center; margin-bottom: 2px;">
                {$orderBarcodeSvg}
                <div style="font-size: 8px; font-family: monospace;">Order: {$order->order_number}</div>
            </div>
            <div class="footer">
                <div>Routing: Hub-to-Door</div>
                <div>Date: {$order->created_at->format('Y-m-d H:i')}</div>
            </div>
        </div>
    </div>
HTML;
    }

    /**
     * Pure PHP Code128-B SVG Barcode Generator.
     */
    public function generateCode128Svg(string $text, int $barHeight = 35): string
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
