<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\KhqrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    // ── ABA PayWay ────────────────────────────────────────
    public function showAba(int $orderId)
    {
        $order = Order::with(['user','orderItems'])
            ->where('id', $orderId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return view('payment.aba', compact('order'));
    }

    // ── ABA Success (mock for testing) ────────────────────
    public function abaSuccess(Request $request, int $orderId)
    {
        $order = Order::where('id', $orderId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $order->update([
            'payment_status' => 'Paid',
            'status'         => 'Processing',
        ]);

        Payment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'user_id'        => Auth::id(),
                'amount'         => $order->total_amount,
                'method'         => $order->payment_method,
                'status'         => 'Completed',
                'transaction_id' => 'TEST-' . strtoupper(uniqid()),
            ]
        );

        return redirect()->route('order.success', $order->id)
            ->with('success', app()->getLocale()=='km'
                ? 'ការទូទាត់បានជោគជ័យ! ✅'
                : 'Payment successful! ✅'
            );
    }

    // ── KHQR ─────────────────────────────────────────────
    public function showKhqr(int $orderId)
    {
        $order = Order::with(['user','orderItems'])
            ->where('id', $orderId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Try real KHQR API
        $qrData = [];
        try {
            $khqr   = new KhqrService();
            $qrData = $khqr->generateQR($order->total_amount, 'RA-' . $order->id);
        } catch (\Exception $e) {
            Log::warning('KHQR API failed: ' . $e->getMessage());
            // Will use mock QR in view
        }

        return view('payment.khqr', compact('order', 'qrData'));
    }

    // ── KHQR Check (polling) ───────────────────────────────
    public function checkKhqr(int $orderId, Request $request)
    {
        $order = Order::where('id', $orderId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // If already paid
        if ($order->payment_status === 'Paid') {
            return response()->json(['paid' => true]);
        }

        try {
            $khqr   = new KhqrService();
            $result = $khqr->checkPayment($request->md5);

            if (isset($result['data']['status']) && $result['data']['status'] === 'SUCCESS') {
                $order->update([
                    'payment_status' => 'Paid',
                    'status'         => 'Processing',
                ]);

                Payment::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'user_id'        => Auth::id(),
                        'amount'         => $order->total_amount,
                        'method'         => 'KHQR',
                        'status'         => 'Completed',
                        'transaction_id' => $result['data']['hash'] ?? null,
                    ]
                );

                return response()->json(['paid' => true]);
            }
        } catch (\Exception $e) {
            Log::warning('KHQR check failed: ' . $e->getMessage());
        }

        return response()->json(['paid' => false]);
    }

    // ── Bank Transfer ─────────────────────────────────────
    public function showBank(int $orderId)
    {
        $order = Order::with(['user', 'orderItems'])
            ->where('id', $orderId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return view('payment.bank', compact('order'));
    }

    // ── Upload Slip + Telegram Notify ─────────────────────
    public function bankSlip(Request $request, int $orderId)
    {
        $request->validate([
            'slip' => 'required|image|max:5120',
        ]);

        $order = Order::with(['user', 'orderItems'])
            ->where('id', $orderId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Save slip
        $path = $request->file('slip')->store('slips', 'public');

        // Update order
        $order->update([
            'payment_status' => 'Pending',
            'notes'          => 'Slip uploaded: ' . ($request->note ?? ''),
        ]);

        // Send Telegram notification with slip photo → personal + group
        try {
            $telegram = new \App\Services\TelegramService();
            $slipPath = storage_path('app/public/' . $path);
            $caption  = $telegram->slipMessage($order, $request->note ?? '');
            $telegram->sendPhotoAll($slipPath, $caption); // sends to personal + group
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Telegram slip failed: ' . $e->getMessage());
        }

        return redirect()->route('order.success', $order->id)
            ->with('success', app()->getLocale()=='km'
                ? 'Slip បានផ្ញើ! Admin នឹងបញ្ជាក់ក្នុងពេលឆាប់ៗ ✅'
                : 'Slip sent! Admin will confirm your order shortly ✅'
            );
    }
}