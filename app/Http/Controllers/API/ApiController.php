<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Review;
use App\Models\StockHistory;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiController extends Controller
{
    // ── Response helpers ───────────────────────────────────
    private function ok($data, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json(['status' => 'success', 'message' => $message, 'data' => $data], $code);
    }

    private function fail(string $message, int $code = 400): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => $message], $code);
    }

    // ════════════════════════════════════════════════════════
    //  AUTH
    // ════════════════════════════════════════════════════════

    /**
     * POST /api/login
     * Body: email, password
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->fail('Invalid email or password.', 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return $this->ok([
            'token' => $token,
            'user'  => $user,
            'role'  => $user->role,
        ]);
    }

    /**
     * POST /api/register
     * Body: name, email, password, role(optional)
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role'     => 'nullable|in:Admin,Customer',
            'phone'    => 'nullable|string|max:20',
        ]);

        $user  = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role ?? 'Customer',
            'phone'    => $request->phone,
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return $this->ok(['token' => $token, 'user' => $user], 'Registered successfully.', 201);
    }

    /**
     * POST /api/logout   [auth:sanctum]
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->ok(null, 'Logged out successfully.');
    }

    /**
     * GET /api/me   [auth:sanctum]
     */
    public function me(Request $request): JsonResponse
    {
        return $this->ok($request->user()->loadCount(['orders', 'wishlists']));
    }

    // ════════════════════════════════════════════════════════
    //  PRODUCTS  (public)
    // ════════════════════════════════════════════════════════

    /**
     * GET /api/products?search=&category_id=&brand_id=&featured=1&on_sale=1&sort=price_asc
     */
    public function products(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'brand'])->where('is_active', true);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%{$s}%")
                                      ->orWhere('short_description', 'like', "%{$s}%"));
        }
        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);
        if ($request->filled('brand_id'))    $query->where('brand_id',    $request->brand_id);
        if ($request->boolean('featured'))   $query->where('is_featured', true);
        if ($request->boolean('on_sale'))    $query->whereNotNull('sale_price');

        match ($request->sort) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'popular'    => $query->orderBy('views', 'desc'),
            'name_asc'   => $query->orderBy('name',  'asc'),
            default      => $query->latest(),
        };

        return $this->ok($query->paginate(12));
    }

    /**
     * GET /api/products/{id}
     */
    public function product(int $id): JsonResponse
    {
        $p = Product::with(['category', 'brand', 'images', 'reviews.user'])->findOrFail($id);
        $p->increment('views');

        return $this->ok(array_merge($p->toArray(), [
            'average_rating'   => $p->average_rating,
            'review_count'     => $p->review_count,
            'is_on_sale'       => $p->is_on_sale,
            'discount_percent' => $p->discount_percent,
            'stock_status'     => $p->stock_status,
            'effective_price'  => $p->effective_price,
        ]));
    }

    // ════════════════════════════════════════════════════════
    //  CATEGORIES  (public)
    // ════════════════════════════════════════════════════════

    /**
     * GET /api/categories
     */
    public function categories(): JsonResponse
    {
        return $this->ok(
            Category::where('is_active', true)
                ->withCount(['products' => fn($q) => $q->where('is_active', true)])
                ->orderBy('sort_order')
                ->get()
        );
    }

    // ════════════════════════════════════════════════════════
    //  BRANDS  (public)
    // ════════════════════════════════════════════════════════

    /**
     * GET /api/brands
     */
    public function brands(): JsonResponse
    {
        return $this->ok(
            Brand::where('is_active', true)
                ->withCount('products')
                ->orderBy('name')
                ->get()
        );
    }

    // ════════════════════════════════════════════════════════
    //  BANNERS  (public)
    // ════════════════════════════════════════════════════════

    /**
     * GET /api/banners
     */
    public function banners(): JsonResponse
    {
        return $this->ok(
            Banner::where('is_active', true)->orderBy('sort_order')->get()
        );
    }

    // ════════════════════════════════════════════════════════
    //  CART  [auth:sanctum]
    // ════════════════════════════════════════════════════════

    /**
     * GET /api/cart
     */
    public function cart(Request $request): JsonResponse
    {
        $items    = CartItem::with('product.brand')->where('user_id', $request->user()->id)->get();
        $subtotal = $items->sum(fn($i) => $i->product->effective_price * $i->quantity);

        return $this->ok([
            'items'    => $items,
            'subtotal' => $subtotal,
            'count'    => $items->sum('quantity'),
        ]);
    }

    /**
     * POST /api/cart
     * Body: product_id, quantity
     */
    public function addToCart(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'integer|min:1|max:99',
        ]);

        $product = Product::findOrFail($request->product_id);
        if ($product->stock < 1) return $this->fail('Product is out of stock.', 422);

        $item = CartItem::firstOrCreate(
            ['user_id' => $request->user()->id, 'product_id' => $request->product_id],
            ['quantity' => 0]
        );

        $item->increment('quantity', $request->quantity ?? 1);

        return $this->ok($item->load('product'), 'Added to cart!');
    }

    /**
     * PUT /api/cart/{id}
     * Body: quantity
     */
    public function updateCart(Request $request, int $id): JsonResponse
    {
        $request->validate(['quantity' => 'required|integer|min:0']);

        $item = CartItem::where('user_id', $request->user()->id)->findOrFail($id);

        if ($request->quantity < 1) {
            $item->delete();
            return $this->ok(null, 'Item removed from cart.');
        }

        $item->update(['quantity' => $request->quantity]);
        return $this->ok($item);
    }

    /**
     * DELETE /api/cart/{id}
     */
    public function removeFromCart(Request $request, int $id): JsonResponse
    {
        CartItem::where('user_id', $request->user()->id)->findOrFail($id)->delete();
        return $this->ok(null, 'Removed from cart.');
    }

    // ════════════════════════════════════════════════════════
    //  WISHLIST  [auth:sanctum]
    // ════════════════════════════════════════════════════════

    /**
     * GET /api/wishlist
     */
    public function wishlist(Request $request): JsonResponse
    {
        return $this->ok(
            Wishlist::with('product.brand')->where('user_id', $request->user()->id)->get()
        );
    }

    /**
     * POST /api/wishlist
     * Body: product_id
     */
    public function toggleWishlist(Request $request): JsonResponse
    {
        $request->validate(['product_id' => 'required|exists:products,id']);

        $existing = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($existing) {
            $existing->delete();
            return $this->ok(null, 'Removed from wishlist.');
        }

        $wish = Wishlist::create([
            'user_id'    => $request->user()->id,
            'product_id' => $request->product_id,
        ]);

        return $this->ok($wish, 'Added to wishlist!');
    }

    // ════════════════════════════════════════════════════════
    //  COUPON  [auth:sanctum]
    // ════════════════════════════════════════════════════════

    /**
     * POST /api/coupon/apply
     * Body: code, subtotal
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'code'     => 'required|string',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('code', strtoupper(trim($request->code)))->first();

        if (!$coupon || !$coupon->isValid($request->subtotal)) {
            return $this->fail('Invalid or expired coupon code.', 422);
        }

        $discount = $coupon->calculateDiscount($request->subtotal);

        return $this->ok([
            'coupon'   => $coupon,
            'discount' => $discount,
            'total'    => $request->subtotal - $discount,
        ]);
    }

    // ════════════════════════════════════════════════════════
    //  ORDERS  [auth:sanctum]
    // ════════════════════════════════════════════════════════

    /**
     * GET /api/my-orders
     */
    public function myOrders(Request $request): JsonResponse
    {
        return $this->ok(
            Order::with('orderItems')
                ->where('user_id', $request->user()->id)
                ->latest()
                ->paginate(10)
        );
    }

    /**
     * GET /api/my-orders/{id}
     */
    public function orderDetail(Request $request, int $id): JsonResponse
    {
        $order = Order::with(['orderItems.product', 'coupon', 'payment'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return $this->ok($order);
    }

    /**
     * POST /api/orders
     * Body: shipping_address, payment_method, coupon_id(optional), note(optional)
     */
    public function placeOrder(Request $request): JsonResponse
    {
        $request->validate([
            'shipping_address' => 'required|string|max:500',
            'payment_method'   => 'required|in:COD,Card,Bank Transfer',
        ]);

        $cartItems = CartItem::with('product')
            ->where('user_id', $request->user()->id)
            ->get();

        if ($cartItems->isEmpty()) return $this->fail('Cart is empty.', 422);

        $subtotal = $cartItems->sum(fn($i) => $i->product->effective_price * $i->quantity);
        $coupon   = $request->filled('coupon_id') ? Coupon::find($request->coupon_id) : null;
        $discount = $coupon ? $coupon->calculateDiscount($subtotal) : 0;
        $total    = $subtotal - $discount;

        $order = null;

        DB::transaction(function () use ($request, $cartItems, $subtotal, $coupon, $discount, $total, &$order) {
            $order = Order::create([
                'user_id'          => $request->user()->id,
                'coupon_id'        => $coupon?->id,
                'subtotal'         => $subtotal,
                'discount'         => $discount,
                'shipping_fee'     => 0,
                'total_amount'     => $total,
                'status'           => 'Pending',
                'shipping_address' => $request->shipping_address,
                'payment_method'   => $request->payment_method,
                'payment_status'   => 'Unpaid',
                'note'             => $request->note,
            ]);

            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id'      => $order->id,
                    'product_id'    => $item->product_id,
                    'product_name'  => $item->product->name,
                    'product_image' => $item->product->image_url,
                    'quantity'      => $item->quantity,
                    'unit_price'    => $item->product->effective_price,
                    'subtotal'      => $item->product->effective_price * $item->quantity,
                ]);
                $item->product->decrement('stock', $item->quantity);
            }

            Payment::create([
                'order_id'       => $order->id,
                'user_id'        => $request->user()->id,
                'transaction_id' => 'TXN-' . strtoupper(uniqid()),
                'amount'         => $total,
                'method'         => $request->payment_method,
                'status'         => 'Pending',
            ]);

            if ($coupon) $coupon->increment('used_count');
            CartItem::where('user_id', $request->user()->id)->delete();
        });

        return $this->ok($order->load(['orderItems.product', 'payment']), 'Order placed!', 201);
    }

    // ════════════════════════════════════════════════════════
    //  REVIEWS  [auth:sanctum]
    // ════════════════════════════════════════════════════════

    /**
     * POST /api/products/{id}/review
     * Body: rating (1-5), comment(optional)
     */
    public function storeReview(Request $request, int $productId): JsonResponse
    {
        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = Review::updateOrCreate(
            ['user_id' => $request->user()->id, 'product_id' => $productId],
            ['rating'  => $request->rating, 'comment' => $request->comment, 'is_approved' => false]
        );

        return $this->ok($review, 'Review submitted! Pending approval.');
    }

    // ════════════════════════════════════════════════════════
    //  ADMIN — DASHBOARD  [auth:sanctum + admin]
    // ════════════════════════════════════════════════════════

    /**
     * GET /api/admin/dashboard
     */
    public function adminDashboard(): JsonResponse
    {
        return $this->ok([
            'total_categories' => Category::count(),
            'total_brands'     => Brand::count(),
            'total_products'   => Product::where('is_active', true)->count(),
            'total_revenue'    => Order::whereNotIn('status', ['Cancelled'])->sum('total_amount'),
            'total_orders'     => Order::count(),
            'total_users'      => User::where('role', 'Customer')->count(),
            'low_stock'        => Product::where('stock', '<', 10)->where('is_active', true)->count(),
            'pending_orders'   => Order::where('status', 'Pending')->count(),
            'pending_reviews'  => Review::where('is_approved', false)->count(),
            'recent_orders'    => Order::with('user')->latest()->take(5)->get(),
        ]);
    }

    // ════════════════════════════════════════════════════════
    //  ADMIN — PRODUCTS  [auth:sanctum + admin]
    // ════════════════════════════════════════════════════════

    /**
     * POST /api/admin/products
     */
    public function storeProduct(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => 'required|string|max:200',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
        ]);

        $imageUrl = '/images/placeholder.jpg';
        if ($request->hasFile('image')) {
            $path     = $request->file('image')->store('products', 'public');
            $imageUrl = '/storage/' . $path;
        }

        $product = Product::create([
            'category_id'       => $request->category_id,
            'brand_id'          => $request->brand_id,
            'name'              => $request->name,
            'slug'              => Str::slug($request->name) . '-' . uniqid(),
            'sku'               => 'RA-' . strtoupper(Str::random(6)),
            'description'       => $request->description,
            'short_description' => $request->short_description,
            'price'             => $request->price,
            'sale_price'        => $request->sale_price ?: null,
            'stock'             => $request->stock,
            'image_url'         => $imageUrl,
            'is_active'         => true,
            'is_featured'       => $request->boolean('is_featured'),
        ]);

        return $this->ok($product, 'Product created.', 201);
    }

    /**
     * PUT /api/admin/products/{id}
     */
    public function updateProduct(Request $request, int $id): JsonResponse
    {
        $product  = Product::findOrFail($id);
        $imageUrl = $product->image_url;

        if ($request->hasFile('image')) {
            $path     = $request->file('image')->store('products', 'public');
            $imageUrl = '/storage/' . $path;
        }

        $product->update([
            'category_id'       => $request->category_id ?? $product->category_id,
            'brand_id'          => $request->brand_id,
            'name'              => $request->name ?? $product->name,
            'description'       => $request->description,
            'short_description' => $request->short_description,
            'price'             => $request->price ?? $product->price,
            'sale_price'        => $request->sale_price ?: null,
            'stock'             => $request->stock ?? $product->stock,
            'image_url'         => $imageUrl,
            'is_featured'       => $request->boolean('is_featured'),
            'is_active'         => $request->boolean('is_active', true),
        ]);

        return $this->ok($product, 'Product updated.');
    }

    /**
     * DELETE /api/admin/products/{id}
     */
    public function deleteProduct(int $id): JsonResponse
    {
        $p = Product::findOrFail($id);
        $p->cartItems()->delete();
        $p->wishlists()->delete();
        $p->allReviews()->delete();
        $p->images()->delete();
        $p->delete();

        return $this->ok(null, 'Product deleted.');
    }

    // ════════════════════════════════════════════════════════
    //  ADMIN — CATEGORIES  [auth:sanctum + admin]
    // ════════════════════════════════════════════════════════

    /**
     * POST /api/admin/categories
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:100']);

        $category = Category::create([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name),
            'description' => $request->description,
            'is_active'   => true,
        ]);

        return $this->ok($category, 'Category created.', 201);
    }

    /**
     * PUT /api/admin/categories/{id}
     */
    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->update($request->only(['name', 'description', 'is_active']));
        return $this->ok($category, 'Category updated.');
    }

    /**
     * DELETE /api/admin/categories/{id}
     */
    public function deleteCategory(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        if ($category->products()->count() > 0) {
            return $this->fail('Cannot delete — category has products.', 422);
        }

        $category->delete();
        return $this->ok(null, 'Category deleted.');
    }

    // ════════════════════════════════════════════════════════
    //  ADMIN — BRANDS  [auth:sanctum + admin]
    // ════════════════════════════════════════════════════════

    /**
     * POST /api/admin/brands
     */
    public function storeBrand(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:100']);

        $brand = Brand::create([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name),
            'description' => $request->description,
            'is_active'   => true,
        ]);

        return $this->ok($brand, 'Brand created.', 201);
    }

    /**
     * PUT /api/admin/brands/{id}
     */
    public function updateBrand(Request $request, int $id): JsonResponse
    {
        $brand = Brand::findOrFail($id);
        $brand->update($request->only(['name', 'description', 'is_active']));
        return $this->ok($brand, 'Brand updated.');
    }

    /**
     * DELETE /api/admin/brands/{id}
     */
    public function deleteBrand(int $id): JsonResponse
    {
        Brand::findOrFail($id)->delete();
        return $this->ok(null, 'Brand deleted.');
    }

    // ════════════════════════════════════════════════════════
    //  ADMIN — ORDERS  [auth:sanctum + admin]
    // ════════════════════════════════════════════════════════

    /**
     * GET /api/admin/orders?status=&payment_status=
     */
    public function allOrders(Request $request): JsonResponse
    {
        $query = Order::with('user');

        if ($request->filled('status'))         $query->where('status',         $request->status);
        if ($request->filled('payment_status')) $query->where('payment_status', $request->payment_status);

        return $this->ok($query->latest()->paginate(20));
    }

    /**
     * PUT /api/admin/orders/{id}/status
     * Body: status, payment_status(optional)
     */
    public function updateOrderStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status'         => 'required|in:Pending,Processing,Shipped,Delivered,Cancelled',
            'payment_status' => 'nullable|in:Unpaid,Paid',
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $request->status]);

        if ($request->filled('payment_status')) {
            $order->update(['payment_status' => $request->payment_status]);
        }

        return $this->ok($order, 'Order updated.');
    }

    // ════════════════════════════════════════════════════════
    //  ADMIN — USERS  [auth:sanctum + admin]
    // ════════════════════════════════════════════════════════

    /**
     * GET /api/admin/users
     */
    public function allUsers(): JsonResponse
    {
        return $this->ok(
            User::where('role', 'Customer')
                ->withCount('orders')
                ->latest()
                ->get()
        );
    }

    // ════════════════════════════════════════════════════════
    //  ADMIN — STOCK  [auth:sanctum + admin]
    // ════════════════════════════════════════════════════════

    /**
     * GET /api/admin/stock
     */
    public function stockData(): JsonResponse
    {
        $products = Product::with(['category', 'brand'])
            ->where('is_active', true)
            ->orderBy('stock', 'asc')
            ->get()
            ->map(fn($p) => array_merge($p->toArray(), ['stock_status' => $p->stock_status]));

        return $this->ok([
            'products'     => $products,
            'total_stock'  => Product::where('is_active', true)->sum('stock'),
            'out_of_stock' => Product::where('is_active', true)->where('stock', 0)->count(),
            'low_stock'    => Product::where('is_active', true)->whereBetween('stock', [1, 9])->count(),
        ]);
    }

    /**
     * POST /api/admin/stock/{id}
     * Body: quantity, note(optional)
     */
    public function addStock(Request $request, int $id): JsonResponse
    {
        $request->validate(['quantity' => 'required|integer|min:1|max:9999']);

        $product = Product::findOrFail($id);
        $before  = $product->stock;

        $product->increment('stock', $request->quantity);

        StockHistory::create([
            'product_id'       => $product->id,
            'quantity_added'   => $request->quantity,
            'stock_before'     => $before,
            'stock_after'      => $product->fresh()->stock,
            'note'             => $request->note,
            'added_by_user_id' => $request->user()->id,
        ]);

        return $this->ok($product->fresh(), 'Stock added!');
    }

    /**
     * GET /api/admin/stock-history?product_id=
     */
    public function stockHistory(Request $request): JsonResponse
    {
        $query = StockHistory::with(['product', 'addedBy']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        return $this->ok($query->latest()->take(100)->get());
    }

    // ════════════════════════════════════════════════════════
    //  ADMIN — COUPONS  [auth:sanctum + admin]
    // ════════════════════════════════════════════════════════

    /**
     * GET /api/admin/coupons
     */
    public function allCoupons(): JsonResponse
    {
        return $this->ok(Coupon::latest()->get());
    }

    /**
     * POST /api/admin/coupons
     */
    public function storeCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'code'  => 'required|unique:coupons,code|string|max:50',
            'type'  => 'required|in:percent,fixed',
            'value' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::create([
            'code'       => strtoupper($request->code),
            'type'       => $request->type,
            'value'      => $request->value,
            'min_order'  => $request->min_order ?? 0,
            'max_uses'   => $request->max_uses ?: null,
            'expires_at' => $request->expires_at ?: null,
            'is_active'  => true,
            'used_count' => 0,
        ]);

        return $this->ok($coupon, 'Coupon created.', 201);
    }

    /**
     * DELETE /api/admin/coupons/{id}
     */
    public function deleteCoupon(int $id): JsonResponse
    {
        Coupon::findOrFail($id)->delete();
        return $this->ok(null, 'Coupon deleted.');
    }

    // ════════════════════════════════════════════════════════
    //  ADMIN — REVIEWS  [auth:sanctum + admin]
    // ════════════════════════════════════════════════════════

    /**
     * GET /api/admin/reviews
     */
    public function allReviews(): JsonResponse
    {
        return $this->ok(Review::with(['user', 'product'])->latest()->get());
    }

    /**
     * PUT /api/admin/reviews/{id}/approve
     */
    public function approveReview(int $id): JsonResponse
    {
        Review::findOrFail($id)->update(['is_approved' => true]);
        return $this->ok(null, 'Review approved!');
    }

    /**
     * DELETE /api/admin/reviews/{id}
     */
    public function deleteReview(int $id): JsonResponse
    {
        Review::findOrFail($id)->delete();
        return $this->ok(null, 'Review deleted.');
    }

    // ════════════════════════════════════════════════════════
    //  ADMIN — PAYMENTS  [auth:sanctum + admin]
    // ════════════════════════════════════════════════════════

    /**
     * GET /api/admin/payments
     */
    public function allPayments(): JsonResponse
    {
        return $this->ok(Payment::with(['order', 'user'])->latest()->paginate(20));
    }

    /**
     * PUT /api/admin/payments/{id}
     * Body: status
     */
    public function updatePayment(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => 'required|in:Pending,Completed,Failed,Refunded']);

        $payment = Payment::findOrFail($id);
        $payment->update(['status' => $request->status]);

        // Also update order payment_status
        if ($request->status === 'Completed') {
            $payment->order->update(['payment_status' => 'Paid']);
        }

        return $this->ok($payment, 'Payment updated.');
    }

    // ════════════════════════════════════════════════════════
    //  ADMIN — BANNERS  [auth:sanctum + admin]
    // ════════════════════════════════════════════════════════

    /**
     * POST /api/admin/banners
     */
    public function storeBanner(Request $request): JsonResponse
    {
        $request->validate([
            'title'     => 'required|string|max:200',
            'image_url' => 'required|string',
        ]);

        $banner = Banner::create([
            'title'       => $request->title,
            'subtitle'    => $request->subtitle,
            'image_url'   => $request->image_url,
            'link_url'    => $request->link_url,
            'button_text' => $request->button_text,
            'sort_order'  => $request->sort_order ?? 0,
            'is_active'   => true,
        ]);

        return $this->ok($banner, 'Banner created.', 201);
    }

    /**
     * DELETE /api/admin/banners/{id}
     */
    public function deleteBanner(int $id): JsonResponse
    {
        Banner::findOrFail($id)->delete();
        return $this->ok(null, 'Banner deleted.');
    }
}