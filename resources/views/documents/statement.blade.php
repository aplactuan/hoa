<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>{{ $statement->code }}</title>
<style>body{font-family:DejaVu Sans,sans-serif;margin:30px;color:#1c2823;font-size:11px}header{border-bottom:3px solid #137c50;padding-bottom:18px}h1{font-size:24px;margin:0}h2{font-size:18px;margin-top:32px}table{width:100%;border-collapse:collapse;margin-top:20px}td,th{padding:10px;border-bottom:1px solid #ddd;text-align:left}td:last-child,th:last-child{text-align:right}.muted{color:#66736b}.total{font-weight:bold;font-size:16px}</style></head>
<body>
<header><h1>{{ $setting->hoa_name }}</h1><div class="muted">{{ $setting->hoa_address }}</div></header>
<h2>Statement of account</h2><p><strong>{{ $statement->code }}</strong> · {{ $statement->billingPeriod->year }}-{{ str_pad((string) $statement->billingPeriod->month, 2, '0', STR_PAD_LEFT) }}<br>Due {{ $statement->due_on->toDateString() }}</p>
<p>Owner: {{ $statement->property->currentOwner?->name ?? 'Property owner' }}<br>Property: {{ $statement->property->code }} · Block {{ $statement->property->block }} Lot {{ $statement->property->lot }}</p>
<table><tr><th>Description</th><th>Amount</th></tr><tr><td>Regular dues</td><td>₱{{ $statement->regular }}</td></tr><tr><td>Special assessment</td><td>₱{{ $statement->special }}</td></tr><tr><td>Previous balance</td><td>₱{{ $statement->previous_balance }}</td></tr><tr><td>Penalty</td><td>₱{{ $statement->penalty }}</td></tr><tr><td>Credits on statement</td><td>−₱{{ $statement->credits }}</td></tr><tr class="total"><td>Current account outstanding</td><td>₱{{ App\Support\BillingMath::decimal($account['outstanding_cents']) }}</td></tr></table>
<p class="muted">Payments and adjustments after generation are reflected in the current account outstanding.</p>
</body></html>
