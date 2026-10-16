@extends('layouts.admin')
@section('title', 'Order Detail')

@section('content')

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('admin.orders') }}" class="qa-btn">← Back</a>
    <h5 class="mb-0 fw-bold">RA-{{ $order->id }}</h5>
    @php $sc = match($order->status){'Delivered'=>'pill-delivered','Cancelled'=>'pill-cancelled','Shipped'=>'pill-shipped','Processing'=>'pill-processing',default=>'pill-pending'}; @endphp
    <span class="pill {{ $sc }}">{{ $order->status }}</span>
</div>

<div class="row g-4">
    <div class="col-lg-8">

        {{-- Items --}}
        <div class="panel mb-4">
            <div class="panel-head">
                <h6>Order Items ({{ $order->orderItems->count() }})</h6>
            </div>
            <div class="table-responsive">
                <table class="ra-table">
                    <thead>
                        <tr>
                            <th style="padding-left:20px;">Product</th>
                            <th>Unit Price</th>
                            <th>Qty</th>
                            <th style="padding-right:20px;text-align:right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->orderItems as $item)
                        <tr>
                            <td style="padding-left:20px;">
                                <div class="d-flex align-items-center gap-2">
                                    <img src="{{ $item->product_image }}"
                                         onerror="this.style.background='#f3f4f6';this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2244%22 height=%2244%22%3E%3Crect width=%2244%22 height=%2244%22 fill=%22%23f3f4f6%22/%3E%3Ctext x=%2222%22 y=%2227%22 text-anchor=%22middle%22 font-size=%2214%22 fill=%22%239ca3af%22%3EP%3C/text%3E%3C/svg%3E'"
                                         style="width:44px;height:44px;object-fit:contain;border-radius:8px;background:#f9fafb;padding:4px;"/>
                                    <span style="font-weight:600;font-size:13px;">{{ $item->product_name }}</span>
                                </div>
                            </td>
                            <td style="font-size:13px;color:#6b7280;">${{ number_format($item->unit_price, 2) }}</td>
                            <td style="font-weight:700;color:#374151;">× {{ $item->quantity }}</td>
                            <td style="padding-right:20px;text-align:right;font-weight:700;">${{ number_format($item->subtotal, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Customer & Shipping --}}
        <div class="row g-3">
            <div class="col-md-6">
                <div class="panel p-4">
                    <h6 class="fw-bold mb-3" style="font-size:13px;">Customer</h6>
                    <div style="font-weight:700;font-size:13px;color:#111;">{{ $order->user->name }}</div>
                    <div style="color:#6b7280;font-size:13px;">{{ $order->user->email }}</div>
                    <div style="color:#6b7280;font-size:13px;">{{ $order->user->phone }}</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel p-4">
                    <h6 class="fw-bold mb-3" style="font-size:13px;">Shipping Address</h6>
                    <p style="font-size:13px;color:#374151;margin:0;line-height:1.7;">{{ $order->shipping_address }}</p>
                    @if($order->note)
                        <p style="font-size:12px;color:#9ca3af;margin-top:8px;"><strong>Note:</strong> {{ $order->note }}</p>
                    @endif
                </div>
            </div>
        </div>

    </div>

    <div class="col-lg-4">

        {{-- Update Status --}}
        <div class="panel p-4 mb-4">
            <h6 class="fw-bold mb-3" style="font-size:13px;">Update Status</h6>
            <form action="{{ route('admin.order.status', $order->id) }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Order Status</label>
                    <select name="status" class="form-ctrl">
                        @foreach(['Pending','Processing','Shipped','Delivered','Cancelled'] as $s)
                            <option value="{{ $s }}" {{ $order->status == $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Payment Status</label>
                    <select name="payment_status" class="form-ctrl">
                        <option value="Unpaid" {{ $order->payment_status == 'Unpaid' ? 'selected' : '' }}>Unpaid</option>
                        <option value="Paid"   {{ $order->payment_status == 'Paid'   ? 'selected' : '' }}>Paid</option>
                    </select>
                </div>
                <button type="submit" class="qa-btn qa-primary w-100" style="padding:10px;">Update</button>
            </form>
        </div>

        {{-- Payment Summary --}}
        <div class="panel p-4">
            <h6 class="fw-bold mb-3" style="font-size:13px;">Payment Summary</h6>
            <div class="d-flex justify-content-between mb-2" style="font-size:13px;">
                <span style="color:#6b7280;">Subtotal</span>
                <span>${{ number_format($order->subtotal, 2) }}</span>
            </div>
            @if($order->discount > 0)
            <div class="d-flex justify-content-between mb-2" style="font-size:13px;">
                <span style="color:#10b981;">Discount</span>
                <span style="color:#10b981;">-${{ number_format($order->discount, 2) }}</span>
            </div>
            @endif
            <div class="d-flex justify-content-between mb-2" style="font-size:13px;">
                <span style="color:#6b7280;">Shipping</span>
                <span style="color:#10b981;">Free</span>
            </div>
            <hr style="border-color:#f3f4f6;"/>
            <div class="d-flex justify-content-between mb-3">
                <span class="fw-bold">Total</span>
                <span style="font-weight:900;font-size:20px;color:#f59e0b;">${{ number_format($order->total_amount, 2) }}</span>
            </div>
            <div style="font-size:13px;color:#6b7280;margin-bottom:4px;">
                <strong style="color:#111;">Payment:</strong> {{ $order->payment_method }}
            </div>
            <div style="font-size:13px;margin-bottom:4px;">
                <strong style="color:#111;">Status:</strong>
                <span style="color:{{ $order->payment_status === 'Paid' ? '#10b981' : '#f59e0b' }};font-weight:700;">
                    {{ $order->payment_status }}
                </span>
            </div>
            @if($order->payment)
            
            @endif
            <div style="font-size:13px;color:#6b7280;margin-top:6px;">
                <strong style="color:#111;">Placed:</strong> {{ $order->created_at->format('M d, Y h:i A') }}
            </div>
        </div>

    </div>
</div>
@endsection