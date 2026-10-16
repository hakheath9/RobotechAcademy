@extends('layouts.admin')
@section('title', app()->getLocale()=='km' ? 'ម៉ាក' : 'Brands')

@section('content')

<div class="d-flex justify-content-end mb-4">
    <a href="{{ route('admin.brand.create') }}" class="qa-btn qa-primary">+ {{ app()->getLocale()=='km' ? 'បន្ថែមម៉ាក' : 'Add Brand' }}</a>
</div>

<div class="panel">
    <div class="panel-head">
        <h6>{{ app()->getLocale()=='km' ? 'ម៉ាក' : 'Brands' }} ({{ $brands->count() }})</h6>
    </div>
    <div class="table-responsive">
        <table class="ra-table">
            <thead>
                <tr>
                    <th style="padding-left:20px;">{{ app()->getLocale()=='km' ? 'ម៉ាក' : 'Brand' }}</th>
                    <th>{{ app()->getLocale()=='km' ? 'ផលិតផល' : 'Products' }}</th>
                    <th>{{ app()->getLocale()=='km' ? 'ស្ថានភាព' : 'Status' }}</th>
                    <th style="padding-right:20px;text-align:right;">{{ app()->getLocale()=='km' ? 'សកម្មភាព' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($brands as $brand)
                <tr>
                    <td style="padding-left:20px;">
                        <div class="d-flex align-items-center gap-3">
                            @if($brand->logo_url)
                                <img src="{{ $brand->logo_url }}"
                                     onerror="this.style.display='none'"
                                     style="width:36px;height:36px;object-fit:contain;border-radius:8px;background:#f9fafb;padding:4px;border:1px solid #e5e7eb;"/>
                            @else
                                <div style="width:36px;height:36px;background:#f3f4f6;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#6b7280;">
                                    {{ strtoupper(substr($brand->name, 0, 2)) }}
                                </div>
                            @endif
                            <span style="font-weight:700;color:#111;">{{ $brand->name }}</span>
                        </div>
                    </td>
                    <td>
                        <span style="font-weight:700;font-size:13px;">{{ $brand->products_count }}</span>
                        <span style="color:#9ca3af;font-size:12px;"> {{ app()->getLocale()=='km' ? 'មុខ' : 'products' }}</span>
                    </td>
                    <td>
                        <span class="pill {{ $brand->is_active ? 'pill-delivered' : 'pill-cancelled' }}">
                            {{ $brand->is_active ? (app()->getLocale()=='km' ? 'សកម្ម' : 'Active') : (app()->getLocale()=='km' ? 'លាក់' : 'Hidden') }}
                        </span>
                    </td>
                    <td style="padding-right:20px;text-align:right;">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="{{ route('admin.brand.edit', $brand->id) }}" class="view-btn">{{ app()->getLocale()=='km' ? 'កែ' : 'Edit' }}</a>
                            <form action="{{ route('admin.brand.delete', $brand->id) }}" method="POST" onsubmit="return confirm('{{ app()->getLocale()=='km' ? 'លុបចោលម៉ាក?' : 'Delete this brand?' }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="view-btn" style="border-color:#fecaca;color:#dc2626;background:#fff;">{{ app()->getLocale()=='km' ? 'លុប' : 'Delete' }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align:center;padding:40px;color:#9ca3af;">
                        {{ app()->getLocale()=='km' ? 'មិនទាន់មានម៉ាក' : 'No brands yet.' }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection