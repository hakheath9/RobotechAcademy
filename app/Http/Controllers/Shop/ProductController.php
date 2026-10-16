<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    // ── Product listing with filter/sort/search ────────────
    public function index(Request $request)
    {
        $query = Product::with(['category', 'brand'])
            ->where('is_active', true);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('short_description', 'like', "%{$search}%")
                  ->orWhereHas('brand', fn($b) => $b->where('name', 'like', "%{$search}%"));
            });
        }

        // Filters
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }
        if ($request->boolean('on_sale')) {
            $query->whereNotNull('sale_price');
        }
        if ($request->boolean('in_stock')) {
            $query->where('stock', '>', 0);
        }

        // Sort
        match ($request->sort) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'popular'    => $query->orderBy('views', 'desc'),
            'name_asc'   => $query->orderBy('name', 'asc'),
            default      => $query->latest(),
        };

        $products   = $query->paginate(12)->withQueryString();
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();
        $brands     = Brand::where('is_active', true)->orderBy('name')->get();

        return view('shop.products', compact('products', 'categories', 'brands'));
    }

    // ── Single product detail ──────────────────────────────
    public function show(string $slug)
    {
        $product = Product::with([
            'category',
            'brand',
            'images',
            'reviews' => fn($q) => $q->with('user')->latest(),
        ])
        ->where('slug', $slug)
        ->where('is_active', true)
        ->firstOrFail();

        // Increment view counter
        $product->increment('views');

        // Related products (same category)
        $related = Product::with(['brand'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->take(4)
            ->get();

        // Current user's review (if any)
        $userReview = null;
        if (Auth::check()) {
            $userReview = Review::where('user_id', Auth::id())
                ->where('product_id', $product->id)
                ->first();
        }

        return view('shop.product-detail', compact('product', 'related', 'userReview'));
    }

    // ── Submit or update a review ──────────────────────────
    public function review(Request $request, int $id)
    {
        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        Review::updateOrCreate(
            ['user_id' => Auth::id(), 'product_id' => $id],
            [
                'rating'      => $request->rating,
                'comment'     => $request->comment,
                'is_approved' => false,
            ]
        );

        return back()->with('success', 'Review submitted! It will appear after approval.');
    }
}