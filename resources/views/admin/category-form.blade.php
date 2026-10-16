@extends('layouts.admin')
@section('title', $category ? 'Edit Category' : 'Add Category')

@section('content')

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('admin.categories') }}" class="qa-btn">← Back</a>
    <h5 class="mb-0 fw-bold" style="font-size:16px;">{{ $category ? 'Edit Category' : 'Add New Category' }}</h5>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="panel p-4">
           

            <form action="{{ $category ? route('admin.category.update', $category->id) : route('admin.category.store') }}" method="POST">
                @csrf
                @if($category) @method('PUT') @endif

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Category Name *</label>
                    <input type="text" name="name" class="form-ctrl" required
                           value="{{ old('name', $category?->name) }}"
                           placeholder="e.g. Smartphones"/>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Description <span style="color:#9ca3af;font-weight:400;">optional</span></label>
                    <textarea name="description" class="form-ctrl" rows="3"
                              placeholder="">{{ old('description', $category?->description) }}</textarea>
                </div>


                @if($category)
                <div class="mb-4">
                    <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1"
                               style="width:16px;height:16px;accent-color:#f59e0b;"
                               {{ old('is_active', $category->is_active) ? 'checked' : '' }}/>
                        <span class="fw-semibold small">Active (visible in store)</span>
                    </label>
                </div>
                @endif

                <div class="d-flex gap-2">
                    <button type="submit" class="qa-btn qa-primary" style="padding:10px 24px;">
                        {{ $category ? 'Update Category' : 'Create Category' }}
                    </button>
                    <a href="{{ route('admin.categories') }}" class="qa-btn" style="padding:10px 16px;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection