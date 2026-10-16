@extends('layouts.admin')
@section('title', $coupon ? 'Edit Coupon' : 'Add Coupon')

@section('content')

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('admin.coupons') }}" class="qa-btn">← Back</a>
    <h5 class="mb-0 fw-bold" style="font-size:16px;">{{ $coupon ? 'Edit Coupon' : 'Add New Coupon' }}</h5>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="panel p-4">
          

            <form action="{{ $coupon ? route('admin.coupon.update', $coupon->id) : route('admin.coupon.store') }}" method="POST">
                @csrf
                @if($coupon) @method('PUT') @endif

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Coupon Code *</label>
                    <input type="text" name="code" class="form-ctrl" required
                           value="{{ old('code', $coupon?->code) }}"
                           placeholder=""
                           style="text-transform:uppercase;font-family:monospace;font-weight:700;letter-spacing:1px;"
                           {{ $coupon ? 'readonly' : '' }}/>
                    @if($coupon)
                        <small style="font-size:11px;color:#9ca3af;margin-top:4px;display:block;">Code cannot be changed after creation.</small>
                    @endif
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Type *</label>
                        <select name="type" class="form-ctrl" required>
                            <option value="percent" {{ old('type', $coupon?->type) == 'percent' ? 'selected' : '' }}>Percentage (%)</option>
                            <option value="fixed"   {{ old('type', $coupon?->type) == 'fixed'   ? 'selected' : '' }}>Fixed Amount ($)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Value *</label>
                        <input type="number" name="value" class="form-ctrl" step="0.01" min="0" required
                               value="{{ old('value', $coupon?->value) }}"
                               placeholder="e.g. 10"/>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Minimum Order ($)</label>
                        <input type="number" name="min_order" class="form-ctrl" step="0.01" min="0"
                               value="{{ old('min_order', $coupon?->min_order ?? 0) }}"
                               placeholder="0"/>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">
                            Max Uses <span style="color:#9ca3af;font-weight:400;">optional</span>
                        </label>
                        <input type="number" name="max_uses" class="form-ctrl" min="1"
                               value="{{ old('max_uses', $coupon?->max_uses) }}"
                               placeholder="Unlimited"/>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">
                        Expiry Date <span style="color:#9ca3af;font-weight:400;">optional</span>
                    </label>
                    <input type="date" name="expires_at" class="form-ctrl"
                           value="{{ old('expires_at', $coupon?->expires_at?->format('Y-m-d')) }}"/>
                </div>

                @if($coupon)
                <div class="mb-4">
                    <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1"
                               style="width:16px;height:16px;accent-color:#f59e0b;"
                               {{ old('is_active', $coupon->is_active) ? 'checked' : '' }}/>
                        <span class="fw-semibold small">Active</span>
                    </label>
                </div>
                @endif

                <div class="d-flex gap-2">
                    <button type="submit" class="qa-btn qa-primary" style="padding:10px 24px;">
                        {{ $coupon ? 'Update Coupon' : 'Create Coupon' }}
                    </button>
                    <a href="{{ route('admin.coupons') }}" class="qa-btn" style="padding:10px 16px;">Cancel</a>
                </div>

            </form>
        </div>
    </div>
</div>
@endsection