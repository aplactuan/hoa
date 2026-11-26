@php
    use App\Services\StatementOfAccount as Soa;
    $date = fn (?string $value): string => $value === null ? '-' : \Illuminate\Support\Carbon::parse($value)->format('M j, Y');
    $balance = $account['balance_cents'];
@endphp
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Statement of account</title>
<style>
body{font-family:DejaVu Sans,sans-serif;margin:30px;color:#1c2823;font-size:9px}
header{border-bottom:3px solid #137c50;padding-bottom:14px}
h1{font-size:22px;margin:0}h2{font-size:16px;margin:22px 0 6px}h3{font-size:12px;margin:18px 0 6px}
table{width:100%;border-collapse:collapse}
.grid td{padding:5px 8px;border-bottom:1px solid #ddd;text-align:left;vertical-align:top}
.grid th{padding:5px 8px;background:#137c50;color:#fff;text-align:left;font-size:8px;text-transform:uppercase}
.grid tr:nth-child(even) td{background:#f7f9f8}
.num{text-align:right!important}
.muted{color:#66736b}
.tiles td{width:20%;padding:8px;background:#e9eeeb;border:3px solid #fff;vertical-align:top}
.tiles td.main{background:#137c50;color:#fff}
.tiles .k{font-size:7px;text-transform:uppercase}
.tiles .v{font-size:13px;font-weight:bold;margin-top:3px}
.bad{color:#b3372b}.ok{color:#227a4b}.warn{color:#9a6a00}
.total td{font-weight:bold;background:#e9eeeb!important}
.page-break{page-break-before:always}
footer{margin-top:16px;color:#66736b;font-size:8px}
</style></head>
<body>
<header><h1>{{ $setting->hoa_name }}</h1><div class="muted">{{ $setting->hoa_address }}</div></header>
<h2>Statement of account</h2>
<table><tr>
<td>
<strong>{{ $account['owner_name'] }}</strong><br>
@foreach ($account['properties'] as $property){{ $property['code'] }} · {{ $property['block_lot'] }}@if ($property['address']) · {{ $property['address'] }}@endif<br>@endforeach
@if ($account['member_since'])Member since {{ $date($account['member_since']) }}@endif
</td>
<td class="num muted">Statement as of {{ $date($account['as_of']) }}<br>Printed {{ $date(now()->toDateString()) }}</td>
</tr></table>

<table class="tiles" style="margin-top:12px"><tr>
<td class="main"><div class="k">{{ $balance < 0 ? 'Credit balance' : 'Balance due' }}</div><div class="v">{{ Soa::peso(abs($balance)) }}</div></td>
<td><div class="k">Total billed</div><div class="v">{{ Soa::peso($account['billed_cents']) }}</div></td>
<td><div class="k">Penalties</div><div class="v">{{ Soa::peso($account['billed_penalties_cents']) }}</div></td>
<td><div class="k">Total paid</div><div class="v">{{ Soa::peso($account['paid_cents']) }}</div></td>
<td><div class="k">Discount</div><div class="v">{{ Soa::peso($account['discount_cents']) }}</div></td>
</tr></table>

@if ($account['overdue_cents'] > 0)<p class="bad">Overdue amount: {{ Soa::peso($account['overdue_cents']) }}</p>@endif
@if ($account['discount_cents'] > 0)<p class="ok">Early payment discount applied: {{ Soa::peso($account['discount_cents']) }}</p>@endif
@if ($account['pending_penalties_cents'] > 0)<p class="warn">Penalty amnesty: pay unpaid dues before the amnesty ends to avoid {{ Soa::peso($account['pending_penalties_cents']) }} in penalties.</p>
@elseif ($account['waived_penalties_cents'] > 0)<p class="ok">Penalties waived under amnesty: {{ Soa::peso($account['waived_penalties_cents']) }}</p>@endif

<h3>Monthly schedule</h3>
<table class="grid">
<tr><th>Item</th><th>Deadline</th><th class="num">Amount</th><th class="num">Penalty</th><th class="num">Paid</th><th class="num">Balance</th><th>Dues paid on</th><th>Status</th></tr>
@foreach ($account['charges'] as $charge)
<tr><td>{{ $charge['label'] }}</td><td>{{ $date($charge['date']) }}</td><td class="num">{{ Soa::amount($charge['amount_cents']) }}</td><td class="num">-</td><td class="num">{{ Soa::amount($charge['paid_cents']) }}</td><td class="num">{{ Soa::amount($charge['balance_cents']) }}</td><td>{{ $charge['balance_cents'] === 0 && $charge['paid_on'] ? $date($charge['paid_on']) : '-' }}</td><td>{{ $charge['status'] }}</td></tr>
@endforeach
@foreach ($account['months'] as $month)
<tr><td>{{ $month['month'] }}@if ($account['multiple_properties']) · {{ $month['property'] }}@endif</td><td>{{ $date($month['deadline']) }}</td><td class="num">{{ Soa::amount($month['dues_cents']) }}</td>
<td class="num">@if ($month['penalty_cents'] > 0){{ Soa::amount($month['penalty_cents']) }} ({{ $month['penalty_count'] }}x)@elseif ($month['waived_cents'] > 0){{ Soa::amount($month['waived_cents']) }} waived@elseif ($month['pending_cents'] > 0){{ Soa::amount($month['pending_cents']) }} held@else-@endif</td>
<td class="num">{{ Soa::amount($month['paid_cents']) }}</td><td class="num">{{ Soa::amount($month['balance_cents']) }}</td><td>{{ $date($month['dues_paid_on']) }}</td>
<td class="{{ ['red' => 'bad', 'amber' => 'warn', 'green' => 'ok'][$month['tone']] ?? 'muted' }}">{{ $month['status'] }}</td></tr>
@endforeach
@if ($account['credit_cents'] > 0)<tr><td colspan="5">Advance payment / credit</td><td class="num ok">-{{ Soa::amount($account['credit_cents']) }}</td><td></td><td>Applied to next charges</td></tr>@endif
<tr class="total"><td colspan="5">Totals as of {{ $date($account['as_of']) }}</td><td class="num">{{ $balance < 0 ? '-' : '' }}{{ Soa::amount($balance) }}</td><td colspan="2"></td></tr>
</table>

<div class="page-break"></div>
<h3>Transaction ledger</h3>
<table class="grid">
<tr><th>Date</th><th>Description</th><th>Reference</th><th class="num">Charges</th><th class="num">Payments</th><th class="num">Balance</th></tr>
@forelse ($account['lines'] as $line)
<tr><td>{{ $date($line['date']) }}</td><td class="{{ $line['kind'] === 'penalty' ? 'bad' : (in_array($line['kind'], ['waived', 'discount'], true) ? 'ok' : '') }}">{{ $line['description'] }}@if ($account['multiple_properties'] && $line['property']) · {{ $line['property'] }}@endif</td><td>{{ $line['reference'] }}</td><td class="num">{{ Soa::amount($line['debit_cents']) }}</td><td class="num">{{ Soa::amount($line['credit_cents']) }}</td><td class="num">{{ $line['balance_cents'] < 0 ? '-' : '' }}{{ Soa::amount($line['balance_cents']) }}</td></tr>
@empty
<tr><td colspan="6">No transactions on or before the as-of date.</td></tr>
@endforelse
</table>

<footer>{{ implode(' | ', $account['rules']) }}</footer>
</body></html>
