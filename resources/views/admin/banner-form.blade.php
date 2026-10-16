@extends('layouts.admin')
@section('title', $banner ? (app()->getLocale()=='km' ? 'កែបដា' : 'Edit Banner') : (app()->getLocale()=='km' ? 'បន្ថែមបដា' : 'Add Banner'))

@section('content')

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('admin.banners') }}" class="qa-btn">
        ← {{ app()->getLocale()=='km' ? 'ត្រឡប់' : 'Back' }}
    </a>
    <h5 class="mb-0 fw-bold" style="font-size:16px;">
        {{ $banner ? (app()->getLocale()=='km' ? 'កែបដា' : 'Edit Banner') : (app()->getLocale()=='km' ? 'បន្ថែមបដាថ្មី' : 'Add New Banner') }}
    </h5>
</div>

<div class="row g-4">

    {{-- Form --}}
    <div class="col-lg-6">
        <div class="panel p-4">
            <form action="{{ $banner ? route('admin.banner.update', $banner->id) : route('admin.banner.store') }}"
                  method="POST" enctype="multipart/form-data">
                @csrf
                @if($banner) @method('PUT') @endif

                {{-- Title --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:13px;">
                        {{ app()->getLocale()=='km' ? 'ចំណងជើង *' : 'Title *' }}
                    </label>
                    <input type="text" name="title" class="form-ctrl" required
                           value="{{ old('title', $banner?->title) }}"
                           placeholder="{{ app()->getLocale()=='km' ? 'ចំណងជើងបដា' : '' }}"
                           oninput="updatePreview()"/>
                </div>

                {{-- Subtitle --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:13px;">
                        {{ app()->getLocale()=='km' ? 'ពណ៌នា' : 'Subtitle' }}
                        <span style="color:#9ca3af;font-weight:400;">({{ app()->getLocale()=='km' ? 'ស្រេចចិត្ត' : 'optional' }})</span>
                    </label>
                    <input type="text" name="subtitle" class="form-ctrl"
                           value="{{ old('subtitle', $banner?->subtitle) }}"
                           placeholder="{{ app()->getLocale()=='km' ? 'ពណ៌នាខ្លី' : '' }}"
                           oninput="updatePreview()"/>
                </div>



                

                {{-- Image Upload --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:13px;">
                        {{ app()->getLocale()=='km' ? 'រូបភាពបដា' : 'Banner Image' }}
                        
                    </label>

                    @if($banner?->image_url)
                    <div style="margin-bottom:10px;">
                        <img src="{{ $banner->image_url }}"
                             style="width:100%;height:100px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;"/>
                        <small style="font-size:11px;color:#9ca3af;">{{ app()->getLocale()=='km' ? 'រូបភាពបច្ចុប្បន្ន' : 'Current image' }}</small>
                    </div>
                    @endif

                    <div id="upload-area"
                         style="border:2px dashed #e2e8f0;border-radius:10px;padding:20px;text-align:center;cursor:pointer;background:#f8fafc;transition:all .15s;"
                         onclick="document.getElementById('bannerImg').click()"
                         ondragover="event.preventDefault();this.style.borderColor='#f59e0b';this.style.background='#fffbeb';"
                         ondragleave="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';"
                         ondrop="handleDrop(event)">
                        <div id="upload-placeholder">
                            <div style="font-size:28px;color:#d1d5db;margin-bottom:6px;">Click</div>
                            <div style="font-size:13px;font-weight:600;color:#374151;">
                                {{ app()->getLocale()=='km' ? 'ចុចឬអូសរូបភាព' : '' }}
                            </div>
                            <div style="font-size:11px;color:#9ca3af;margin-top:4px;"></div>
                        </div>
                        <img id="upload-preview" style="display:none;max-width:100%;max-height:120px;border-radius:8px;object-fit:cover;"/>
                    </div>
                    <input type="file" name="image" id="bannerImg" accept="image/*" style="display:none;" onchange="previewImg(this)"/>
                    <button type="button" id="remove-btn"
                            style="display:none;margin-top:6px;width:100%;background:#fee2e2;color:#dc2626;border:none;border-radius:8px;padding:6px;font-size:12px;font-weight:600;cursor:pointer;"
                            onclick="removeImg()">
                        {{ app()->getLocale()=='km' ? 'លប់រូបភាព' : 'Remove Image' }}
                    </button>
                </div>

                {{-- Active toggle (edit only) --}}
                @if($banner)
                <div class="mb-4">
                    <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1"
                               style="width:16px;height:16px;accent-color:#f59e0b;"
                               {{ old('is_active', $banner->is_active) ? 'checked' : '' }}/>
                        <span style="font-size:13px;font-weight:600;">
                            {{ app()->getLocale()=='km' ? 'សកម្ម (បង្ហាញនៅ Homepage)' : 'Active (show on Homepage)' }}
                        </span>
                    </label>
                </div>
                @endif

                {{-- Buttons --}}
                <div class="d-flex gap-2">
                    <button type="submit" class="qa-btn qa-primary">
                        {{ $banner ? (app()->getLocale()=='km' ? 'រក្សាទុក' : 'Update Banner') : (app()->getLocale()=='km' ? 'បន្ថែមបដា' : 'Create Banner') }}
                    </button>
                    <a href="{{ route('admin.banners') }}" class="qa-btn">
                        {{ app()->getLocale()=='km' ? 'បោះបង់' : 'Cancel' }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Live Preview --}}
    
    </div>

</div>

@push('scripts')
<script>
function previewImg(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('upload-placeholder').style.display = 'none';
            const p = document.getElementById('upload-preview');
            p.src = e.target.result; p.style.display = 'block';
            document.getElementById('remove-btn').style.display = 'block';
            document.getElementById('upload-area').style.borderColor = '#f59e0b';
            // Update preview BG
            const bg = document.getElementById('preview-bg');
            bg.src = e.target.result; bg.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeImg() {
    document.getElementById('bannerImg').value = '';
    document.getElementById('upload-preview').style.display = 'none';
    document.getElementById('upload-placeholder').style.display = 'block';
    document.getElementById('remove-btn').style.display = 'none';
    document.getElementById('upload-area').style.borderColor = '#e2e8f0';
    document.getElementById('preview-bg').style.display = 'none';
}

function handleDrop(e) {
    e.preventDefault();
    e.currentTarget.style.borderColor = '#e2e8f0';
    e.currentTarget.style.background = '#f8fafc';
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        const input = document.getElementById('bannerImg');
        const dt = new DataTransfer();
        dt.items.add(file); input.files = dt.files;
        previewImg(input);
    }
}

function updatePreview() {
    const title   = document.querySelector('input[name="title"]').value;
    const sub     = document.querySelector('input[name="subtitle"]').value;
    const btn     = document.querySelector('input[name="button_text"]').value;

    document.getElementById('preview-title').textContent = title   || '{{ app()->getLocale()=="km" ? "ចំណងជើងបដា" : "Banner Title" }}';
    document.getElementById('preview-sub').textContent   = sub     || '{{ app()->getLocale()=="km" ? "ពណ៌នាខ្លី" : "Subtitle here" }}';
    document.getElementById('preview-btn').textContent   = btn     || 'Shop Now';
}
</script>
@endpush
@endsection