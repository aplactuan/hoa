<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>{{ $payment->or_no }}</title>
<style>body{font-family:DejaVu Sans,sans-serif;margin:30px;color:#1c2823;font-size:11px}header{border-bottom:3px solid #137c50;padding-bottom:18px}h1{font-size:24px;margin:0}h2{font-size:18px;margin-top:32px}table{width:100%;border-collapse:collapse;margin-top:20px}td,th{padding:10px;border-bottom:1px solid #ddd;text-align:left}td:last-child,th:last-child{text-align:right}.muted{color:#66736b}.total{font-weight:bold;font-size:16px}</style></head>
<body>
<header><h1>{{ $setting->hoa_name }}</h1><div class="muted">{{ $setting->hoa_address }}</div></header>
<h2>Office receipt record @if ($payment->status === 'Voided') — VOIDED @endif</h2>
<p><strong>{{ $payment->or_no }}</strong> · {{ $payment->paid_on->toDateString() }}</p>
<p>Received from: {{ $payment->property->currentOwner?->name ?? 'Property owner' }}<br>Property: {{ $payment->property->code }} · Block {{ $payment->property->block }} Lot {{ $payment->property->lot }}<br>Method: {{ $payment->method }} @if ($payment->reference_no) · Reference {{ $payment->reference_no }} @endif</p>
<table><tr><th>Applied to</th><th>Amount</th></tr>@foreach ($payment->allocations as $allocation)<tr><td>{{ $allocation->allocatable?->code ?? 'Account item' }}</td><td>₱{{ $allocation->amount }}</td></tr>@endforeach<tr class="total"><td>Total received</td><td>₱{{ $payment->amount }}</td></tr></table>
<p class="muted">This printout records an office payment. Confirm official receipt requirements with the HOA accountant.</p>
</body></html>
