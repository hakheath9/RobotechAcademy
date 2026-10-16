<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    // ── View wishlist ──────────────────────────────────────
    public function index()
    {
        $wishlists = Wishlist::with(['product.brand', 'product.category'])
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('wishlist.index', compact('wishlists'));
    }

    // ── Toggle product in wishlist (add/remove) ────────────
    public function toggle(int $productId)
    {
        $existing = Wishlist::where('user_id', Auth::id())
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            $existing->delete();
            $message = 'Removed from wishlist.';
        } else {
            Wishlist::create([
                'user_id'    => Auth::id(),
                'product_id' => $productId,
            ]);
            $message = '❤️ Added to wishlist!';
        }

        return back()->with('success', $message);
    }

    // ── Remove single item ─────────────────────────────────
    public function remove(int $id)
    {
        Wishlist::where('user_id', Auth::id())->findOrFail($id)->delete();
        return back()->with('success', 'Removed from wishlist.');
    }

    // ── Move wishlist item to cart ─────────────────────────
    public function moveToCart(int $productId)
    {
        $wishItem = Wishlist::where('user_id', Auth::id())
            ->where('product_id', $productId)
            ->firstOrFail();

        $cartItem = \App\Models\CartItem::firstOrCreate(
            ['user_id' => Auth::id(), 'product_id' => $productId],
            ['quantity' => 0]
        );
        $cartItem->increment('quantity', 1);

        $wishItem->delete();

        return redirect()->route('cart')->with('success', 'Item moved to cart!');
    }
}