@extends('layouts.app')
@section('title', 'My Account')
@section('content')
<div class="container py-5" style="max-width:600px;">
    <h2 class="section-title">My Account</h2>
    <div class="product-card p-4">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div style="width:64px;height:64px;background:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:800;color:#000;">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div>
                <div style="font-weight:800;font-size:18px;">{{ $user->name }}</div>
                <div style="color:#6b7280;font-size:13px;">{{ $user->email }}</div>
            </div>
        </div>
        <table class="table table-borderless" style="font-size:14px;">
            <tr>
                <td style="color:#9ca3af;width:120px;">Name</td>
                <td style="font-weight:600;">{{ $user->name }}</td>
            </tr>
            <tr>
                <td style="color:#9ca3af;">Email</td>
                <td style="font-weight:600;">{{ $user->email }}</td>
            </tr>
            <tr>
                <td style="color:#9ca3af;">Joined</td>
                <td style="font-weight:600;">{{ $user->created_at->format('d M Y') }}</td>
            </tr>
        </table>
        
    </div>  
</div>
@endsection