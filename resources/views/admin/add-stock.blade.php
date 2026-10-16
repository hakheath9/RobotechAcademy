@extends('layouts.admin')
@section('title', app()->getLocale()=='km' ? 'បន្ថែមស្តុក' : 'Add Stock')

@section('content')

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('admin.stock') }}" class="qa-btn">← {{ app()->getLocale()=='km' ? 'ត្រឡប់ទៅស្តុក' : 'Back to Stock' }}</a>
    <h5 class="mb-0 fw-bold" style="font-size:16px;">
        {{ app()->getLocale()=='km' ? 'បន្ថែមស្តុក' : 'Add Stock' }}
    </h5>
</div>

<div class="row g-4">

    {{-- Left Column --}}
    <div class="col-lg-5">

        {{-- Product Info --}}
        <div class="panel p-4 mb-4">
            <h6 style="font-size:13px;font-weight:700;color:#111;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #f3f4f6;">
                {{ app()->getLocale()=='km' ? 'ព័ត៌មានផលិតផល' : 'Product Info' }}
            </h6>
            <div class="d-flex align-items-center gap-3">
                <img src="{{ $product->image_url ?? '' }}"
                     onerror="this.style.background='#f3f4f6'"
                     style="width:72px;height:72px;object-fit:contain;border-radius:12px;background:#f9fafb;padding:8px;flex-shrink:0;border:1px solid #e5e7eb;"/>
                <div>
                    <div style="font-weight:800;font-size:15px;color:#111;line-height:1.3;">{{ $product->name }}</div>
                    <div style="font-size:12px;color:#6b7280;margin-top:4px;">
                        {{ $product->category->name ?? '' }}
                        @if($product->brand) · {{ $product->brand->name }} @endif
                    </div>
                    <div style="margin-top:10px;display:flex;align-items:baseline;gap:6px;">
                        <span style="font-size:11px;color:#9ca3af;font-weight:600;">
                            {{ app()->getLocale()=='km' ? 'ស្តុកបច្ចុប្បន្ន:' : 'Current Stock:' }}
                        </span>
                        <span style="font-size:26px;font-weight:900;line-height:1;color:{{ $product->stock == 0 ? '#ef4444' : ($product->stock < 10 ? '#f59e0b' : '#10b981') }};">
                            {{ $product->stock }}
                        </span>
                        <span style="font-size:12px;color:#9ca3af;">{{ app()->getLocale()=='km' ? 'មុខ' : 'units' }}</span>
                    </div>
                    <div style="margin-top:6px;">
                        @if($product->stock == 0)
                            <span class="pill pill-cancelled">{{ app()->getLocale()=='km' ? 'អស់ស្តុក' : 'Out of Stock' }}</span>
                        @elseif($product->stock < 10)
                            <span class="pill pill-pending">{{ app()->getLocale()=='km' ? 'ស្តុកទាប' : 'Low Stock' }}</span>
                        @else
                            <span class="pill pill-delivered">{{ app()->getLocale()=='km' ? 'មានស្តុក' : 'In Stock' }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Add Stock Form --}}
        <div class="panel p-4">
            <h6 style="font-size:13px;font-weight:700;color:#111;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #f3f4f6;">
                {{ app()->getLocale()=='km' ? 'បន្ថែមបរិមាណ' : 'Add Quantity' }}
            </h6>

            <form action="{{ route('admin.stock.store', $product->id) }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label class="form-label fw-semibold small">
                        {{ app()->getLocale()=='km' ? 'បរិមាណដែលត្រូវបន្ថែម *' : 'Quantity to Add *' }}
                    </label>
                    <input type="number" name="quantity" class="form-ctrl"
                           min="1" max="9999" required autofocus
                           placeholder="{{ app()->getLocale()=='km' ? 'ឧ. 50' : 'e.g. 50' }}"
                           oninput="updatePreview(this.value)"/>
                    @error('quantity')
                        <small style="color:#dc2626;font-size:12px;">{{ $message }}</small>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small">
                        {{ app()->getLocale()=='km' ? 'កំណត់ចំណាំ' : 'Note' }}
                        <span style="color:#9ca3af;font-weight:400;">{{ app()->getLocale()=='km' ? '(ស្រេចចិត្ត)' : '(optional)' }}</span>
                    </label>
                    <textarea name="note" class="form-ctrl" rows="2"
                              placeholder="{{ app()->getLocale()=='km' ? 'ឧ. ទំនិញមកពីអ្នកផ្គត់ផ្គង់ថ្មី' : 'e.g. New shipment from supplier' }}"></textarea>
                </div>

                {{-- Stock Preview --}}
                <div style="background:#f9fafb;border-radius:12px;padding:16px;margin-bottom:20px;border:1px solid #f0f0f0;">
                    <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px;">
                        {{ app()->getLocale()=='km' ? 'ការមើលជាមុន' : 'Preview' }}
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <div style="text-align:center;">
                            <div style="font-size:10px;color:#9ca3af;margin-bottom:4px;">{{ app()->getLocale()=='km' ? 'បច្ចុប្បន្ន' : 'Current' }}</div>
                            <div style="font-size:24px;font-weight:900;color:#111;">{{ $product->stock }}</div>
                        </div>
                        <div style="font-size:20px;color:#9ca3af;font-weight:300;">+</div>
                        <div style="text-align:center;">
                            <div style="font-size:10px;color:#9ca3af;margin-bottom:4px;">{{ app()->getLocale()=='km' ? 'បន្ថែម' : 'Adding' }}</div>
                            <div style="font-size:24px;font-weight:900;color:#6366f1;" id="addQty">0</div>
                        </div>
                        <div style="font-size:20px;color:#9ca3af;font-weight:300;">=</div>
                        <div style="text-align:center;">
                            <div style="font-size:10px;color:#9ca3af;margin-bottom:4px;">{{ app()->getLocale()=='km' ? 'សរុប' : 'Total' }}</div>
                            <div style="font-size:24px;font-weight:900;color:#10b981;" id="totalQty">{{ $product->stock }}</div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="qa-btn qa-primary w-100" style="padding:12px;justify-content:center;font-size:14px;">
                    + {{ app()->getLocale()=='km' ? 'បន្ថែមស្តុក' : 'Add Stock' }}
                </button>

            </form>
        </div>

    </div>

    {{-- Right Column: Stock History --}}
    <div class="col-lg-7">
        <div class="panel">
            <div class="panel-head">
                <h6>{{ app()->getLocale()=='km' ? 'ប្រវត្តិស្តុកថ្មីៗ' : 'Recent Stock History' }}</h6>
            </div>
            @php
                $histories = \App\Models\StockHistory::where('product_id', $product->id)
                    ->with('addedBy')
                    ->latest()
                    ->take(10)
                    ->get();
            @endphp
            @if($histories->isEmpty())
                <div style="text-align:center;padding:40px;color:#9ca3af;font-size:13px;">
                    {{ app()->getLocale()=='km' ? 'មិនទាន់មានប្រវត្តិ' : 'No stock history yet.' }}
                </div>
            @else
                <table class="ra-table">
                    <thead>
                        <tr>
                            <th style="padding-left:20px;">{{ app()->getLocale()=='km' ? 'កាលបរិច្ឆេទ' : 'Date' }}</th>
                            <th>{{ app()->getLocale()=='km' ? 'មុន' : 'Before' }}</th>
                            <th>{{ app()->getLocale()=='km' ? 'បន្ថែម' : 'Added' }}</th>
                            <th>{{ app()->getLocale()=='km' ? 'ក្រោយ' : 'After' }}</th>
                            <th>{{ app()->getLocale()=='km' ? 'ដោយ' : 'By' }}</th>
                            <th style="padding-right:20px;">{{ app()->getLocale()=='km' ? 'កំណត់ចំណាំ' : 'Note' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($histories as $h)
                        <tr>
                            <td style="padding-left:20px;font-size:12px;color:#6b7280;">
                                {{ $h->created_at->format('M d, Y') }}<br>
                                <span style="font-size:11px;color:#9ca3af;">{{ $h->created_at->format('h:i A') }}</span>
                            </td>
                            <td style="font-size:13px;color:#6b7280;">{{ $h->stock_before }}</td>
                            <td>
                                <span style="font-weight:800;color:#10b981;font-size:14px;">+{{ $h->quantity_added }}</span>
                            </td>
                            <td style="font-weight:800;font-size:14px;color:#111;">{{ $h->stock_after }}</td>
                            <td style="font-size:12px;color:#374151;">{{ $h->addedBy->name ?? '—' }}</td>
                            <td style="padding-right:20px;font-size:12px;color:#9ca3af;">{{ $h->note ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

</div>

@push('scripts')
<script>
function updatePreview(val) {
    const qty   = parseInt(val) || 0;
    const stock = {{ $product->stock }};
    document.getElementById('addQty').textContent   = qty;
    document.getElementById('totalQty').textContent = stock + qty;
}
</script>
@endpush
@endsection