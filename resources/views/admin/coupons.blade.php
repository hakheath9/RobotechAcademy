@extends('layouts.admin')
@section('title', app()->getLocale()=='km' ? 'គូប៉ុង' : 'Coupons')

@section('content')

<div class="d-flex justify-content-end mb-4">
    <a href="{{ route('admin.coupon.create') }}" class="qa-btn qa-primary">+ {{ app()->getLocale()=='km' ? 'បន្ថែមគូប៉ុង' : 'Add Coupon' }}</a>
</div>

<div class="panel">
    <div class="panel-head">
        <h6>{{ app()->getLocale()=='km' ? 'គូប៉ុង' : 'Coupons' }} ({{ $coupons->count() }})</h6>
    </div>
    <div class="table-responsive">
        <table class="ra-table">
            <thead>
                <tr>
                    <th style="padding-left:20px;">{{ app()->getLocale()=='km' ? 'លេខកូដ' : 'Code' }}</th>
                    <th>{{ app()->getLocale()=='km' ? 'ប្រភេទ' : 'Type' }}</th>
                    <th>{{ app()->getLocale()=='km' ? 'តម្លៃ' : 'Value' }}</th>
                    <th>{{ app()->getLocale()=='km' ? 'អប្បបរមា' : 'Min Order' }}</th>
                    <th>{{ app()->getLocale()=='km' ? 'ការប្រើ' : 'Uses' }}</th>
                    <th>{{ app()->getLocale()=='km' ? 'ផុតកំណត់' : 'Expires' }}</th>
                    <th>{{ app()->getLocale()=='km' ? 'ស្ថានភាព' : 'Status' }}</th>
                    <th style="padding-right:20px;text-align:right;">{{ app()->getLocale()=='km' ? 'សកម្មភាព' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($coupons as $coupon)
                <tr>
                    <td style="padding-left:20px;">
                        <span style="font-family:monospace;font-weight:800;font-size:13px;background:#f3f4f6;padding:4px 10px;border-radius:6px;letter-spacing:1px;">
                            {{ $coupon->code }}
                        </span>
                    </td>
                    <td>
                        <span class="pill {{ $coupon->type==='percent' ? 'pill-processing' : 'pill-shipped' }}">
                            {{ $coupon->type==='percent' ? (app()->getLocale()=='km' ? 'ភាគរយ' : 'Percent') : (app()->getLocale()=='km' ? 'ថេរ' : 'Fixed') }}
                        </span>
                    </td>
                    <td style="font-weight:700;color:#f59e0b;">
                        {{ $coupon->type==='percent' ? $coupon->value.'%' : '$'.number_format($coupon->value, 2) }}
                    </td>
                    <td style="font-size:13px;color:#374151;">${{ number_format($coupon->min_order, 2) }}</td>
                    <td style="font-size:13px;color:#374151;">
                        {{ $coupon->used_count }}
                        @if($coupon->max_uses) / {{ $coupon->max_uses }} @else / ∞ @endif
                    </td>
                    <td style="font-size:12px;color:#6b7280;">
                        {{ $coupon->expires_at ? $coupon->expires_at->format('M d, Y') : '—' }}
                        @if($coupon->expires_at && $coupon->expires_at->isPast())
                            <span class="pill pill-cancelled" style="font-size:10px;margin-left:4px;">
                                {{ app()->getLocale()=='km' ? 'ផុតកំណត់' : 'Expired' }}
                            </span>
                        @endif
                    </td>
                    <td>
                        <span class="pill {{ $coupon->is_active ? 'pill-delivered' : 'pill-cancelled' }}">
                            {{ $coupon->is_active ? (app()->getLocale()=='km' ? 'សកម្ម' : 'Active') : (app()->getLocale()=='km' ? 'អសកម្ម' : 'Inactive') }}
                        </span>
                    </td>
                    <td style="padding-right:20px;text-align:right;">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="{{ route('admin.coupon.edit', $coupon->id) }}" class="view-btn">{{ app()->getLocale()=='km' ? 'កែ' : 'Edit' }}</a>
                            <form action="{{ route('admin.coupon.delete', $coupon->id) }}" method="POST" onsubmit="return confirm('{{ app()->getLocale()=='km' ? 'លប់ចោលគូប៉ុង?' : 'Delete this coupon?' }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="view-btn" style="border-color:#fecaca;color:#dc2626;background:#fff;">{{ app()->getLocale()=='km' ? 'លប់' : 'Delete' }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center;padding:40px;color:#9ca3af;">
                        {{ app()->getLocale()=='km' ? 'មិនទាន់មានគូប៉ុង' : 'No coupons yet.' }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection