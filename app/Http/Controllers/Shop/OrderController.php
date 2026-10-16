<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Mail\AdminNewOrderMail;
use App\Mail\OrderConfirmationMail;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    // ── Show checkout page ─────────────────────────────────
    public function checkout()
    {
        $cartItems = CartItem::with('product')
            ->where('user_id', Auth::id())
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart')->with('error',
                app()->getLocale()=='km' ? 'កន្ត្រកទទេ!' : 'Your cart is empty!'
            );
        }

        $subtotal = $cartItems->sum(fn($i) => $i->product->effective_price * $i->quantity);
        $coupon   = null;
        $discount = 0;
        $total    = $subtotal;

        // Load coupon from session
        if (session('coupon_code')) {
            $coupon = Coupon::where('code', session('coupon_code'))
                ->where('is_active', true)
                ->where(function($q) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
                })
                ->first();

            if ($coupon) {
                $discount = $coupon->type === 'percent'
                    ? ($subtotal * $coupon->value / 100)
                    : $coupon->value;
                $discount = min($discount, $subtotal);
                $total    = max(0, $subtotal - $discount);
            } else {
                session()->forget('coupon_code');
            }
        }

        return view('orders.checkout', compact('cartItems', 'subtotal', 'coupon', 'discount', 'total'));
    }

    // ── Place order ────────────────────────────────────────
    public function placeOrder(Request $request)
    {
        $request->validate([
            'shipping_address' => 'required|string|max:500',
            'payment_method'   => 'required|in:COD,Card,BankTransfer,KHQR,ABA',
            'phone'            => 'required|string|max:20',
        ], [
            'shipping_address.required' => app()->getLocale()=='km' ? 'សូមបញ្ចូលអាសយដ្ឋាន' : 'Address is required.',
            'payment_method.required'   => app()->getLocale()=='km' ? 'សូមជ្រើស វិធីទូទាត់' : 'Please select payment method.',
            'phone.required'            => app()->getLocale()=='km' ? 'សូមបញ្ចូលលេខទូរស័ព្ទ' : 'Phone is required.',
        ]);

        $cartItems = CartItem::with('product')
            ->where('user_id', Auth::id())
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart')->with('error',
                app()->getLocale()=='km' ? 'កន្ត្រកទទេ!' : 'Your cart is empty!'
            );
        }

        DB::beginTransaction();
        try {
            // Calculate totals
            $subtotal = $cartItems->sum(fn($i) => $i->product->effective_price * $i->quantity);
            $discount = 0;
            $coupon   = null;

            // Apply coupon from session
            if (session('coupon_code')) {
                $coupon = Coupon::where('code', session('coupon_code'))
                    ->where('is_active', true)
                    ->where(function($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    })
                    ->first();

                if ($coupon) {
                    $discount = $coupon->type === 'percent'
                        ? ($subtotal * $coupon->value / 100)
                        : $coupon->value;
                    $discount = min($discount, $subtotal);
                }
            }

            $total = max(0, $subtotal - $discount);

            // Create order
            $order = Order::create([
                'user_id'          => Auth::id(),
                'status'           => 'Pending',
                'payment_method'   => $request->payment_method,
                'payment_status'   => 'Unpaid',
                'shipping_address' => $request->shipping_address,
                'phone'            => $request->phone,
                'subtotal'         => $subtotal,
                'discount'         => $discount,
                'total_amount'     => $total,
                'notes'            => $request->notes,
            ]);

            // Create order items
            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $item->product_id,
                    'product_name' => $item->product->name,
                    'quantity'     => $item->quantity,
                    'unit_price'   => $item->product->effective_price,
                    'subtotal'     => $item->product->effective_price * $item->quantity,
                ]);

                // Decrease stock
                $item->product->decrement('stock', $item->quantity);
            }

            // Create payment record
            Payment::create([
                'order_id'   => $order->id,
                'user_id'    => Auth::id(),
                'amount'     => $total,
                'method'     => $request->payment_method,
                'status'     => 'Pending',
            ]);

            // Clear cart + coupon session
            CartItem::where('user_id', Auth::id())->delete();
            session()->forget('coupon_code');

            DB::commit();

            // Send Emails
            $order->load(['user', 'orderItems']);

            // 1. Confirmation to customer
            try {
                Mail::to($order->user->email)
                    ->send(new OrderConfirmationMail($order));
            } catch (\Exception $e) {
                Log::error('Order confirmation email failed: ' . $e->getMessage());
            }

            // 2. Alert to Admins
            try {
                $admins = User::where('role', 'Admin')->get();
                foreach ($admins as $admin) {
                    Mail::to($admin->email)->send(new AdminNewOrderMail($order));
                }
            } catch (\Exception $e) {
                Log::error('Admin new order email failed: ' . $e->getMessage());
            }

            

            // Redirect based on payment method
            if ($request->payment_method === 'ABA') {
                return redirect()->route('payment.aba', $order->id);
            }
            if ($request->payment_method === 'KHQR') {
                return redirect()->route('payment.khqr', $order->id);
            }
            if ($request->payment_method === 'BankTransfer') {
                return redirect()->route('payment.bank', $order->id);
            }

            // COD → success
            return redirect()->route('order.success', $order->id)
                ->with('success', app()->getLocale()=='km'
                    ? 'ការបញ្ជាទិញបានជោគជ័យ!'
                    : 'Order placed successfully!'
                );

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order failed: ' . $e->getMessage());
            return back()->with('error',
                app()->getLocale()=='km' ? 'មានបញ្ហា! សូមព្យាយាមម្តងទៀត' : 'Something went wrong! Please try again.'
            );
        }
    }

    // ── Order success page ────────────────────────────────
    public function success(int $orderId)
    {
        $order = Order::with(['orderItems', 'user'])
            ->where('id', $orderId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return view('orders.order-success', compact('order'));
    }

    // ── My orders ─────────────────────────────────────────
    public function myOrders()
    {
        $orders = Order::with('orderItems')
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(10);

        return view('orders.my-orders', compact('orders'));
    }

    // ── Order detail ──────────────────────────────────────
    public function detail(int $id)
    {
        $order = Order::with(['orderItems.product', 'user'])
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return view('orders.detail', compact('order'));
    }
}
