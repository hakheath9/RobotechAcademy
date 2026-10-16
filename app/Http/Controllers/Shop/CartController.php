<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    // ── View Cart ─────────────────────────────────────────
    public function index()
    {
        $cartItems = CartItem::where('user_id', Auth::id())
            ->with('product.brand')
            ->get();

        $subtotal = $cartItems->sum(fn($item) => $item->product->effective_price * $item->quantity);
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

        return view('cart.index', compact('cartItems', 'subtotal', 'coupon', 'discount', 'total'));
    }

    // ── Add to Cart ───────────────────────────────────────
    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'integer|min:1',
        ]);

        $product  = Product::findOrFail($request->product_id);
        $quantity = $request->quantity ?? 1;

        if ($product->stock < $quantity) {
            return back()->with('error',
                app()->getLocale()=='km' ? 'ស្តុកមិនគ្រប់!' : 'Not enough stock!'
            );
        }

        $cartItem = CartItem::firstOrCreate(
            ['user_id' => Auth::id(), 'product_id' => $product->id],
            ['quantity' => 0]
        );

        $newQty = $cartItem->quantity + $quantity;

        if ($newQty > $product->stock) {
            return back()->with('error',
                app()->getLocale()=='km' ? 'ស្តុកមិនគ្រប់!' : 'Not enough stock!'
            );
        }

        $cartItem->update(['quantity' => $newQty]);

        return back()->with('success',
            app()->getLocale()=='km' ? 'បានបន្ថែមទៅកន្ត្រក!' : 'Added to cart!'
        );
    }

    // ── Update Quantity ───────────────────────────────────
    public function update(Request $request)
    {
        $request->validate([
            'cart_item_id' => 'required|exists:cart_items,id',
            'quantity'     => 'required|integer|min:1',
        ]);

        $cartItem = CartItem::where('id', $request->cart_item_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($request->quantity > $cartItem->product->stock) {
            return back()->with('error',
                app()->getLocale()=='km' ? 'ស្តុកមិនគ្រប់!' : 'Not enough stock!'
            );
        }

        $cartItem->update(['quantity' => $request->quantity]);

        return back()->with('success',
            app()->getLocale()=='km' ? 'បានធ្វើបច្ចុប្បន្នភាព!' : 'Cart updated!'
        );
    }

    // ── Remove Item ───────────────────────────────────────
    public function remove(int $id)
    {
        CartItem::where('id', $id)
            ->where('user_id', Auth::id())
            ->delete();

        return back()->with('success',
            app()->getLocale()=='km' ? 'បានលប់ចេញ!' : 'Item removed!'
        );
    }

    // ── Clear Cart ────────────────────────────────────────
    public function clear()
    {
        CartItem::where('user_id', Auth::id())->delete();
        session()->forget('coupon_code');

        return back()->with('success',
            app()->getLocale()=='km' ? 'កន្ត្រកត្រូវបានលប់!' : 'Cart cleared!'
        );
    }

    // ── Apply Coupon ──────────────────────────────────────
    public function applyCoupon(Request $request)
    {
        $code   = strtoupper(trim($request->code));

        $coupon = Coupon::where('code', $code)
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$coupon) {
            return back()->with('error',
                app()->getLocale()=='km'
                    ? 'គូប៉ុងមិនត្រឹមត្រូវ ឬ ផុតកំណត់!'
                    : 'Invalid or expired coupon code!'
            );
        }

        // Store in session
        session(['coupon_code' => $coupon->code]);

        return back()->with('success',
            app()->getLocale()=='km'
                ? "គូប៉ុង {$coupon->code} បានអនុវត្ត! បញ្ចុះ " . ($coupon->type === 'percent' ? $coupon->value . '%' : '$' . $coupon->value)
                : "Coupon {$coupon->code} applied! " . ($coupon->type === 'percent' ? $coupon->value . '% off' : '$' . $coupon->value . ' off')
        );
    }

    // ── Remove Coupon ─────────────────────────────────────
    public function removeCoupon()
    {
        session()->forget('coupon_code');

        return back()->with('success',
            app()->getLocale()=='km' ? 'គូប៉ុងត្រូវបានលុបចេញ!' : 'Coupon removed!'
        );
    }
}