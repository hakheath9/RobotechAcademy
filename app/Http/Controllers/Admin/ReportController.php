<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // ── Report Page ───────────────────────────────────────
    public function index(Request $request)
    {
        if (!$request->has('type')) {
            return view('admin.report');
        }

        $type  = $request->type;
        $year  = (int) $request->get('year',  date('Y'));
        $month = (int) $request->get('month', date('n'));
        $day   = (int) $request->get('day',   date('d'));

        $query = Order::with(['user', 'orderItems'])
            ->whereNotIn('status', ['Cancelled']);

        if ($type === 'day') {
            $query->whereYear('created_at', $year)
                  ->whereMonth('created_at', $month)
                  ->whereDay('created_at', $day);
            $periodLabel = sprintf('%02d/%02d/%d', $day, $month, $year);
            $chartLabels = array_map(fn($h) => sprintf('%02d:00', $h), range(0, 23));
            $chartRevenue = $chartOrders = [];
            for ($h = 0; $h <= 23; $h++) {
                $hq = Order::whereYear('created_at', $year)->whereMonth('created_at', $month)->whereDay('created_at', $day)->whereRaw('HOUR(created_at) = ?', [$h])->whereNotIn('status', ['Cancelled']);
                $chartRevenue[] = (float) $hq->sum('total_amount');
                $chartOrders[]  = $hq->count();
            }
        } elseif ($type === 'month') {
            $query->whereYear('created_at', $year)->whereMonth('created_at', $month);
            $daysInMonth  = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $periodLabel  = date('F', mktime(0,0,0,$month,1,$year)).' '.$year;
            $chartLabels  = array_map(fn($d) => sprintf('%02d', $d), range(1, $daysInMonth));
            $chartRevenue = $chartOrders = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dq = Order::whereYear('created_at', $year)->whereMonth('created_at', $month)->whereDay('created_at', $d)->whereNotIn('status', ['Cancelled']);
                $chartRevenue[] = (float) $dq->sum('total_amount');
                $chartOrders[]  = $dq->count();
            }
        } else {
            $query->whereYear('created_at', $year);
            $periodLabel  = (string) $year;
            $chartLabels  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            $chartRevenue = $chartOrders = [];
            for ($m = 1; $m <= 12; $m++) {
                $mq = Order::whereYear('created_at', $year)->whereMonth('created_at', $m)->whereNotIn('status', ['Cancelled']);
                $chartRevenue[] = (float) $mq->sum('total_amount');
                $chartOrders[]  = $mq->count();
            }
        }

        $orders   = $query->latest()->get();
        $orderIds = $orders->pluck('id');
        $products = OrderItem::whereIn('order_id', $orderIds)
            ->select('product_id','product_name', DB::raw('SUM(quantity) as total_sold'), DB::raw('SUM(subtotal) as total_revenue'))
            ->groupBy('product_id','product_name')->orderByDesc('total_sold')->with('product.category')->get();

        $reportData = [
            'period_label'  => $periodLabel,
            'total_revenue' => $orders->sum('total_amount'),
            'total_orders'  => $orders->count(),
            'total_items'   => $products->sum('total_sold'),
            'avg_order'     => $orders->count() > 0 ? $orders->sum('total_amount') / $orders->count() : 0,
            'orders'        => $orders,
            'products'      => $products,
            'chart_labels'  => $chartLabels,
            'chart_revenue' => $chartRevenue,
            'chart_orders'  => $chartOrders,
        ];

        return view('admin.report', compact('reportData'));
    }

    // ── Export Excel ──────────────────────────────────────
    public function exportExcel(Request $request)
    {
        $type  = $request->get('type', 'month');
        $year  = (int) $request->get('year',  date('Y'));
        $month = (int) $request->get('month', date('n'));
        $day   = (int) $request->get('day',   date('d'));
        $isKm  = $request->get('locale', app()->getLocale()) == 'km';

        $query = Order::with(['user', 'orderItems'])
            ->whereNotIn('status', ['Cancelled']);

        if ($type === 'day') {
            $query->whereYear('created_at', $year)->whereMonth('created_at', $month)->whereDay('created_at', $day);
            $periodLabel = sprintf('%02d-%02d-%d', $day, $month, $year);
        } elseif ($type === 'month') {
            $query->whereYear('created_at', $year)->whereMonth('created_at', $month);
            $periodLabel = date('F', mktime(0,0,0,$month,1,$year)).'-'.$year;
        } else {
            $query->whereYear('created_at', $year);
            $periodLabel = (string) $year;
        }

        $orders   = $query->latest()->get();
        $orderIds = $orders->pluck('id');
        $products = OrderItem::whereIn('order_id', $orderIds)
            ->select('product_id','product_name', DB::raw('SUM(quantity) as total_sold'), DB::raw('SUM(subtotal) as total_revenue'))
            ->groupBy('product_id','product_name')->orderByDesc('total_sold')->get();

        $totalRevenue = $orders->sum('total_amount');
        $totalOrders  = $orders->count();
        $totalItems   = $products->sum('total_sold');
        $avgOrder     = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        $filename = "Report-{$periodLabel}.xls";

        $html = '
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <style>
        body    { font-family: Arial, sans-serif; font-size: 11pt; }
        h1      { font-size: 16pt; font-weight: bold; color: #111111; margin: 0 0 4px; }
        h2      { font-size: 12pt; font-weight: bold; color: #1d6f42; margin: 20px 0 6px; background: #f0fdf4; padding: 6px 10px; }
        p       { font-size: 10pt; color: #6b7280; margin: 2px 0; }
        table   { border-collapse: collapse; width: 100%; margin-bottom: 10px; }

        /* Summary table */
        .tbl-summary th { background: #1d6f42; color: #ffffff; font-size: 11pt; font-weight: bold; padding: 8px 14px; border: 1px solid #166534; text-align: center; }
        .tbl-summary td { font-size: 13pt; font-weight: bold; padding: 10px 14px; border: 1px solid #d1fae5; text-align: center; background: #f0fdf4; }

        /* Products table */
        .tbl-products th { background: #1d6f42; color: #fff; font-size: 10pt; font-weight: bold; padding: 7px 12px; border: 1px solid #166534; }
        .tbl-products td { padding: 7px 12px; border: 1px solid #e5e7eb; font-size: 10pt; vertical-align: middle; }
        .tbl-products tr:nth-child(even) td { background: #f0fdf4; }
        .tbl-products tr:hover td { background: #dcfce7; }

        /* Orders table */
        .tbl-orders th { background: #111111; color: #ffffff; font-size: 10pt; font-weight: bold; padding: 7px 12px; border: 1px solid #374151; }
        .tbl-orders td { padding: 7px 12px; border: 1px solid #e5e7eb; font-size: 10pt; vertical-align: middle; }
        .tbl-orders tr:nth-child(even) td { background: #f9fafb; }

        .gold   { color: #b45309; font-weight: bold; }
        .green  { color: #065f46; font-weight: bold; }
        .center { text-align: center; }
        .right  { text-align: right; }
        .badge-pending    { background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; }
        .badge-delivered  { background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 4px; }
        .badge-cancelled  { background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 4px; }
        .badge-shipped    { background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 4px; }
        .badge-processing { background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>🤖 ROBOTECH Academy</h1>
    <p>'.($isKm ? 'របាយការណ៍' : 'Report').': <strong>'.$periodLabel.'</strong></p>
    <p>'.($isKm ? 'បង្កើតនៅ' : 'Generated').': <strong>'.now()->format('M d, Y — h:i A').'</strong></p>

    <h2>📊 '.($isKm ? 'សង្ខេប' : 'Summary').'</h2>
    <table class="tbl-summary">
        <thead>
            <tr>
                <th>'.($isKm ? 'ចំណូលសរុប' : 'Total Revenue').'</th>
                <th>'.($isKm ? 'ការបញ្ជាទិញ' : 'Total Orders').'</th>
                <th>'.($isKm ? 'ផលិតផលបានលក់' : 'Products Sold').'</th>
                <th>'.($isKm ? 'មធ្យម' : 'Avg Order Value').'</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="gold">$'.number_format($totalRevenue, 2).'</td>
                <td class="center">'.$totalOrders.'</td>
                <td class="center">'.$totalItems.'</td>
                <td class="gold">$'.number_format($avgOrder, 2).'</td>
            </tr>
        </tbody>
    </table>

    <h2>📦 '.($isKm ? 'ផលិតផលបានលក់' : 'Products Sold').'</h2>
    <table class="tbl-products">
        <thead>
            <tr>
                <th class="center">#</th>
                <th>'.($isKm ? 'ឈ្មោះផលិតផល' : 'Product Name').'</th>
                <th class="center">'.($isKm ? 'បរិមាណបានលក់' : 'Units Sold').'</th>
                <th class="right">'.($isKm ? 'ចំណូលសរុប' : 'Total Revenue').'</th>
                <th class="right">'.($isKm ? 'តម្លៃមធ្យម' : 'Avg Price').'</th>
            </tr>
        </thead>
        <tbody>';

        if ($products->isEmpty()) {
            $html .= '<tr><td colspan="5" class="center" style="color:#9ca3af;padding:16px;">'.($isKm ? 'មិនមានទិន្នន័យ' : 'No products sold in this period').'</td></tr>';
        } else {
            foreach ($products as $i => $p) {
                $html .= '<tr>
                    <td class="center">'.($i + 1).'</td>
                    <td>'.htmlspecialchars($p->product_name).'</td>
                    <td class="center"><strong>'.number_format($p->total_sold).'</strong></td>
                    <td class="right gold">$'.number_format($p->total_revenue, 2).'</td>
                    <td class="right">$'.number_format($p->total_revenue / max($p->total_sold, 1), 2).'</td>
                </tr>';
            }
        }

        $html .= '
        </tbody>
    </table>

    <h2>🛒 '.($isKm ? 'បញ្ជីការបញ្ជាទិញ' : 'Orders List').' ('.$totalOrders.')</h2>
    <table class="tbl-orders">
        <thead>
            <tr>
                <th>'.($isKm ? 'លេខ' : 'Order No.').'</th>
                <th>'.($isKm ? 'អតិថិជន' : 'Customer').'</th>
                <th>'.($isKm ? 'អ៊ីមែល' : 'Email').'</th>
                <th>'.($isKm ? 'លេខទូរស័ព្ទ' : 'Phone').'</th>
                <th>'.($isKm ? 'កាលបរិច្ឆេទ' : 'Date').'</th>
                <th class="right">'.($isKm ? 'សរុប' : 'Total').'</th>
                <th class="center">'.($isKm ? 'ការទូទាត់' : 'Payment').'</th>
                <th class="center">'.($isKm ? 'ស្ថានភាព' : 'Status').'</th>
            </tr>
        </thead>
        <tbody>';

        if ($orders->isEmpty()) {
            $html .= '<tr><td colspan="8" class="center" style="color:#9ca3af;padding:16px;">'.($isKm ? 'មិនមានការបញ្ជាទិញ' : 'No orders in this period').'</td></tr>';
        } else {
            $statusBadge = [
                'Pending'    => 'badge-pending',
                'Processing' => 'badge-processing',
                'Shipped'    => 'badge-shipped',
                'Delivered'  => 'badge-delivered',
                'Cancelled'  => 'badge-cancelled',
            ];
            foreach ($orders as $order) {
                $badge = $statusBadge[$order->status] ?? '';
                $html .= '<tr>
                    <td><strong>RA-'.$order->id.'</strong></td>
                    <td>'.htmlspecialchars($order->user->name ?? '—').'</td>
                    <td>'.htmlspecialchars($order->user->email ?? '—').'</td>
                    <td>'.htmlspecialchars($order->user->phone ?? '—').'</td>
                    <td>'.$order->created_at->format('M d, Y').'</td>
                    <td class="right gold">$'.number_format($order->total_amount, 2).'</td>
                    <td class="center">'.$order->payment_method.'</td>
                    <td class="center"><span class="'.$badge.'">'.$order->status.'</span></td>
                </tr>';
            }
        }

        $html .= '
        </tbody>
    </table>

    <p style="margin-top:20px;font-size:9pt;color:#9ca3af;border-top:1px solid #e5e7eb;padding-top:8px;">
        © '.date('Y').' ROBOTECH Academy — '.($isKm ? 'របាយការណ៍ត្រូវបានបង្កើតដោយប្រព័ន្ធ' : 'Report generated automatically by system').'
    </p>
</body>
</html>';

        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}