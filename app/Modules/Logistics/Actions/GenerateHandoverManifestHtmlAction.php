<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Actions;

use App\Modules\Order\Models\Order;
use Carbon\CarbonImmutable;

final class GenerateHandoverManifestHtmlAction
{
    /**
     * Generate printable HTML Courier Handover Manifest / Pickup Challan.
     *
     * @param  array<Order>  $orders
     */
    public function __invoke(array $orders, ?string $courierCode = null): string
    {
        $now = CarbonImmutable::now();
        $dateFormatted = $now->format('d M Y, h:i A');
        $manifestNo = 'MAN-'.$now->format('ymd').'-'.strtoupper(substr(md5(uniqid('', true)), 0, 5));
        $courierName = $courierCode ? strtoupper($courierCode) : 'COURIER DISPATCH';

        $totalParcels = count($orders);
        $totalCodBdt = 0.0;

        $rowsHtml = '';
        foreach ($orders as $index => $order) {
            $sl = $index + 1;
            $codAmount = (float) $order->payable_cod_amount_bdt;
            $totalCodBdt += $codAmount;

            $trackingOrOrder = $order->courier_tracking_code ?: ($order->courier_consignment_id ?: $order->order_number);
            $barcodeSvg = $this->generateCode128Svg($trackingOrOrder, 24);

            $custName = htmlspecialchars($order->customer_name ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $custPhone = htmlspecialchars($order->customer_phone ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $district = htmlspecialchars($order->district?->name_en ?? ($order->delivery_zone === 'inside_dhaka' ? 'Dhaka' : 'Outside Dhaka'), ENT_QUOTES, 'UTF-8');
            $courierBadge = htmlspecialchars(strtoupper($order->courier_code ?: ($courierCode ?: 'N/A')), ENT_QUOTES, 'UTF-8');

            $itemsQty = $order->items->sum('quantity');

            $rowsHtml .= <<<HTML
            <tr>
                <td class="text-center font-bold">{$sl}</td>
                <td>
                    <div class="font-bold font-mono">{$order->order_number}</div>
                    <div class="text-xs text-muted">{$itemsQty} item(s)</div>
                </td>
                <td class="text-center">
                    <div class="barcode-wrap">
                        {$barcodeSvg}
                        <div class="barcode-sub font-mono">{$trackingOrOrder}</div>
                    </div>
                </td>
                <td>
                    <div class="font-bold">{$custName}</div>
                    <div class="font-mono text-xs">📞 {$custPhone}</div>
                </td>
                <td>
                    <div>{$district}</div>
                    <span class="zone-badge">{$order->delivery_zone}</span>
                </td>
                <td class="text-center">
                    <span class="courier-badge">{$courierBadge}</span>
                </td>
                <td class="text-right font-bold font-mono">
                    ৳{$this->formatMoney($codAmount)}
                </td>
                <td class="text-center">
                    <span class="check-box">☐</span>
                </td>
            </tr>
HTML;
        }

        $formattedTotalCod = $this->formatMoney($totalCodBdt);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courier Handover Manifest - {$manifestNo}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            padding: 24px;
            font-size: 13px;
            line-height: 1.4;
        }
        .manifest-container {
            max-width: 960px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 28px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 20px;
            border-bottom: 2px solid #0f172a;
            margin-bottom: 20px;
        }
        .company-info h1 {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #0f172a;
        }
        .company-info p {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }
        .manifest-meta {
            text-align: right;
        }
        .manifest-title {
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #2563eb;
            background: #eff6ff;
            padding: 4px 10px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 4px;
        }
        .meta-line {
            font-size: 12px;
            color: #475569;
        }
        .meta-line strong {
            color: #0f172a;
        }
        .summary-cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .summary-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 16px;
        }
        .summary-card.highlight {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }
        .summary-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
        }
        .summary-value {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 2px;
        }
        .summary-card.highlight .summary-value {
            color: #15803d;
        }
        table.manifest-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        table.manifest-table th {
            background: #0f172a;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 10px;
            border: 1px solid #0f172a;
        }
        table.manifest-table td {
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            font-size: 12px;
            vertical-align: middle;
        }
        table.manifest-table tr:nth-child(even) td {
            background: #f8fafc;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: 700; }
        .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .text-xs { font-size: 11px; }
        .text-muted { color: #64748b; }
        .barcode-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .barcode-sub {
            font-size: 10px;
            font-weight: 700;
            color: #334155;
            margin-top: 1px;
        }
        .courier-badge {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            font-size: 10px;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .zone-badge {
            display: inline-block;
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
        }
        .check-box {
            font-size: 16px;
            color: #94a3b8;
        }
        .signatures-area {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            padding-top: 24px;
            border-top: 2px dashed #cbd5e1;
            margin-top: 20px;
        }
        .sig-block {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px;
            background: #f8fafc;
        }
        .sig-title {
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            color: #334155;
            margin-bottom: 30px;
        }
        .sig-line {
            border-top: 1px solid #64748b;
            margin-top: 30px;
            padding-top: 6px;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #475569;
        }
        .print-btn-bar {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-print {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
        }
        @media print {
            body { background: #ffffff !important; padding: 0; }
            .manifest-container { border: none; box-shadow: none; padding: 0; width: 100%; max-width: 100%; }
            .print-btn-bar { display: none; }
            table.manifest-table th { background: transparent !important; color: #000000 !important; border: 1.5px solid #000000 !important; }
            table.manifest-table tr:nth-child(even) td { background: transparent !important; }
            .summary-card, .sig-block { background: transparent !important; border: 1px solid #000000 !important; }
            .courier-badge, .zone-badge, .manifest-title { background: transparent !important; color: #000000 !important; border: 1px solid #000000 !important; }
            * { -webkit-print-color-adjust: economy; print-color-adjust: economy; }
            @page { margin: 10mm; }
        }
    </style>
</head>
<body>
    <div class="print-btn-bar">
        <button class="btn-print" onclick="window.print()">🖨️ Print Pickup Challan / Manifest (Ctrl + P)</button>
    </div>

    <div class="manifest-container">
        <!-- Header -->
        <div class="header-row">
            <div class="company-info">
                <h1>COMMERCIAL DISPATCH MANIFEST</h1>
                <p>E-Commerce Hub Warehouse • Courier Consignment Handover Slip</p>
            </div>
            <div class="manifest-meta">
                <div class="manifest-title">🚚 {$courierName} PICKUP</div>
                <div class="meta-line">Manifest #: <strong class="font-mono">{$manifestNo}</strong></div>
                <div class="meta-line">Date & Time: <strong>{$dateFormatted}</strong></div>
            </div>
        </div>

        <!-- Summary KPIs -->
        <div class="summary-cards-grid">
            <div class="summary-card">
                <div class="summary-label">Total Parcels Handed Over</div>
                <div class="summary-value">{$totalParcels} Package(s)</div>
            </div>
            <div class="summary-card highlight">
                <div class="summary-label">Total COD Cash to Collect</div>
                <div class="summary-value">৳{$formattedTotalCod}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Courier Partner</div>
                <div class="summary-value">{$courierName}</div>
            </div>
        </div>

        <!-- Orders Manifest Table -->
        <table class="manifest-table">
            <thead>
                <tr>
                    <th style="width: 35px;">SL</th>
                    <th style="width: 110px;">Order #</th>
                    <th style="width: 180px;">Tracking / Barcode</th>
                    <th>Customer Name & Phone</th>
                    <th style="width: 110px;">Destination</th>
                    <th style="width: 80px;">Courier</th>
                    <th style="width: 100px;">COD (৳)</th>
                    <th style="width: 40px;">Rcv</th>
                </tr>
            </thead>
            <tbody>
                {$rowsHtml}
            </tbody>
            <tfoot>
                <tr style="background: #f1f5f9; font-weight: 800;">
                    <td colspan="6" class="text-right" style="padding: 10px; font-size: 13px;">
                        TOTAL CONSIGNMENT VALUE (COD):
                    </td>
                    <td class="text-right font-mono" style="padding: 10px; font-size: 14px; color: #15803d;">
                        ৳{$formattedTotalCod}
                    </td>
                    <td class="text-center font-bold">{$totalParcels}</td>
                </tr>
            </tfoot>
        </table>

        <!-- Signatures & Declarations -->
        <div class="signatures-area">
            <div class="sig-block">
                <div class="sig-title">Warehouse Dispatch Officer</div>
                <div style="font-size: 11px; color: #64748b;">
                    I certify that {$totalParcels} parcel(s) with total COD collectible amount of ৳{$formattedTotalCod} have been inspected, packed, and handed over in sealed condition.
                </div>
                <div class="sig-line">
                    <span>Officer Signature</span>
                    <span>Date: {$now->format('d/m/Y')}</span>
                </div>
            </div>

            <div class="sig-block">
                <div class="sig-title">Courier Pickup Rider / Driver</div>
                <div style="font-size: 11px; color: #64748b;">
                    I hereby acknowledge receipt of {$totalParcels} intact package(s) from merchant for onward delivery under {$courierName}.
                </div>
                <div class="sig-line">
                    <span>Rider Signature & Mobile No.</span>
                    <span>Time: {$now->format('h:i A')}</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }

    /**
     * Pure PHP Code128-B SVG Barcode Generator.
     */
    private function generateCode128Svg(string $text, int $barHeight = 24): string
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

        $checksum = $sum % 103;
        $chars[] = $checksum;
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
        $modules .= '2331112';

        $unitWidth = 1.2;
        $totalWidth = strlen($modules) * $unitWidth;

        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$totalWidth}\" height=\"{$barHeight}\" viewBox=\"0 0 {$totalWidth} {$barHeight}\">";
        $svg .= '<rect width="100%" height="100%" fill="#ffffff"/>';

        $x = 0.0;
        for ($k = 0; $k < strlen($modules); $k++) {
            if ($modules[$k] === '1') {
                $svg .= "<rect x=\"{$x}\" y=\"0\" width=\"{$unitWidth}\" height=\"{$barHeight}\" fill=\"#000000\"/>";
            }
            $x += $unitWidth;
        }

        $svg .= '</svg>';

        return $svg;
    }
}
