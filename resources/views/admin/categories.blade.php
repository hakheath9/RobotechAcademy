@extends('layouts.admin')
@section('title', app()->getLocale()=='km' ? 'ប្រភេទ' : 'Categories')

@section('content')

<div class="d-flex justify-content-end mb-4">
    <a href="{{ route('admin.category.create') }}" class="qa-btn qa-primary">+ {{ app()->getLocale()=='km' ? 'បន្ថែមប្រភេទ' : 'Add Category' }}</a>
</div>

<div class="panel">
    <div class="panel-head">
        <h6>{{ app()->getLocale()=='km' ? 'ប្រភេទ' : 'Categories' }} ({{ $categories->count() }})</h6>
    </div>
    <div class="table-responsive">
        <table class="ra-table">
            <thead>
                <tr>
                    <th style="padding-left:20px;">No</th>
                    <th>{{ app()->getLocale()=='km' ? 'ឈ្មោះ' : 'Name' }}</th>
                    <th>{{ app()->getLocale()=='km' ? 'ផលិតផល' : 'Products' }}</th>
                    <th>{{ app()->getLocale()=='km' ? 'ស្ថានភាព' : 'Status' }}</th>
                    <th style="padding-right:20px;text-align:right;">{{ app()->getLocale()=='km' ? 'សកម្មភាព' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $cat)
                <tr>
                    <td style="padding-left:20px;font-size:12px;color:#9ca3af;">{{ $cat->id }}</td>
                    <td style="font-weight:700;color:#111;">{{ $cat->name }}</td>
                    <td>
                        <span style="font-weight:700;font-size:13px;">{{ $cat->products_count }}</span>
                        <span style="color:#9ca3af;font-size:12px;"> {{ app()->getLocale()=='km' ? 'មុខ' : 'products' }}</span>
                    </td>
                    <td>
                        <span class="pill {{ $cat->is_active ? 'pill-delivered' : 'pill-cancelled' }}">
                            {{ $cat->is_active ? (app()->getLocale()=='km' ? 'សកម្ម' : 'Active') : (app()->getLocale()=='km' ? 'លាក់' : 'Hidden') }}
                        </span>
                    </td>
                    <td style="padding-right:20px;text-align:right;">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="{{ route('admin.category.edit', $cat->id) }}" class="view-btn">{{ app()->getLocale()=='km' ? 'កែ' : 'Edit' }}</a>
                            <form action="{{ route('admin.category.delete', $cat->id) }}" method="POST" onsubmit="return confirm('{{ app()->getLocale()=='km' ? 'លុបចោលប្រភេទ?' : 'Delete this category?' }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="view-btn" style="border-color:#fecaca;color:#dc2626;background:#fff;">{{ app()->getLocale()=='km' ? 'លុប' : 'Delete' }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center;padding:40px;color:#9ca3af;">
                        {{ app()->getLocale()=='km' ? 'មិនទាន់មានប្រភេទ' : 'No categories yet.' }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection