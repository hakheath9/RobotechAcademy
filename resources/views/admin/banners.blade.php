@extends('layouts.admin')
@section('title', app()->getLocale()=='km' ? 'បដា' : 'Banners')

@section('content')

<div class="d-flex justify-content-end mb-4">
    <a href="{{ route('admin.banner.create') }}" class="qa-btn qa-primary">
        + {{ app()->getLocale()=='km' ? 'បន្ថែមបដា' : 'Add Banner' }}
    </a>
</div>

<div class="panel">
    <div class="panel-head">
        <h6>{{ app()->getLocale()=='km' ? 'បដា' : 'Banners' }} ({{ $banners->count() }})</h6>
    </div>
    <div class="table-responsive">
        <table class="ra-table">
            <thead>
                <tr>
                    <th style="padding-left:20px;">{{ app()->getLocale()=='km' ? 'រូបភាព' : 'Image' }}</th>
                    <th>{{ app()->getLocale()=='km' ? 'ចំណងជើង' : 'Title' }}</th>
                    <th>{{ app()->getLocale()=='km' ? 'ស្ថានភាព' : 'Status' }}</th>
                    <th style="padding-right:20px;text-align:right;">{{ app()->getLocale()=='km' ? 'សកម្មភាព' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($banners as $banner)
                @php $imgSrc = $banner->image_url ?? null; @endphp
                <tr>
                    {{-- Image --}}
                    <td style="padding-left:20px;">
                        @if($imgSrc)
                            <a href="#" onclick="event.preventDefault();viewImg('{{ $imgSrc }}')" title="{{ app()->getLocale()=='km' ? 'ចុចដើម្បីមើល' : 'Click to view' }}">
                                <img src="{{ $imgSrc }}"
                                     onerror="this.style.display='none'"
                                     style="width:80px;height:50px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;cursor:zoom-in;transition:opacity .15s;"
                                     onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'"/>
                            </a>
                        @else
                            <div style="width:80px;height:50px;background:#f3f4f6;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;">🖼</div>
                        @endif
                    </td>

                    {{-- Title only --}}
                    <td>
                        <div style="font-weight:700;font-size:13px;color:#111;">{{ $banner->title }}</div>
                        @if($banner->subtitle)
                            <div style="font-size:11px;color:#9ca3af;margin-top:2px;">{{ Str::limit($banner->subtitle, 40) }}</div>
                        @endif
                    </td>

                    {{-- Toggle --}}
                    <td>
                        <form action="{{ route('admin.banner.toggle', $banner->id) }}" method="POST">
                            @csrf
                            <button type="submit" style="background:none;border:none;cursor:pointer;padding:0;">
                                <div style="width:44px;height:24px;border-radius:20px;background:{{ $banner->is_active ? '#10b981' : '#e5e7eb' }};position:relative;transition:background .2s;">
                                    <div style="width:18px;height:18px;border-radius:50%;background:#fff;position:absolute;top:3px;left:{{ $banner->is_active ? '23px' : '3px' }};transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2);"></div>
                                </div>
                            </button>
                        </form>
                    </td>

                    {{-- Actions --}}
                    <td style="padding-right:20px;text-align:right;">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="{{ route('admin.banner.edit', $banner->id) }}" class="view-btn">
                                {{ app()->getLocale()=='km' ? 'កែ' : 'Edit' }}
                            </a>
                            <form action="{{ route('admin.banner.delete', $banner->id) }}" method="POST"
                                  onsubmit="return confirm('{{ app()->getLocale()=='km' ? 'លប់ចោលបដា?' : 'Delete this banner?' }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="view-btn" style="border-color:#fecaca;color:#dc2626;">
                                    {{ app()->getLocale()=='km' ? 'លុប' : 'Delete' }}
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align:center;padding:40px;color:#9ca3af;">
                        {{ app()->getLocale()=='km' ? 'មិនទាន់មានបដា' : 'No banners yet.' }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Image Preview Modal --}}
<div id="imgModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center;cursor:zoom-out;" onclick="closeModal()">
    <img id="imgModalSrc" src="" style="max-width:90vw;max-height:85vh;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.5);"/>
    <button onclick="closeModal()" style="position:absolute;top:20px;right:24px;background:rgba(255,255,255,.15);border:none;color:#fff;font-size:24px;width:40px;height:40px;border-radius:50%;cursor:pointer;">✕</button>
</div>

@push('scripts')
<script>
function viewImg(src) {
    document.getElementById('imgModalSrc').src = src;
    document.getElementById('imgModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    document.getElementById('imgModal').style.display = 'none';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal(); });
</script>
@endpush

@endsection