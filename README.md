# Green Meadows

Homeowners association management app for Green Meadows: properties, owners and residents, billing, and statements of account.

## Billing maintenance commands

Billing for owners onboarded in production starts in **January 2025**. The membership fee, monthly fee and penalty rules aren't final yet, so we fix up billing in two steps.

### 1. Move owners' start of ownership to January 2025 (run after deploy)

```bash
php artisan owners:backdate-start --dry-run   # preview the changes
php artisan owners:backdate-start             # apply
```

- Moves every current owner who started after the date back to it. The default date is `2025-01-01`; use `--date=YYYY-MM-DD` to choose another.
- Each change is recorded in the audit log.
- Properties whose previous owner held them after that date are skipped and listed so they can be fixed by hand.
- Only changes dates. It doesn't bill anything.

### 2. Rebuild every account once the billing rules are final

First set the final **membership fee** and **penalty rules** in **Settings → Association**. Then:

```bash
php artisan billing:rebuild-accounts --monthly-fee=<amount> --dry-run   # preview the counts
php artisan billing:rebuild-accounts --monthly-fee=<amount> --force     # apply
```

It:

1. Saves `--monthly-fee` as the monthly rate effective from `--from` (default `2025-01`). Use this instead of the Settings form, which refuses back-dated rates once statements exist.
2. Generates statements for any month from `--from` to now that doesn't have one yet. Every owner is billed for every month.
3. Reprices existing statements from `--from` onward with the current monthly fee, due day and penalty rules. Fees locked in by an advance-payment promo are kept.
4. Charges each member's one-time membership fee, dated their start of ownership, or updates an existing one to the current amount. Waived fees are left alone.
5. Recalculates penalties and payment allocations for every account.

Good to know:

- Each statement keeps the rules from when it was generated. Changing Settings alone won't reprice past months; run this command after any rule change.
- Running it again with unchanged rules changes nothing, so it's safe to re-run.
- `--dry-run` does all the work and then rolls it back, so the counts it shows are real.
- A real run saves each owner's account as soon as it's rebuilt and shows a progress bar. If it's interrupted, just run it again; it picks up where it left off.
- Until the rebuild runs, the monthly statement run (1st of each month) and the nightly penalty update use the current settings. To keep owners from seeing interim penalties, add a **Penalty amnesty** in Settings.
