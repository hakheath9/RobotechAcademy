<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Review;
use App\Models\StockHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    // ════════════════════════════════════════════════════════
    //  DASHBOARD
    // ════════════════════════════════════════════════════════

    public function dashboard()
    {
        $totalCategories = Category::count();
        $totalBrands     = Brand::count();
        $totalProducts   = Product::where('is_active', true)->count();
        $totalRevenue    = Order::whereNotIn('status', ['Cancelled'])->sum('total_amount');
        $totalOrders     = Order::count();
        $totalUsers      = User::where('role', 'Customer')->count();
        $lowStock        = Product::where('stock', '<', 10)->where('is_active', true)->count();
        $pendingOrders   = Order::where('status', 'Pending')->count();
        $pendingReviews  = Review::where('is_approved', false)->count();

        $recentOrders = Order::with('user')->latest()->take(10)->get();

        // Revenue cards
        $revenueToday = Order::whereDate('created_at', today())->whereNotIn('status', ['Cancelled'])->sum('total_amount');
        $revenueWeek  = Order::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->whereNotIn('status', ['Cancelled'])->sum('total_amount');
        $revenueMonth = Order::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->whereNotIn('status', ['Cancelled'])->sum('total_amount');
        $ordersWeek   = Order::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();

        // Revenue last month for comparison
        $revenueLastMonth = Order::whereYear('created_at', now()->subMonth()->year)->whereMonth('created_at', now()->subMonth()->month)->whereNotIn('status', ['Cancelled'])->sum('total_amount');
        $revenueGrowth    = $revenueLastMonth > 0 ? round((($revenueMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1) : 0;

        // Chart data
        $chartLabels  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $chartRevenue = [];
        $chartOrders  = [];
        for ($m = 1; $m <= 12; $m++) {
            $mq = Order::whereYear('created_at', now()->year)->whereMonth('created_at', $m)->whereNotIn('status', ['Cancelled']);
            $chartRevenue[] = (float) $mq->sum('total_amount');
            $chartOrders[]  = $mq->count();
        }

        // Status counts for donut chart
        $statusCounts = [
            'Pending'    => Order::where('status', 'Pending')->count(),
            'Processing' => Order::where('status', 'Processing')->count(),
            'Shipped'    => Order::where('status', 'Shipped')->count(),
            'Delivered'  => Order::where('status', 'Delivered')->count(),
            'Cancelled'  => Order::where('status', 'Cancelled')->count(),
        ];

        // Top 5 best selling products
        $topProducts = OrderItem::select('product_id', 'product_name', DB::raw('SUM(quantity) as total_sold'), DB::raw('SUM(subtotal) as total_revenue'))
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_sold')
            ->take(5)
            ->get();

        $maxSold = $topProducts->max('total_sold') ?: 1;

        return view('admin.dashboard', compact(
            'totalCategories','totalBrands','totalProducts','totalRevenue',
            'totalOrders','totalUsers','lowStock','pendingOrders','pendingReviews',
            'recentOrders','revenueToday','revenueWeek','revenueMonth',
            'ordersWeek','revenueLastMonth','revenueGrowth',
            'chartLabels','chartRevenue','chartOrders',
            'statusCounts','topProducts','maxSold'
        ));
    }

    // ════════════════════════════════════════════════════════
    //  PRODUCTS
    // ════════════════════════════════════════════════════════

    public function products(Request $request)
    {
        $query = Product::with(['category', 'brand']);
        if ($request->filled('search'))      $query->where('name', 'like', '%'.$request->search.'%');
        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);
        if ($request->filled('brand_id'))    $query->where('brand_id', $request->brand_id);

        return view('admin.products', [
            'products'   => $query->latest()->paginate(15),
            'categories' => Category::orderBy('name')->get(),
            'brands'     => Brand::orderBy('name')->get(),
        ]);
    }

    public function createProduct()
    {
        return view('admin.product-form', [
            'product'    => null,
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            'brands'     => Brand::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeProduct(Request $request)
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
            $imageUrl = '/storage/'.$path;
        }

        Product::create([
            'category_id'       => $request->category_id,
            'brand_id'          => $request->brand_id,
            'name'              => $request->name,
            'slug'              => Str::slug($request->name).'-'.uniqid(),
            'sku'               => 'RA-'.strtoupper(Str::random(6)),
            'description'       => $request->description,
            'short_description' => $request->short_description,
            'price'             => $request->price,
            'sale_price'        => $request->sale_price ?: null,
            'stock'             => $request->stock,
            'image_url'         => $imageUrl,
            'is_active'         => true,
            'is_featured'       => $request->boolean('is_featured'),
        ]);

        return redirect()->route('admin.products')
            ->with('success', app()->getLocale()=='km' ? 'បានបន្ថែមផលិតផល!' : 'Product created!');
    }

    public function editProduct(int $id)
    {
        return view('admin.product-form', [
            'product'    => Product::findOrFail($id),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            'brands'     => Brand::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function updateProduct(Request $request, int $id)
    {
        $request->validate([
            'name'        => 'required|string|max:200',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
        ]);

        $product  = Product::findOrFail($id);
        $imageUrl = $product->image_url;
        if ($request->hasFile('image')) {
            $path     = $request->file('image')->store('products', 'public');
            $imageUrl = '/storage/'.$path;
        }

        $product->update([
            'category_id'       => $request->category_id,
            'brand_id'          => $request->brand_id,
            'name'              => $request->name,
            'description'       => $request->description,
            'short_description' => $request->short_description,
            'price'             => $request->price,
            'sale_price'        => $request->sale_price ?: null,
            'stock'             => $request->stock,
            'image_url'         => $imageUrl,
            'is_featured'       => $request->boolean('is_featured'),
            'is_active'         => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.products')
            ->with('success', app()->getLocale()=='km' ? 'បានធ្វើបច្ចុប្បន្នភាព!' : 'Product updated!');
    }

    public function deleteProduct(int $id)
    {
        $product = Product::findOrFail($id);
        $product->cartItems()->delete();
        $product->wishlists()->delete();
        $product->allReviews()->delete();
        $product->images()->delete();
        $product->delete();

        return redirect()->route('admin.products')
            ->with('success', app()->getLocale()=='km' ? 'បានលប់ចោល!' : 'Product deleted!');
    }

    // ════════════════════════════════════════════════════════
    //  CATEGORIES
    // ════════════════════════════════════════════════════════

    public function categories()
    {
        return view('admin.categories', [
            'categories' => Category::withCount('products')->orderBy('sort_order')->get(),
        ]);
    }

    public function createCategory()
    {
        return view('admin.category-form', ['category' => null]);
    }

    public function storeCategory(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100']);
        Category::create([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name),
            'description' => $request->description,
            'is_active'   => true,
            'sort_order'  => $request->sort_order ?? 0,
        ]);
        return redirect()->route('admin.categories')
            ->with('success', app()->getLocale()=='km' ? 'បានបន្ថែមប្រភេទ!' : 'Category created!');
    }

    public function editCategory(int $id)
    {
        return view('admin.category-form', ['category' => Category::findOrFail($id)]);
    }

    public function updateCategory(Request $request, int $id)
    {
        $request->validate(['name' => 'required|string|max:100']);
        Category::findOrFail($id)->update([
            'name'        => $request->name,
            'description' => $request->description,
            'is_active'   => $request->boolean('is_active', true),
            'sort_order'  => $request->sort_order ?? 0,
        ]);
        return redirect()->route('admin.categories')
            ->with('success', app()->getLocale()=='km' ? 'បានធ្វើបច្ចុប្បន្នភាព!' : 'Category updated!');
    }

    public function deleteCategory(int $id)
    {
        $category = Category::findOrFail($id);
        if ($category->products()->count() > 0) {
            return redirect()->route('admin.categories')
                ->with('error', app()->getLocale()=='km' ? 'មិនអាចលប់ — មានផលិតផលនៅក្នុងប្រភេទនេះ!' : 'Cannot delete — category has products!');
        }
        $category->delete();
        return redirect()->route('admin.categories')
            ->with('success', app()->getLocale()=='km' ? 'បានលប់ចោល!' : 'Category deleted!');
    }

    // ════════════════════════════════════════════════════════
    //  BRANDS
    // ════════════════════════════════════════════════════════

    public function brands()
    {
        return view('admin.brands', [
            'brands' => Brand::withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function createBrand()
    {
        return view('admin.brand-form', ['brand' => null]);
    }

    public function storeBrand(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100']);
        $logoUrl = null;
        if ($request->hasFile('logo')) {
            $path    = $request->file('logo')->store('brands', 'public');
            $logoUrl = '/storage/'.$path;
        }
        Brand::create([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name),
            'description' => $request->description,
            'logo_url'    => $logoUrl,
            'is_active'   => true,
        ]);
        return redirect()->route('admin.brands')
            ->with('success', app()->getLocale()=='km' ? 'បានបន្ថែមម៉ាក!' : 'Brand created!');
    }

    public function editBrand(int $id)
    {
        return view('admin.brand-form', ['brand' => Brand::findOrFail($id)]);
    }

    public function updateBrand(Request $request, int $id)
    {
        $request->validate(['name' => 'required|string|max:100']);
        $brand   = Brand::findOrFail($id);
        $logoUrl = $brand->logo_url;
        if ($request->hasFile('logo')) {
            $path    = $request->file('logo')->store('brands', 'public');
            $logoUrl = '/storage/'.$path;
        }
        $brand->update([
            'name'        => $request->name,
            'description' => $request->description,
            'logo_url'    => $logoUrl,
            'is_active'   => $request->boolean('is_active', true),
        ]);
        return redirect()->route('admin.brands')
            ->with('success', app()->getLocale()=='km' ? 'បានធ្វើបច្ចុប្បន្នភាព!' : 'Brand updated!');
    }

    public function deleteBrand(int $id)
    {
        Brand::findOrFail($id)->delete();
        return redirect()->route('admin.brands')
            ->with('success', app()->getLocale()=='km' ? 'បានលប់ចោល!' : 'Brand deleted!');
    }

    // ════════════════════════════════════════════════════════
    //  ORDERS
    // ════════════════════════════════════════════════════════

    public function orders(Request $request)
    {
        $query = Order::with('user');
        if ($request->filled('status'))         $query->where('status', $request->status);
        if ($request->filled('payment_status')) $query->where('payment_status', $request->payment_status);
        if ($request->filled('search'))         $query->where('id', 'like', '%'.$request->search.'%');

        return view('admin.orders', ['orders' => $query->latest()->paginate(20)]);
    }

    public function orderDetail(int $id)
    {
        $order = Order::with(['user', 'orderItems.product', 'coupon', 'payment'])->findOrFail($id);
        return view('admin.order-detail', compact('order'));
    }

    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'status'         => 'required|in:Pending,Processing,Shipped,Delivered,Cancelled',
            'payment_status' => 'nullable|in:Unpaid,Paid',
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $request->status]);

        if ($request->filled('payment_status')) {
            $order->update(['payment_status' => $request->payment_status]);
            if ($order->payment) {
                $order->payment->update([
                    'status' => $request->payment_status === 'Paid' ? 'Completed' : 'Pending',
                ]);
            }
        }

        return redirect()->route('admin.order.detail', $id)
            ->with('success', app()->getLocale()=='km' ? 'បានធ្វើបច្ចុប្បន្នភាព!' : 'Order status updated!');
    }

    // ════════════════════════════════════════════════════════
    //  USERS MANAGEMENT
    // ════════════════════════════════════════════════════════

    public function users(Request $request)
    {
        $query = User::withCount('orders');

        if ($request->filled('search')) {
            $query->where(fn($q) => $q
                ->where('name', 'like', '%'.$request->search.'%')
                ->orWhere('email', 'like', '%'.$request->search.'%')
            );
        }

        if ($request->filled('role'))   $query->where('role', $request->role);
        if ($request->filled('status')) $query->where('is_active', $request->status === 'active');

        // Tab filter
        $staffRoles = ['Staff'];
        if ($request->filled('tab')) {
            if ($request->tab === 'admin')    $query->where('role', 'Admin');
            if ($request->tab === 'staff')    $query->where('role', 'Staff');
            if ($request->tab === 'customer') $query->where('role', 'Customer');
            if ($request->tab === 'inactive') $query->where('is_active', false);
        }

        return view('admin.users', [
            'users'          => $query->latest()->paginate(20),
            'totalUsers'     => User::count(),
            'totalCustomers' => User::where('role', 'Customer')->count(),
            'totalAdmins'    => User::where('role', 'Admin')->count(),
            'totalStaff'     => User::where('role', 'Staff')->count(),
            'totalInactive'  => User::where('is_active', false)->count(),
        ]);
    }

    public function toggleUser(int $id)
    {
        $user = User::findOrFail($id);

        if ($user->isMainAdmin()) {
            return back()->with('error',
                app()->getLocale()=='km' ? 'មិនអាចបិទ Admin ចម្បងបានទេ!' : 'Cannot deactivate the main admin!'
            );
        }

        $user->update(['is_active' => !$user->is_active]);

        return back()->with('success',
            $user->fresh()->is_active
                ? (app()->getLocale()=='km' ? 'បានបើក User!' : 'User activated!')
                : (app()->getLocale()=='km' ? 'បានបិទ User!' : 'User deactivated!')
        );
    }

    public function promoteUser(Request $request, int $id)
    {
        $user    = User::findOrFail($id);
        $newRole = $request->input('role', 'Customer');

        // Cannot change MainAdmin
        if ($user->isMainAdmin()) {
            return back()->with('error',
                app()->getLocale()=='km'
                    ? 'មិនអាចផ្លាស់ប្តូរ Main Admin!'
                    : 'Cannot change Main Admin role!'
            );
        }

        // Valid roles
        $validRoles = ['Customer', 'Staff', 'Admin'];
        if (!in_array($newRole, $validRoles)) {
            return back()->with('error',
                app()->getLocale()=='km' ? 'ប្រភេទមិនត្រឹមត្រូវ!' : 'Invalid role!'
            );
        }

        $user->update(['role' => $newRole]);

        $label = match($newRole) {
            'Admin'    => 'Admin',
            'Staff'    => app()->getLocale()=='km' ? 'បុគ្គលិក' : 'Staff',
            'Customer' => app()->getLocale()=='km' ? 'អតិថិជន' : 'Customer',
            default    => $newRole,
        };

        return back()->with('success',
            app()->getLocale()=='km'
                ? "បានផ្លាស់ប្តូរ {$user->name} ជា {$label}!"
                : "Changed {$user->name} role to {$label}!"
        );
    }
    public function resetUserPassword(int $id)
    {
        $user = User::findOrFail($id);

        if ($user->isMainAdmin()) {
            return back()->with('error',
                app()->getLocale()=='km' ? 'មិនអាច Reset password Admin ចម្បងបានទេ!' : 'Cannot reset main admin password here!'
            );
        }

        $user->update(['password' => Hash::make('password123')]);

        return back()->with('success',
            app()->getLocale()=='km' ? 'បាន Reset password ជា "password123"!' : 'Password reset to "password123"!'
        );
    }

    public function deleteUser(int $id)
    {
        $user = User::findOrFail($id);

        if ($user->isMainAdmin()) {
            return back()->with('error',
                app()->getLocale()=='km' ? 'មិនអាចលប់ Admin ចម្បងបានទេ!' : 'Cannot delete the main admin!'
            );
        }

        if ($user->orders()->count() > 0) {
            return back()->with('error',
                app()->getLocale()=='km' ? 'មិនអាចលប់ — User នេះមានការបញ្ជាទិញ!' : 'Cannot delete — user has orders!'
            );
        }

        $user->cartItems()->delete();
        $user->wishlists()->delete();
        $user->reviews()->delete();
        $user->delete();

        return back()->with('success',
            app()->getLocale()=='km' ? 'បានលប់ User!' : 'User deleted!'
        );
    }

    // ════════════════════════════════════════════════════════
    //  COUPONS
    // ════════════════════════════════════════════════════════

    public function coupons()
    {
        return view('admin.coupons', ['coupons' => Coupon::latest()->get()]);
    }

    public function createCoupon()
    {
        return view('admin.coupon-form', ['coupon' => null]);
    }

    public function storeCoupon(Request $request)
    {
        $request->validate([
            'code'  => 'required|string|max:50|unique:coupons,code',
            'type'  => 'required|in:percent,fixed',
            'value' => 'required|numeric|min:0',
        ]);
        Coupon::create([
            'code'       => strtoupper($request->code),
            'type'       => $request->type,
            'value'      => $request->value,
            'min_order'  => $request->min_order ?? 0,
            'max_uses'   => $request->max_uses ?: null,
            'expires_at' => $request->expires_at ?: null,
            'is_active'  => true,
            'used_count' => 0,
        ]);
        return redirect()->route('admin.coupons')
            ->with('success', app()->getLocale()=='km' ? 'បានបន្ថែមគូប៉ុង!' : 'Coupon created!');
    }

    public function editCoupon(int $id)
    {
        return view('admin.coupon-form', ['coupon' => Coupon::findOrFail($id)]);
    }

    public function updateCoupon(Request $request, int $id)
    {
        $request->validate(['type' => 'required|in:percent,fixed', 'value' => 'required|numeric|min:0']);
        Coupon::findOrFail($id)->update([
            'type'       => $request->type,
            'value'      => $request->value,
            'min_order'  => $request->min_order ?? 0,
            'max_uses'   => $request->max_uses ?: null,
            'expires_at' => $request->expires_at ?: null,
            'is_active'  => $request->boolean('is_active', true),
        ]);
        return redirect()->route('admin.coupons')
            ->with('success', app()->getLocale()=='km' ? 'បានធ្វើបច្ចុប្បន្នភាព!' : 'Coupon updated!');
    }

    public function deleteCoupon(int $id)
    {
        Coupon::findOrFail($id)->delete();
        return redirect()->route('admin.coupons')
            ->with('success', app()->getLocale()=='km' ? 'បានលប់ចោល!' : 'Coupon deleted!');
    }

    // ════════════════════════════════════════════════════════
    //  REVIEWS
    // ════════════════════════════════════════════════════════

    public function reviews(Request $request)
    {
        $query = Review::with(['user', 'product']);
        if ($request->filled('status')) {
            $query->where('is_approved', $request->status === 'approved');
        }
        return view('admin.reviews', ['reviews' => $query->latest()->paginate(20)]);
    }

    public function approveReview(int $id)
    {
        Review::findOrFail($id)->update(['is_approved' => true]);
        return back()->with('success', app()->getLocale()=='km' ? 'បានអនុម័ត!' : 'Review approved!');
    }

    public function deleteReview(int $id)
    {
        Review::findOrFail($id)->delete();
        return back()->with('success', app()->getLocale()=='km' ? 'បានលប់ចោល!' : 'Review deleted!');
    }

    // ════════════════════════════════════════════════════════
    //  BANNERS
    // ════════════════════════════════════════════════════════

    public function banners()
    {
        return view('admin.banners', [
            'banners' => Banner::orderBy('sort_order')->get(),
        ]);
    }

    public function createBanner()
    {
        return view('admin.banner-form', ['banner' => null]);
    }

    public function storeBanner(Request $request)
    {
        $request->validate(['title' => 'required|string|max:200']);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path     = $request->file('image')->store('banners', 'public');
            $imageUrl = '/storage/'.$path;
        }

        Banner::create([
            'title'       => $request->title,
            'subtitle'    => $request->subtitle,
            'image_url'   => $imageUrl,
            'link_url'    => $request->link_url,
            'button_text' => $request->button_text,
            'sort_order'  => $request->sort_order ?? 0,
            'is_active'   => true,
        ]);

        return redirect()->route('admin.banners')
            ->with('success', app()->getLocale()=='km' ? 'បានបន្ថែមបដា!' : 'Banner created!');
    }

    public function editBanner(int $id)
    {
        return view('admin.banner-form', ['banner' => Banner::findOrFail($id)]);
    }

    public function updateBanner(Request $request, int $id)
    {
        $banner   = Banner::findOrFail($id);
        $imageUrl = $banner->image_url;

        if ($request->hasFile('image')) {
            $path     = $request->file('image')->store('banners', 'public');
            $imageUrl = '/storage/'.$path;
        }

        $banner->update([
            'title'       => $request->title,
            'subtitle'    => $request->subtitle,
            'image_url'   => $imageUrl,
            'link_url'    => $request->link_url,
            'button_text' => $request->button_text,
            'sort_order'  => $request->sort_order ?? 0,
            'is_active'   => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.banners')
            ->with('success', app()->getLocale()=='km' ? 'បានធ្វើបច្ចុប្បន្នភាព!' : 'Banner updated!');
    }

    public function toggleBanner(int $id)
    {
        $banner = Banner::findOrFail($id);
        $banner->update(['is_active' => !$banner->is_active]);
        return back()->with('success',
            $banner->fresh()->is_active
                ? (app()->getLocale()=='km' ? 'បានបើក!' : 'Banner activated!')
                : (app()->getLocale()=='km' ? 'បានបិទ!' : 'Banner deactivated!')
        );
    }

    public function deleteBanner(int $id)
    {
        Banner::findOrFail($id)->delete();
        return redirect()->route('admin.banners')
            ->with('success', app()->getLocale()=='km' ? 'បានលប់ចោល!' : 'Banner deleted!');
    }

    // ════════════════════════════════════════════════════════
    //  PAYMENTS
    // ════════════════════════════════════════════════════════

    public function payments(Request $request)
    {
        $query = Payment::with(['order', 'user']);
        if ($request->filled('status')) $query->where('status', $request->status);
        return view('admin.payments', ['payments' => $query->latest()->paginate(20)]);
    }

    // ════════════════════════════════════════════════════════
    //  STOCK
    // ════════════════════════════════════════════════════════

    public function stock(Request $request)
    {
        $query  = Product::with(['category', 'brand'])->where('is_active', true);
        $filter = $request->filter ?? 'all';
        if ($request->filled('search')) $query->where('name', 'like', '%'.$request->search.'%');
        match ($filter) {
            'out'   => $query->where('stock', 0),
            'low'   => $query->whereBetween('stock', [1, 9]),
            'ok'    => $query->where('stock', '>=', 10),
            default => null,
        };
        return view('admin.stock', [
            'products'   => $query->orderBy('stock', 'asc')->get(),
            'totalStock' => Product::where('is_active', true)->sum('stock'),
            'outOfStock' => Product::where('is_active', true)->where('stock', 0)->count(),
            'lowStock'   => Product::where('is_active', true)->whereBetween('stock', [1, 9])->count(),
            'filter'     => $filter,
        ]);
    }

    public function addStockForm(int $id)
    {
        return view('admin.add-stock', ['product' => Product::findOrFail($id)]);
    }

    public function addStock(Request $request, int $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:9999',
            'note'     => 'nullable|string|max:200',
        ]);

        $product = Product::findOrFail($id);
        $before  = $product->stock;
        $product->increment('stock', $request->quantity);

        StockHistory::create([
            'product_id'       => $product->id,
            'quantity_added'   => $request->quantity,
            'stock_before'     => $before,
            'stock_after'      => $product->fresh()->stock,
            'note'             => $request->note,
            'added_by_user_id' => Auth::id(),
        ]);

        return redirect()->route('admin.stock')
            ->with('success', app()->getLocale()=='km'
                ? "បានបន្ថែម {$request->quantity} ទៅ '{$product->name}'!"
                : "Added {$request->quantity} units to '{$product->name}'!");
    }

    public function stockHistory(Request $request)
    {
        $query = StockHistory::with(['product', 'addedBy']);
        if ($request->filled('product_id')) $query->where('product_id', $request->product_id);
        return view('admin.stock-history', [
            'histories' => $query->latest()->paginate(50),
            'products'  => Product::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    // ════════════════════════════════════════════════════════
    //  SETTINGS
    // ════════════════════════════════════════════════════════

    public function settings()
    {
        return view('admin.settings');
    }
}