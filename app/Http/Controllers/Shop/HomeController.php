<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        $banners = Banner::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->withCount(['products' => fn($q) => $q->where('is_active', true)])
            ->take(8)
            ->get();

        $featuredProducts = Product::with(['category', 'brand'])
            ->where('is_active', true)
            ->where('is_featured', true)
            ->take(8)
            ->get();

        $newArrivals = Product::with(['category', 'brand'])
            ->where('is_active', true)
            ->latest()
            ->take(8)
            ->get();

        $onSaleProducts = Product::with(['category', 'brand'])
            ->where('is_active', true)
            ->whereNotNull('sale_price')
            ->take(4)
            ->get();

        return view('shop.home', compact(
            'banners',
            'categories',
            'featuredProducts',
            'newArrivals',
            'onSaleProducts'
        ));
    }
}