# Green Meadows Phase 1 HOA — Implementation Plan

Oct 11, 2026 · @Sam

## Overview

Build the Green Meadows Phase 1 HOA Management Office as a Laravel + Livewire web app in 8 phases: about 19 weeks to go-live with a two-developer team, then 4 weeks of hypercare. The plan follows the prototype artifact screen by screen: 20 modules in 5 sidebar groups (Overview, Property & People, Billing, Office, Admin), one Property ID linking every record, and four roles.

Rules the prototype already settles, to carry into the build as-is:

- One property record per block and lot; every owner, resident, vehicle, bill and case links to it.
- Ownership is history: recording a new current owner moves the previous one to Former with an end date. Nothing is overwritten.
- Vehicle stickers are kept per year; a renewal is a new record, not an edit.
- Monthly dues = lot area × ₱10 per sqm, due on the 15th. An unpaid balance carries to the next statement with a 2% penalty.
- Standing is calculated: Good Standing (no arrears), With Arrears (less than 3 months of regular dues overdue), Delinquent (3 months or more).
- Every add, edit and payment writes a permanent audit entry with user and device.
- Security Guards never see balances, payments or owner documents.

| Layer | Recommended choice | Why it fits |
| --- | --- | --- |
| Framework | Laravel, current stable release | Mature auth, queues, scheduler, policies |
| UI | Livewire 3 + Alpine.js + Tailwind CSS | Drawers, modals, live filters and chips like the prototype, without a separate SPA |
| Auth | Laravel Fortify (or Breeze), 2FA for Administrator and Treasurer | Office PCs and a shared guardhouse tablet |
| Permissions | spatie/laravel-permission + Laravel policies | Role matrix plus per-field masking of financial data |
| Database | MySQL 8 or PostgreSQL; money as DECIMAL(12,2) | Exact peso amounts, foreign keys to `properties` |
| Audit | Custom append-only `audit_logs` table written by model observers | Old/new values, user, device; no edits allowed |
| Documents | barryvdh/laravel-dompdf | Statements of account, receipts, demand letters, clearances |
| Import/export | maatwebsite/excel | Migrating the existing spreadsheet tabs; CSV exports |
| Background work | Laravel scheduler + database queue | Statement generation, expiry checks, daily digest |
| Files | Laravel storage (local disk or S3), served through authorized routes | Titles, deeds, IDs, OR scans, photos |

The plan reads in two layers: Part 1 is the eight phases in build order; Part 2 breaks down each of the 20 modules (fields, actions, statuses, validation, related modules, metrics). The schema, MVP scope, timeline and open questions close it.

## Roles and access

Four roles, matching the prototype's role switcher, with approvals added where money or enforcement is involved. Codes: **V** view · **C** create · **E** edit · **A** approve · **R** read only · **—** no access (hidden from the sidebar).

| Module | Administrator | Office Staff | Treasurer | Security Guard |
| --- | --- | --- | --- | --- |
| Dashboard | V (all widgets) | V (no payment button) | V (financial widgets) | — (lands on Gate Check) |
| Gate Check | V | V | — | V, C gate log entries |
| Properties | V C E | V C E | R | R (no standing or balance) |
| Owners | V C E, A ownership transfer | V C E (drafts transfer) | R | — |
| Residents | V C E | V C E | — | R |
| Family Members | V C E | V C E | — | R (name, relationship only) |
| House Staff | V C E | V C E | — | R, E remarks |
| Vehicles & Stickers | V C E | V C E, issues stickers | — | R, E parking and remarks |
| Monthly Dues | V, A adjustments and penalty waivers | R | V C (generate), E adjustments for approval | — |
| Payments | V, A voids | R | V C (record, issue OR), requests voids | — |
| Other Charges | V C E, A waivers | V C E | V C E | — |
| Owner Account Summary | V | V | V | — |
| Transactions | V C E | V C E | V C E (fees, OR) | — |
| Requests & Complaints | V C E | V C E (assign, resolve) | — | C, R own entries |
| Violations | V C E, A escalation and fine waiver | V C E | — (fines reach them as charges) | — |
| Facility Use | V C E, A approve or decline | V C E (books) | V C E (fees, deposits, refunds) | — |
| Correspondence | V C E | V C E | V C E (demand letters) | — |
| Audit Log | R, export | — | R | — |
| Roles & Access | V C E users and roles | — | — | — |
| Global Search | All records | All records | Properties, owners | Plates, passes, resident names only |

Rules behind the matrix:

- Financial fields (balance, standing, dues, owner email, TCT) are masked by policy for Security Guard everywhere, including drawers, search results and property profile tabs.
- Nobody edits the Audit Log, including Administrator.
- Posted statements and payments are never edited; they are adjusted or voided, and the void needs Administrator approval.
- In the prototype Office Staff can see billing but cannot record payments. Keep that separation of duties unless the office also acts as cashier (open question).

## Part 1 — Phases at a glance

Each phase ends with something the office can use and test; billing (Phase 3) is the critical path because everything financial on the dashboard depends on it.

| # | Phase | Delivers | Weeks | Priority |
| --- | --- | --- | --- | --- |
| 1 | Project foundation | Login, roles, layout shell, audit log, settings and lookup lists | 1–2 | Must-have |
| 2 | Property and people records | Properties, owners, residents, family, house staff, vehicles, property profile | 3–5 | Must-have |
| 3 | Billing | Statements, payments with OR, other charges, account summary, standing | 6–9 | Must-have |
| 4 | Office operations | Transactions, requests and complaints, violations, correspondence | 10–12 | Must-have (correspondence templates Should-have) |
| 5 | Gate and security | Gate Check, sticker issuance and renewal, gate log | 11–12, alongside Phase 4 | Must-have (gate log Should-have) |
| 6 | Facility reservations | Bookings, conflict check, approval, fees and deposits | 13–14 | Should-have |
| 7 | Dashboard, search, notifications, reports | KPIs, charts, Needs Attention, global search, reports, polish | 15–16 | Must-have (reports and digests Should-have) |
| 8 | Launch | Testing, data migration, training, deployment, hypercare | 17–19, then 20–23 | Must-have |

### Phase 1: Project foundation

- **Goal:** A secure, empty shell every later module plugs into: users log in, see only their sidebar items, and every change is logged.
- **Sections/modules included:** Roles & Access, Audit Log (write side and viewer), base layout (sidebar, top bar, drawer, modal, toast), settings and master data.
- **User roles involved:** Administrator (sets up users, roles, settings); all four roles for login testing.
- **Features to build:**
  - Laravel project, Livewire 3, Tailwind, CI pipeline (tests + Pint) and staging server.
  - Login, logout, password reset, session timeout; 2FA for Administrator and Treasurer.
  - Roles and permissions seeded from the matrix above; Laravel policies per model; a `financial` permission that masks money fields.
  - App shell copied from the prototype: grouped sidebar with count badges, top bar with global search slot and signed-in user, right-side record drawer, centered form modal, toasts, mobile menu, dark mode.
  - Reusable Livewire pieces: data table (filter box, status chips, sorting, pagination, record count, totals row), record drawer with change history, form modal with validation, status pill.
  - Code generator for display IDs (PRP-0001, OWN-0001, SOA-202610-001, OR numbers) using a locked `sequences` table, so two users never get the same number.
  - Audit trait/observer that writes Create, Update, Delete, Payment, Void, Login and Export entries with old/new values, user, IP and device label, inside the same database transaction.
  - Settings screen: dues rate per sqm, penalty %, due day, grace days, expiry warning window (45 days), HOA name and address for printouts.
  - Lookup lists editable by Administrator: streets, sections, property types, transaction types with default fees, charge types, violation types with default fines, request categories, facilities with fees and deposits.
- **Database entities / tables needed:** `users`, `roles`, `permissions`, `model_has_roles`, `role_has_permissions`, `audit_logs`, `settings`, `sequences`, `attachments` (polymorphic), `streets`, `sections`, `transaction_types`, `charge_types`, `violation_types`, `request_categories`, `facilities`, `devices` (named PCs and guardhouse tablet).
- **Key workflows:**
  1. Administrator creates a user, assigns one role, user receives a set-password link.
  2. User logs in and lands on their home screen (Dashboard; Gate Check for Security Guard).
  3. Any save calls the audit observer; the entry appears in the record's change history.
- **Dependencies or prerequisites:** Hosting decision (cloud VPS or on-premise office server), domain/SSL, the final role matrix signed off by the board, lookup values (fees, fines, facilities) confirmed.
- **Acceptance criteria:**
  - Each role sees exactly the sidebar items in the matrix; direct URLs to hidden modules return 403.
  - A Security Guard response never contains a money value (checked by automated test on every route).
  - Audit entries cannot be updated or deleted through the app, and the database user lacks UPDATE/DELETE on `audit_logs`.
  - Two simultaneous saves never produce the same display ID.
  - Layout works at phone width (guard tablet) and on office PCs.
- **Priority:** Must-have.

### Phase 2: Property and people records

- **Goal:** Every block and lot has one complete profile showing who owns it, who lives there, their household, staff and vehicles.
- **Sections/modules included:** Properties (list + property profile), Owners, Residents, Family Members, House Staff, Vehicles & Stickers (registry part; sticker issuance finishes in Phase 5).
- **User roles involved:** Administrator and Office Staff (create, edit); Treasurer (read properties and owners); Security Guard (read residents, family, staff, vehicles).
- **Features to build:**
  - Module list pages with the prototype's chips (status filters), filter box, record count and row → drawer.
  - Property profile with header (Property ID, Account No., status, standing, outstanding balance placeholder until Phase 3) and tabs: Overview, Ownership history, Household, Vehicles, Billing & payments (stub), Office records (stub), Audit trail.
  - Overview tab: property, current owner and current resident panels plus linked-record counters that jump to tabs.
  - Record transfer of ownership: new Current owner closes the previous owner (Former, end date = transfer date) in one transaction; timeline view of all owners.
  - Resident move-in and move-out; setting the property's current resident.
  - Family members tied to both property and resident; house staff with HOA staff pass number and expiry; vehicles with plate, sticker number, sticker year and expiry.
  - Document attachments: TCT, tax declaration, deed of sale, lease, staff valid ID.
  - Archive instead of delete for any record with history.
- **Database entities / tables needed:** `properties`, `owners` (ownership records, one row per owner per tenure), `residents`, `family_members`, `house_staff`, `vehicles`, `vehicle_stickers` (one row per vehicle per year), `attachments`.
- **Key workflows:**
  1. Add property (block, lot, street, section, type, area) → system assigns Property ID, Account No., Membership No.
  2. Record transfer of ownership → previous owner becomes Former → property's current owner updated → two audit entries.
  3. Tenant moves in → resident (Tenant) added with lease attached → family and staff added → vehicles registered.
  4. Tenant moves out → move-out date set → family and staff deactivated → stickers cancelled.
- **Dependencies or prerequisites:** Phase 1 complete; a clean master list of blocks and lots with lot areas; decision on co-owners (several Current owners on one property).
- **Acceptance criteria:**
  - Block + lot is unique; a property cannot have two Current owners unless both are Co-Owner.
  - A former owner's record is read-only except remarks.
  - Opening a property shows all linked people and vehicles within 1 second for 1,000 properties.
  - Security Guard sees household names and vehicles but no owner documents or balances.
  - Every create and edit appears in the property's Audit trail tab.
- **Priority:** Must-have.

### Phase 3: Billing, dues, payments, charges and owner statements

- **Goal:** The Treasurer generates monthly statements, records every peso received against a statement or charge, and any account's balance and standing is correct at all times.
- **Sections/modules included:** Monthly Dues, Payments, Other Charges, Owner Account Summary, the Billing & payments tab of the property profile, and the standing and balance shown on Properties.
- **User roles involved:** Treasurer (generate, record, adjust); Administrator (approve adjustments, waivers, voids); Office Staff (read, add charges); Security Guard (no access).
- **Features to build:**
  - Generate next period's statements for all properties in one action (the prototype's "Generate November 2026 statements" button), as a queued job that is safe to run twice.
  - Statement calculation: regular dues = lot area × rate; special assessment; penalty = 2% of previous balance; previous balance; credits/adjustments; total, paid, balance and status all calculated, never typed.
  - Record payment and issue OR: choose property, apply to latest statement, a specific unpaid charge, or advance payment; method (Cash, Check, GCash, Maya, Bank Transfer); reference number; received by; next OR number pre-filled.
  - Payment allocations: one payment can settle several charges and statements, oldest first by default; overpayment becomes a credit on the next statement.
  - Void payment (Treasurer requests, Administrator approves) with reason; reverses allocations and writes audit entries.
  - Other charges: manual and automatic (sticker, violation fine, transaction fee, facility fee), Unpaid/Paid/Waived, waiver needs approval.
  - Account service: one class that returns dues balance, unpaid charges, arrears, total and standing for a property; used everywhere (profile header, Properties list, dashboard, summary).
  - Owner Account Summary: look up by Owner ID, show owner, property, standing, outstanding, totals and all ten record sections; print as PDF.
  - Printable statement of account and official receipt (PDF).
  - Totals row on Dues, Payments and Charges lists ("Total shown").
- **Database entities / tables needed:** `billing_periods`, `dues_statements`, `statement_adjustments`, `payments`, `payment_allocations`, `charges`, `or_series` (receipt booklets or ranges), `credits`.
- **Key workflows:**
  1. 1st of the month: Treasurer clicks Generate → job creates one statement per property, carrying forward unpaid balance with the 2% penalty → audit entry "Generated October 2026 statements (N)".
  2. Owner pays at the office → Treasurer records payment → allocations settle oldest items → OR issued and printed → statement status updates (Paid, Partial) → standing recalculates.
  3. Wrong payment → Treasurer requests void → Administrator approves → allocations reversed → corrected payment re-entered with a new OR.
  4. Penalty waiver → Treasurer enters adjustment with reason → Administrator approves → credit applied to the statement.
- **Dependencies or prerequisites:** Phase 2 properties with correct lot areas; opening balances per account as of the cut-over month; board-approved rules for penalty (simple or compounding), payment allocation order, special assessments; OR/BIR decision (see open questions).
- **Acceptance criteria:**
  - Recalculated balances match a hand-checked spreadsheet for at least 20 sample accounts across 6 months, including partial, late and delinquent payers.
  - Generating the same period twice creates no duplicates.
  - A posted statement or payment cannot be edited; corrections only through adjustment or void, both audited.
  - OR numbers are unique and sequential with no gaps unexplained by a void.
  - Standing labels follow the thresholds (Good Standing, With Arrears under 3 months, Delinquent 3 months or more).
- **Priority:** Must-have.

### Phase 4: Office operations

- **Goal:** Every walk-in request, complaint, rule violation and letter is logged against a property, assigned, tracked to a close, and billed where a fee or fine applies.
- **Sections/modules included:** Transactions, Requests & Complaints, Violations, Correspondence, the Office records tab of the property profile.
- **User roles involved:** Office Staff (main user); Administrator (approves escalations and fine waivers); Treasurer (fees, demand letters); Security Guard (logs requests and incidents from the gate).
- **Features to build:**
  - Transactions with 8 types and default fees (Clearance ₱200, Gate Pass ₱0, Construction Permit ₱2,500, Move-in ₱1,000, Move-out ₱500, Sticker Application ₱600, Certificate of Residency ₱150, Document Request ₱100); a fee creates a charge, and Completed needs the OR when a fee applies.
  - Printable outputs for transactions: HOA clearance, certificate of residency, gate pass, construction permit (Should-have).
  - Requests & Complaints: category, priority, assignee, action-taken updates as a timeline, resolution date, photos.
  - Violations: type with default fine, notice date, compliance deadline (default notice + 15 days), escalate, mark complied, close; issuing a notice creates the Violation Fine charge and a Notice correspondence entry.
  - Correspondence log: notices, letters, demand letters, memos, emails, circulars; acknowledgment tracking; templates for demand letters and violation notices with merge fields (owner, property, balance, statement ID).
  - Demand letter batch: pick With Arrears/Delinquent accounts → generate letters → log one correspondence row each.
- **Database entities / tables needed:** `office_transactions`, `cases` (requests and complaints), `case_updates`, `violations`, `correspondence`, `letter_templates`, plus `charges` and `attachments` from earlier phases.
- **Key workflows:**
  1. Owner requests a clearance → Office Staff creates transaction → system checks standing → fee charge created → Treasurer records payment → transaction Completed with OR → clearance printed.
  2. Resident complaint → case logged (Open) → assigned → updates added (In Progress) → Resolved with action taken → Closed after confirmation.
  3. Guard reports unapproved construction → violation recorded → notice issued (Notice Sent, fine charged, letter logged) → deadline passes → Escalated (Administrator approves) or Complied → Closed.
  4. Month-end → Treasurer runs demand letter batch → letters printed/emailed → acknowledgment updated as replies come in.
- **Dependencies or prerequisites:** Phase 3 charges and payments (fees and fines must post to accounts); approved fine schedule and violation rules from the HOA by-laws; letter wording approved by the board.
- **Acceptance criteria:**
  - A transaction with a fee cannot be Completed without a linked payment.
  - A violation fine appears on the owner's balance the moment the notice is issued.
  - Resolved cases always have action taken and a resolution date.
  - Demand letters only go to accounts that are With Arrears or Delinquent at generation time.
- **Priority:** Must-have for transactions, requests and violations; Should-have for letter templates and the demand letter batch.

### Phase 5: Gate and security operations

- **Goal:** A guard types a plate, sticker number, staff pass or name and gets a clear VALID / EXPIRED / NOT FOUND answer in under 2 seconds, with no financial data shown.
- **Sections/modules included:** Gate Check, Vehicles & Stickers (issuance and renewal), House Staff passes, gate log (new; the prototype has lookup only).
- **User roles involved:** Security Guard (lookups, log entries, incident requests); Office Staff (issue and renew stickers and passes); Administrator.
- **Features to build:**
  - Gate Check screen: large plate-style input, Enter to check, result cards with a verdict stamp (VALID, EXPIRED, PASS OK, PASS EXP., RESIDENT, MOVED OUT, NOT FOUND) and the property's block, lot and street.
  - Matching rules: plate ignoring spaces and case; sticker number; staff pass number or name; resident and family names.
  - NOT FOUND guidance: treat as visitor, confirm with the household.
  - "Expired stickers on file" list below the search, as in the prototype.
  - Sticker issuance: register or renew → new `vehicle_stickers` row for the year → ₱600 Car Sticker charge → sticker active once paid (or on issue, per policy).
  - Year-end renewal batch: list all vehicles without next-year stickers; reminder letters.
  - Staff pass renewal with new expiry.
  - Gate log (Should-have): entry time, plate or name, verdict, visitor's host property, purpose, guard, exit time.
  - Tablet-friendly layout and short session timeout for the shared guardhouse device.
- **Database entities / tables needed:** `vehicles`, `vehicle_stickers`, `house_staff`, `gate_logs`, `devices`.
- **Key workflows:**
  1. Car arrives → guard enters plate → VALID → let in (optionally logged).
  2. Plate EXPIRED → guard advises the driver to renew at the office → entry logged with verdict.
  3. NOT FOUND → treated as visitor → guard calls the household → visitor logged with host property.
  4. January → Office Staff runs renewal list → owners pay at the office → new stickers issued.
- **Dependencies or prerequisites:** Phase 2 vehicles and staff; Phase 3 charges for sticker fees; guardhouse device and internet confirmed; sticker policy (renewal date, fee, whether arrears block issuance).
- **Acceptance criteria:**
  - Lookup returns in under 2 seconds on the guardhouse connection.
  - VALID only when status is Active and expiry is today or later.
  - No guard-facing screen or API response includes balances, standing or payments.
  - Each sticker year is a separate record; last year's sticker stays in history.
- **Priority:** Must-have for Gate Check and sticker issuance; Should-have for the gate log.

### Phase 6: Facility reservations and approval flow

- **Goal:** Homeowners book the clubhouse, function hall, courts and pool through the office without double bookings, and fees and deposits are tracked to refund.
- **Sections/modules included:** Facility Use, its link to Payments and Other Charges, Upcoming reservations on the dashboard.
- **User roles involved:** Office Staff (books for the homeowner); Administrator (approves or declines); Treasurer (collects fees and deposits, refunds deposits).
- **Features to build:**
  - Reservation form: facility, event date, start and end time (missing in the prototype; needed for conflict checks), purpose, expected guests, fees and deposit pre-filled from the facility list (Clubhouse ₱3,000 + ₱2,000 deposit; Function Hall ₱5,000 + ₱3,000; Basketball Court ₱500; Swimming Pool ₱1,500 + ₱1,000; Covered Court ₱800 + ₱500).
  - Availability check: no two Approved or Pending bookings overlap for the same facility and time.
  - Approval queue for Administrator: Approve or Decline with reason; approver and timestamp stored.
  - Fees and deposit as charges; payment status Unpaid, Partially Paid, Paid, Refunded.
  - Post-event inspection: refund deposit in full, or deduct a damage charge.
  - Calendar view per facility (Should-have).
- **Database entities / tables needed:** `facilities`, `facility_reservations`, `charges`, `payments`.
- **Key workflows:**
  1. Owner asks for the clubhouse → Office Staff checks availability → reservation Pending → fee and deposit charges created.
  2. Administrator approves (or declines with reason) → owner pays → payment status Paid.
  3. After the event → inspection → deposit refunded or damage charge applied → status Refunded.
  4. Cancellation → status Cancelled → refund per policy.
- **Dependencies or prerequisites:** Phase 3 charges and payments; facility rules (hours, max guests, who may book, whether delinquent accounts may book, cancellation and refund policy).
- **Acceptance criteria:**
  - Overlapping bookings for the same facility are rejected with a clear message.
  - Only Administrator can approve or decline.
  - A deposit cannot be refunded before the event date.
  - Pending approvals show in Needs Attention and the sidebar badge.
- **Priority:** Should-have (small module; recommended inside the MVP because the prototype's dashboard already depends on it).

### Phase 7: Dashboard, reporting, search, notifications and polish

- **Goal:** The office opens one screen each morning and sees what was collected, who is behind, and what needs action today; any record is two keystrokes away.
- **Sections/modules included:** Dashboard, Global Search, Notifications / Needs Attention, sidebar badges, reports, final UI polish across all modules.
- **User roles involved:** All four; widgets and search results filtered per role.
- **Features to build:**
  - Dashboard KPI row: total properties (occupied, vacant), total outstanding, current-month collection rate (collected ÷ billed), accounts in good standing out of total.
  - Collections by month chart (last 6 months, current month to date highlighted).
  - Panels: accounts with arrears (top 6 by balance), open cases by priority, upcoming reservations, Needs Attention.
  - Quick actions by permission: Record payment, New transaction, Log request.
  - Needs Attention counts: pending transactions, violations awaiting compliance, stickers and staff passes expired or expiring in 45 days, demand letters without response, reservations pending approval; each count links to a pre-filtered list.
  - Sidebar badges: open requests, open violations, pending transactions, pending reservations.
  - Global search in the top bar: Blk/Lot, address, account number, Property ID, current owner, non-owner resident, plate; minimum 2 characters, top 8 results, opens the property profile.
  - In-app notifications (Laravel database notifications) for: reservation awaiting approval, void or waiver awaiting approval, urgent case logged; daily email digest (Should-have).
  - Reports (PDF and CSV): collection report by day and month, aging of receivables (current, 1–30, 31–60, 61–90, 90+ days), delinquency list, sticker registry, violations summary, occupancy list.
  - Polish: empty states, loading states, keyboard support (Esc closes drawers), print styles, accessibility pass, performance tuning (indexes, eager loading, cached KPI queries).
- **Database entities / tables needed:** `notifications`; reporting views or cached aggregates (`monthly_collection_summaries`) if queries get slow; full-text or prefix indexes on names, plates and account numbers.
- **Key workflows:**
  1. Morning: user opens Dashboard → clicks a Needs Attention count → lands on the filtered list → resolves items → count drops.
  2. Phone call from a homeowner → user types the surname or Blk/Lot in search → opens profile → answers balance or status question.
  3. Month-end: Treasurer exports collection and aging reports for the board.
- **Dependencies or prerequisites:** Phases 2–6 data in place; board agreement on which reports it wants monthly.
- **Acceptance criteria:**
  - Every dashboard number equals the total of the list it links to (automated reconciliation tests).
  - Security Guard never sees the dashboard financial widgets or owner search results.
  - Search returns in under 500 ms for 1,000 properties.
  - Reports match the module totals for the same date range.
- **Priority:** Must-have for the dashboard, Needs Attention and global search; Should-have for reports and email digests; Nice-to-have for charts beyond the collections chart.

### Phase 8: Testing, data migration, training, deployment and post-launch support

- **Goal:** Go live with correct opening balances, trained users and a rollback plan, then keep the system stable through the first two billing cycles.
- **Sections/modules included:** All modules; spreadsheet import tools; production environment; backups.
- **User roles involved:** All four roles in user acceptance testing; Administrator and Treasurer sign off on migrated balances.
- **Features to build:**
  - Automated tests: Pest feature tests per module, permission tests per role and route, billing calculation tests with fixed scenarios, browser tests (Laravel Dusk) for Record payment, Generate statements, Gate Check and Transfer of ownership.
  - Import tools for each spreadsheet tab (the prototype's schema mirrors them): validate → preview errors → import → reconciliation report.
  - Opening balance import per account as of the cut-over month, entered as a "Balance forward" statement line.
  - Production setup: HTTPS, daily database and file backups with off-site copy, tested restore, queue worker and scheduler under a process supervisor, error monitoring, uptime check.
  - Training material: one-page guides per role (Gate Check for guards, Record payment and Generate statements for the Treasurer, front-desk flows for Office Staff), short screen recordings.
  - Hypercare: daily check-ins for week 1, bug triage, a feedback channel, fixes released weekly.
- **Database entities / tables needed:** `import_batches` and `import_errors` (staging), all production tables; no new business tables.
- **Key workflows:**
  1. Freeze the spreadsheets at cut-over date → export → import into staging → reconciliation report → fix data → sign-off.
  2. UAT: each role runs a scripted day (collect payments, issue a sticker, log a complaint, approve a reservation) → defects fixed.
  3. Go-live weekend: final import → smoke test → users switch on Monday → spreadsheets become read-only archives.
  4. First billing cycle in the new system checked side by side with a manual calculation.
- **Dependencies or prerequisites:** All Must-have features complete; clean source data; trainers and users available; production hosting paid for.
- **Acceptance criteria:**
  - Migrated total outstanding equals the spreadsheet total to the centavo; every account's balance signed off by the Treasurer.
  - All UAT scripts pass; no open critical or high defects.
  - Backup restored successfully on a test server before go-live.
  - Each user has logged in and completed their role's training checklist.
  - First generated statements after go-live need no manual correction.
- **Priority:** Must-have.

## Part 2 — Module breakdown

All 20 sections from the artifact, in sidebar order, plus the cross-cutting pieces (Global Search, Notifications). Every record module shares the same pattern from the prototype: list with status chips and filter, row opens a drawer with all fields and change history, Edit opens a form modal, and each record links back to its property.

### Overview

#### Dashboard

- **Main data fields:** KPI tiles (total properties with occupied/vacant split, total outstanding, current-month collection %, accounts in good standing / total, payments recorded); collections by month for 6 months; top 6 accounts with arrears (Blk/Lot, owner, street, balance, standing); open cases (category, description, priority); upcoming reservations (facility, date, purpose, approval); Needs Attention counts.
- **Required actions:** Record payment, New transaction, Log request (shown only with permission); "View all" links to filtered lists; click a case or reservation to open its drawer.
- **Statuses/workflow states:** None of its own; displays standing, case priority and reservation approval.
- **Important validation rules:** Widgets filtered by role (Security Guard never lands here); collection % = dues payments this month ÷ regular dues billed this month; every figure computed from the same query scopes as the module lists.
- **Related modules:** Properties, Monthly Dues, Payments, Other Charges, Requests, Facility Use, Transactions, Violations, Vehicles, House Staff, Correspondence.
- **Reports or dashboard metrics it contributes to:** It is the summary surface; the same queries feed the monthly board report.

#### Gate Check

- **Main data fields:** Search input (plate, sticker no., staff pass no., resident or family name). Results: verdict stamp; vehicle plate, color, make, model, sticker no. and year, expiry, registered driver; staff name, role, pass no., expiry, employer; resident name, relationship; property Blk/Lot, street.
- **Required actions:** Check (button or Enter), example shortcuts, open property (non-financial view), view expired stickers on file; log entry (gate log).
- **Statuses/workflow states:** VALID, EXPIRED (vehicles); PASS OK, PASS EXP. (staff); RESIDENT, MOVED OUT (residents and family); NOT FOUND → treat as visitor.
- **Important validation rules:** Normalize input (uppercase, spaces removed for plates); VALID only if status Active and expiry ≥ today; no money fields in any response; lookups by the guard role can be audited.
- **Related modules:** Vehicles & Stickers, House Staff, Residents, Family Members, Properties, gate log.
- **Reports or dashboard metrics it contributes to:** Entries per day, NOT FOUND and EXPIRED counts, expired stickers seen at the gate (feeds Needs Attention).

### Property & People

#### Properties

- **Main data fields:** Property ID (PRP-), Account No. (ACC-), Membership No. (MEM-), property status, block, lot, house no., street, phase/section, property type (House & Lot, Townhouse, Vacant Lot, Commercial), lot area (sqm), date added, current owner, current resident, notes. Calculated: HOA standing, outstanding balance, monthly dues (area × rate).
- **Required actions:** Add, edit, archive; filter by status or standing (All, Good Standing, With Arrears, Delinquent); open property profile with 7 tabs (Overview, Ownership history, Household, Vehicles, Billing & payments, Office records, Audit trail); record payment from the profile header.
- **Statuses/workflow states:** Occupied, Vacant, Under Construction, For Sale. Standing: Good Standing, With Arrears, Delinquent.
- **Important validation rules:** Block + lot unique; account and membership numbers unique; lot area > 0; current owner must be a Current owner record of this property; a Vacant lot has no active resident; no hard delete when linked records exist.
- **Related modules:** Every module (all records carry the property ID).
- **Reports or dashboard metrics it contributes to:** Total properties, occupied vs vacant, standing distribution, outstanding total, occupancy list.

#### Owners

- **Main data fields:** Owner ID, property, full name, phone, email, mailing address, civil status, ownership status (Sole Owner, Co-Owner, Corporate, Heirs/Estate), TCT/title no., tax declaration no., deed of sale ref., date acquired, registered owner (Yes/No), verification date, start date, end date, record status, remarks.
- **Required actions:** Add, edit, record transfer of ownership, view ownership timeline, attach title and deed scans.
- **Statuses/workflow states:** Current, Former.
- **Important validation rules:** Saving a new Current owner closes the previous Current owner (Former, end date set) in the same transaction; several Current owners only if Co-Owner; end date ≥ start date; Former records read-only except remarks; Philippine mobile format (09XX XXX XXXX); email format; transfer blocked or warned when the account has arrears (policy to confirm).
- **Related modules:** Properties, Owner Account Summary, Correspondence (recipient), Transactions (clearance).
- **Reports or dashboard metrics it contributes to:** Ownership transfers per year, unverified owners, absentee owners (mailing address differs from property).

#### Residents

- **Main data fields:** Resident ID, property, full name, phone, email, relationship to owner (Owner, Spouse, Tenant, Relative, Caretaker), occupancy type (Owner-Occupied, Tenant, Caretaker, Relative), move-in date, move-out date, emergency contact name and number, status, remarks.
- **Required actions:** Add, edit, set as current resident, mark moved out.
- **Statuses/workflow states:** Active, Moved Out.
- **Important validation rules:** Move-out ≥ move-in; moving out clears the property's current resident and prompts to deactivate family, staff and stickers; tenant should have a lease attached; warning when adding to a Vacant lot.
- **Related modules:** Family Members, House Staff (employer), Vehicles (driver), Gate Check, Requests (filed by), Transactions (move-in/move-out).
- **Reports or dashboard metrics it contributes to:** Occupancy rate, tenant vs owner-occupied count, move-ins and move-outs per month.

#### Family Members

- **Main data fields:** Family record ID, property, resident ID, full name, relationship (Spouse, Son, Daughter, Parent, Sibling, Relative), phone, email, birth date, occupation, emergency contact (Yes/No), move-in, move-out, status, remarks.
- **Required actions:** Add, edit, mark moved out.
- **Statuses/workflow states:** Active, Moved Out.
- **Important validation rules:** Resident must belong to the same property; birth date not in the future; contact fields optional for minors; guards see name and relationship only.
- **Related modules:** Residents, Gate Check, Owner Account Summary.
- **Reports or dashboard metrics it contributes to:** Household headcount and total population per section.

#### House Staff

- **Main data fields:** Staff record ID, property, full name, role (Helper, Driver, Caregiver, Gardener, Nanny, Cook), phone, employer/resident, start date, end date, ID type, ID reference, HOA staff pass no., pass expiry, status, remarks.
- **Required actions:** Add, edit, renew pass, deactivate.
- **Statuses/workflow states:** Active, Inactive; pass shown as valid, expiring (within 45 days) or expired.
- **Important validation rules:** Pass number unique; valid ID type and reference required; expiry after start date; deactivating sets the end date.
- **Related modules:** Gate Check, Residents, Other Charges (pass fee if any), Needs Attention.
- **Reports or dashboard metrics it contributes to:** Active staff count, passes expired or expiring in 45 days.

#### Vehicles & Stickers

- **Main data fields:** Vehicle ID, property, owner/driver, vehicle type (Car, SUV, Van, Pickup, Motorcycle), make, model, color, plate no.; per sticker year: sticker no., sticker year, issue date, expiration date; parking assignment (Garage, Driveway, Street, None), status, remarks.
- **Required actions:** Register vehicle, issue or renew sticker (creates a ₱600 Car Sticker charge), cancel, change parking assignment.
- **Statuses/workflow states:** Active, Expired, Cancelled.
- **Important validation rules:** Plate unique among active vehicles (compared without spaces); sticker number unique; one sticker per vehicle per year; expiry defaults to December 31 of the sticker year; issuance rules for unpaid fee or delinquent accounts per policy.
- **Related modules:** Gate Check, Other Charges, Payments, Properties, Residents.
- **Reports or dashboard metrics it contributes to:** Active stickers, renewals this year, expired or expiring (Needs Attention), sticker revenue.

### Billing

#### Monthly Dues

- **Main data fields:** Statement ID (SOA-YYYYMM-NNN), property, billing month, billing year, regular dues, special assessment, penalties, previous balance, credits/adjustments, total amount due (calculated), due date, amount paid (sum of allocations), balance (calculated), status (calculated), remarks.
- **Required actions:** Generate next period's statements for all properties; adjust (credit, penalty waiver) with reason; print or export the statement of account; filter by status; see "Balance of shown statements".
- **Statuses/workflow states:** Unpaid, Partial, Paid, Overdue (past due date, nothing paid), Carried Forward (older statement whose balance moved to a newer one), Settled (carried-forward balance later paid).
- **Important validation rules:** One statement per property per period; periods generated in sequence and only once; total = regular + special + penalties + previous − credits; penalty = penalty % × previous balance; amounts ≥ 0; generated statements locked — changes only through approved adjustments.
- **Related modules:** Payments, Properties (standing), Owner Account Summary, Correspondence (demand letters), Dashboard.
- **Reports or dashboard metrics it contributes to:** Billed vs collected by month, collection rate, aging buckets, delinquency count, total outstanding.

#### Payments

- **Main data fields:** Payment No., property, payment date, OR no., amount, payment for (Monthly Dues, Car Sticker, Facility Rental, Violation Fine, Special Assessment, Clearance Fee, Other), method (Cash, Check, GCash, Maya, Bank Transfer), reference no., received by, billing period covered, remarks, receipt file; allocation lines (statement or charge, amount).
- **Required actions:** Record payment and issue OR, print OR, attach proof (GCash screenshot, deposit slip), request void, approve void.
- **Statuses/workflow states:** Posted, Voided (new); for checks: Pending Clearance, Cleared, Bounced (Should-have).
- **Important validation rules:** Amount > 0; OR number unique; reference no. required for non-cash; check no. and bank for checks; date not in the future; allocations total the payment amount; overpayment becomes credit; posted payments never edited.
- **Related modules:** Monthly Dues, Other Charges, Transactions, Facility Use, Owner Account Summary, Audit Log.
- **Reports or dashboard metrics it contributes to:** Collections by month chart, daily cashier report, collections by method, unapplied credits, payments recorded count.

#### Other Charges

- **Main data fields:** Charge ID, property, charge date, charge type (Car Sticker, Gate Pass, Construction Bond, Clearance Fee, Violation Fine, Damage Charge, Other), description, amount, due date, payment status, related OR/payment no., source record (sticker, violation, transaction, reservation), remarks.
- **Required actions:** Add manually; created automatically by stickers, violations, transactions and reservations; settle through a payment; waive (approval); refund a construction bond.
- **Statuses/workflow states:** Unpaid, Paid, Waived (+ Refunded for bonds and deposits).
- **Important validation rules:** Amount > 0; status becomes Paid only through a payment allocation; waiver needs reason and Administrator approval; an auto-created charge cannot be deleted while its source record exists.
- **Related modules:** Payments, Vehicles & Stickers, Violations, Transactions, Facility Use, Owner Account Summary.
- **Reports or dashboard metrics it contributes to:** Unpaid charges (part of total outstanding), fines issued vs collected, bonds and deposits held.

#### Owner Account Summary

- **Main data fields:** Input: Owner ID (with a suggestion list of current owners). Output: owner name, record status, phone, email, TCT; linked property, Blk/Lot, street, property status; HOA standing, outstanding, total payments, active vehicles, active staff, family records, open requests, open violations; ten record sections (dues, payments, transactions, charges, vehicles, family, staff, requests, violations, correspondence).
- **Required actions:** Look up by Owner ID, open the property, print or export the summary as PDF (for clearance or demand letters).
- **Statuses/workflow states:** Read-only view; shows standing.
- **Important validation rules:** Unknown Owner ID shows a clear "no owner with this ID" message; a Former owner's summary is limited to their tenure dates (to confirm).
- **Related modules:** All Billing modules plus Property & People and Office modules.
- **Reports or dashboard metrics it contributes to:** No metrics of its own; it is the printable statement used at the counter.

### Office

#### Transactions

- **Main data fields:** Transaction No., property, date, transaction type (Clearance Request, Gate Pass, Construction Permit, Move-in Processing, Move-out Clearance, Sticker Application, Certificate of Residency, Document Request), requested by, description, amount (default from type), assigned staff, status, OR no., reference no., supporting document, notes.
- **Required actions:** Create, assign, start, complete, cancel with reason; print the clearance, certificate, gate pass or permit.
- **Statuses/workflow states:** Pending, In Progress, Completed, Cancelled.
- **Important validation rules:** Fee pre-filled from type and creates a charge; Completed requires the OR when amount > 0; clearance and move-out clearance require Good Standing (policy to confirm); construction permit requires a bond charge.
- **Related modules:** Payments, Other Charges, Residents (move-in/out), Vehicles (sticker application), Correspondence.
- **Reports or dashboard metrics it contributes to:** Pending transactions (Needs Attention, sidebar badge), turnaround days, fees collected by type.

#### Requests & Complaints

- **Main data fields:** Case No., property, date filed, category (Maintenance, Streetlight, Noise, Garbage, Security, Pets, Drainage, Neighbor Dispute, Other), description, filed by, assigned to, priority (Low, Medium, High, Urgent), status, action taken, resolution date, attachments, remarks; update history.
- **Required actions:** Log, assign, add update, resolve, close, reopen; convert to a violation when a rule was broken.
- **Statuses/workflow states:** Open, In Progress, Resolved, Closed.
- **Important validation rules:** Resolved requires action taken and resolution date; resolution date ≥ filed date; Urgent notifies Administrator; Closed is read-only until reopened.
- **Related modules:** Violations, Correspondence, Properties, Gate Check (guard-logged incidents).
- **Reports or dashboard metrics it contributes to:** Open cases by priority (dashboard panel, sidebar badge), average resolution days, cases by category.

#### Violations

- **Main data fields:** Violation No., property, date, violation type (Illegal Parking, Unapproved Construction, Improper Garbage Disposal, Pet Nuisance, Overgrown Lot, Noise After Quiet Hours, Late Renovation Hours), description, notice issued date, compliance deadline, fine/penalty, status, resolution, resolution date, supporting documents, remarks.
- **Required actions:** Record, issue notice (creates a Violation Fine charge and a Notice correspondence entry), mark complied, escalate, close, waive fine (approval).
- **Statuses/workflow states:** Open, Notice Sent, Complied, Escalated, Closed.
- **Important validation rules:** Deadline ≥ notice date (default +15 days); fine defaults from the violation type schedule; Escalated only after the deadline passes and needs Administrator approval; Complied requires resolution text and date; repeat offenses flagged.
- **Related modules:** Other Charges, Correspondence, Requests & Complaints, Properties.
- **Reports or dashboard metrics it contributes to:** Violations awaiting compliance (Needs Attention, badge), overdue deadlines, fines issued vs collected, violations by type.

#### Facility Use

- **Main data fields:** Reservation No., property, facility (Clubhouse, Function Hall, Basketball Court, Swimming Pool, Covered Court), reservation date, event date, start and end time (new), purpose, guests (new), fees, deposit, payment status, approval status, approved by, remarks.
- **Required actions:** Book, approve or decline, cancel, record fee and deposit payment, refund deposit or apply damage charge.
- **Statuses/workflow states:** Approval: Pending, Approved, Declined, Cancelled. Payment: Unpaid, Partially Paid, Paid, Refunded.
- **Important validation rules:** No overlap with another Pending or Approved booking of the same facility; event date not in the past when booking; only Administrator approves; deposit refund only after the event; delinquent-account rule per policy.
- **Related modules:** Payments, Other Charges, Properties, Dashboard.
- **Reports or dashboard metrics it contributes to:** Upcoming reservations panel, reservations pending approval (Needs Attention, badge), utilization and revenue per facility.

#### Correspondence

- **Main data fields:** Correspondence ID, property, date, type (Notice, Letter, Demand Letter, Memo, Email, Circular), subject, sender, recipient, related case or transaction, acknowledgment status, attachment, notes.
- **Required actions:** Log incoming or outgoing; generate a demand letter or violation notice from a template; batch demand letters; mark acknowledged; attach the signed PDF; circular to all owners (Should-have).
- **Statuses/workflow states:** Pending, Acknowledged, No Response.
- **Important validation rules:** Recipient defaults to the current owner; related reference must exist; demand letters only for accounts With Arrears or Delinquent; No Response set after a configurable number of days.
- **Related modules:** Monthly Dues, Violations, Requests, Transactions, Owners.
- **Reports or dashboard metrics it contributes to:** Demand letters without response (Needs Attention), letters sent per month.

### Admin and cross-cutting

#### Audit Log

- **Main data fields:** Log ID, timestamp, user, action (Create, Update, Payment, plus Void, Approve, Login, Export), module, record ID, property, old value/details, new value/details, IP/device notes.
- **Required actions:** View, filter (user, module, action, date range, property), export (Administrator); shown as "Change history" in each record drawer and as the property's Audit trail tab.
- **Statuses/workflow states:** None; entries are permanent.
- **Important validation rules:** Append-only at app and database level; written in the same transaction as the change it records; only changed fields stored in old/new; timestamps in Asia/Manila.
- **Related modules:** Every module.
- **Reports or dashboard metrics it contributes to:** Activity by user, financial changes and voids, after-hours edits (board audit).

#### Roles & Access

- **Main data fields:** Users (name, username/email, role, status, last login, 2FA on/off); roles; permissions; the module × role matrix shown in the prototype; device labels (Office PC-01, Office PC-02, Guardhouse tablet).
- **Required actions:** Create user, assign role, deactivate, reset password, view matrix; record-keeping rules panel.
- **Statuses/workflow states:** User Active, Disabled.
- **Important validation rules:** At least one active Administrator at all times; users cannot change their own role; password policy; 2FA required for Administrator and Treasurer.
- **Related modules:** Audit Log, all modules (permissions).
- **Reports or dashboard metrics it contributes to:** Active users, last login per user.

#### Global Search

- **Main data fields:** One input in the top bar; searches property Blk/Lot, address, account no., Property ID, current owner names, non-owner resident names, vehicle plates (spaces ignored). Each result shows a label and context ("Owner · Blk 2 Lot 4").
- **Required actions:** Type 2+ characters → up to 8 results → open the property profile; keyboard navigation; Esc closes.
- **Statuses/workflow states:** None.
- **Important validation rules:** Results filtered by role (no owner results for Security Guard); 300 ms debounce; output escaped; indexed columns only.
- **Related modules:** Properties, Owners, Residents, Vehicles & Stickers.
- **Reports or dashboard metrics it contributes to:** None.

#### Notifications / Needs Attention

- **Main data fields:** Counts — pending office transactions; violations awaiting compliance (Open, Notice Sent, Escalated); stickers and staff passes expired or expiring within 45 days; demand letters without response; reservations pending approval. Sidebar badges — open requests, open violations, pending transactions, pending reservations. In-app notifications — approvals waiting, urgent cases, statement generation finished.
- **Required actions:** Click a count to open the pre-filtered list; mark notifications read; daily email digest (Should-have).
- **Statuses/workflow states:** Notifications: Unread, Read. Counts are live.
- **Important validation rules:** Each count uses the exact query scope of the list it opens; items shown only to roles that can act on them; warning window (45 days) from settings.
- **Related modules:** Transactions, Violations, Vehicles, House Staff, Correspondence, Facility Use, Requests, Payments.
- **Reports or dashboard metrics it contributes to:** It is the action list on the dashboard.

## Database schema summary

About 35 tables. Every business table has an integer primary key, a unique display `code` (PRP-0001, SOA-202610-001), `property_id` where it belongs to a lot, `created_by`/`updated_by`, timestamps and soft deletes (archive); money is DECIMAL(12,2).

| Table | Phase | Key columns | Links to |
| --- | --- | --- | --- |
| users, roles, permissions (+ pivots) | 1 | name, email, status, 2FA | audit\_logs |
| audit\_logs | 1 | user\_id, action, module, auditable type/id, property\_id, old\_values, new\_values (JSON), ip, device | all |
| settings | 1 | dues\_rate\_per\_sqm (10.00), penalty\_pct (2.00), due\_day (15), expiry\_warning\_days (45) | billing |
| sequences | 1 | key, prefix, next\_number | all display codes |
| attachments | 1 | attachable type/id, path, label, uploaded\_by | owners, residents, staff, payments, cases, violations, correspondence |
| streets, sections, devices | 1 | name, sort order | properties, audit\_logs |
| transaction\_types, charge\_types, violation\_types, request\_categories, facilities | 1 | name, default\_fee / fine / deposit, active | Office and Billing modules |
| properties | 2 | block, lot (unique pair), house\_no, street\_id, section\_id, type, lot\_area, status, account\_no, membership\_no, current\_owner\_id, current\_resident\_id | almost every table |
| owners | 2 | property\_id, name, contacts, ownership\_status, tct\_no, tax\_dec\_no, deed\_ref, acquired, registered, verified\_at, start\_date, end\_date, record\_status | properties, correspondence |
| residents | 2 | property\_id, name, relationship, occupancy\_type, move\_in, move\_out, emergency contact, status | family\_members, house\_staff, vehicles |
| family\_members | 2 | property\_id, resident\_id, name, relationship, birth\_date, emergency\_contact, status | residents |
| house\_staff | 2 | property\_id, resident\_id, name, role, id\_type, id\_ref, pass\_no, pass\_expiry, status | gate\_logs |
| vehicles | 2 | property\_id, driver, type, make, model, color, plate (normalized, indexed), parking, status | vehicle\_stickers |
| vehicle\_stickers | 2/5 | vehicle\_id, sticker\_no, year, issued\_on, expires\_on, status, charge\_id | charges |
| billing\_periods | 3 | year, month, generated\_at, generated\_by | dues\_statements |
| dues\_statements | 3 | property\_id, billing\_period\_id, regular, special, penalty, previous\_balance, credits, due\_date, status | payment\_allocations, statement\_adjustments |
| statement\_adjustments | 3 | statement\_id, amount, reason, requested\_by, approved\_by, approved\_at | dues\_statements |
| payments | 3 | property\_id, paid\_on, or\_no (unique), amount, method, reference\_no, received\_by, status (Posted/Voided), void\_reason, voided\_by | payment\_allocations |
| payment\_allocations | 3 | payment\_id, allocatable type/id (statement or charge), amount | payments, dues\_statements, charges |
| charges | 3 | property\_id, charge\_type\_id, source type/id, description, amount, due\_date, status, waived\_by | payments |
| or\_series | 3 | prefix, start\_no, end\_no, next\_no, active | payments |
| office\_transactions | 4 | property\_id, transaction\_type\_id, requested\_by, amount, assigned\_to, status, payment\_id | charges, payments |
| cases, case\_updates | 4 | property\_id, category\_id, filed\_by, assigned\_to, priority, status, resolved\_on; updates: note, user | violations, correspondence |
| violations | 4 | property\_id, violation\_type\_id, notice\_date, deadline, fine, status, resolution, resolved\_on | charges, correspondence |
| correspondence, letter\_templates | 4 | property\_id, type, subject, sender, recipient, related type/id, ack\_status | owners, dues, violations, cases |
| gate\_logs | 5 | device\_id, guard\_id, query, verdict, matched type/id, host property\_id, purpose, time\_in, time\_out | vehicles, house\_staff, residents |
| facility\_reservations | 6 | property\_id, facility\_id, event\_date, start\_time, end\_time, purpose, guests, fee, deposit, approval\_status, approved\_by, payment\_status | charges, payments |
| notifications | 7 | Laravel database notifications | users |
| import\_batches, import\_errors | 8 | source tab, rows, errors, status | staging only |

Statement totals, balances, statuses and property standing are calculated by one `AccountService` (cached per request), never stored as editable columns. A nightly job can snapshot balances for reports if queries grow slow.

## Recommended MVP scope

The MVP is everything an HOA office needs to stop using the spreadsheets: all 20 screens in the artifact at the depth below, internal users only, launched at week 19.

| Area | In the MVP | After MVP |
| --- | --- | --- |
| Foundation | Login, 4 roles, permission matrix, financial masking, audit log, settings, lookup lists | Single sign-on, IP restrictions |
| Property & people | All six modules, property profile with 7 tabs, ownership transfer, attachments | Bulk edit, photo ID capture by webcam |
| Billing | Statement generation, record payment with OR, allocations, voids and waivers with approval, other charges, account summary, printable SOA and OR | Online payment (GCash/Maya/bank) with auto-posting, check clearing workflow, emailed SOAs, accounting export (GL) |
| Office | Transactions with fees, requests and complaints, violations with notices and fines, correspondence log | Letter templates and batch demand letters, printed certificates and permits, SMS notices |
| Gate | Gate Check lookup, sticker issuance and renewal, staff pass renewal | Gate log, visitor pre-registration and QR codes, RFID or plate-recognition integration, offline mode |
| Facility | Booking, conflict check, approval, fees and deposits, refund | Calendar view, resident self-booking |
| Dashboard and search | KPIs, collections chart, arrears panel, open cases, upcoming reservations, Needs Attention, sidebar badges, global search, in-app notifications | Daily email digest, board report pack, extra charts and trends |
| Launch | Tests, spreadsheet import, opening balances, training, backups, hypercare | — |

### Deferred after MVP

- **Resident self-service portal:** owners view their statement, pay online, file requests, book facilities.
- **Online payments:** GCash, Maya and bank integrations with automatic posting and receipts.
- **Messaging:** SMS and email notices (due reminders, sticker renewal, violation notices) and the daily staff digest.
- **Gate extensions:** visitor log and pre-registration, QR guest passes, RFID sticker readers, offline-capable guard app.
- **Finance extensions:** expenses, budget vs actual, general-ledger export to the HOA's accountant, check clearing.
- **Document generation:** templated clearances, certificates, permits and batch demand letters.
- **Governance:** member voting for elections and assemblies, board minutes, circular distribution.
- **Analytics:** trend reports, collection forecasting, multi-phase support if Green Meadows Phase 2 joins later.

## Delivery timeline

With two developers the MVP goes live at the end of week 19 (about 4.5 months), followed by 4 weeks of hypercare; a single developer would need roughly 28–32 weeks.

&#91;embedded content: delivery timeline · 8 phases, go-live week 19\]

Phase 5 runs alongside Phase 4 on the second developer. Data-migration dry-runs start in week 6 and continue in the background until cut-over. Add 1–2 weeks of contingency if the open questions below are still unanswered at kickoff.

## Risks, assumptions and open questions

### Key risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Spreadsheet data is incomplete or inconsistent (duplicate owners, missing lot areas, balances that don't add up) | Wrong opening balances; loss of trust on day one | Start the import dry-runs in week 6, not week 17; reconciliation report per account; Treasurer signs off |
| Billing rules are unclear (penalty compounding, allocation order, special assessments, advance payments) | Rework of Phase 3, disputes with owners | Write the rules as test cases and get board sign-off before Phase 3 starts |
| Official receipts: system-printed ORs may need registration with the BIR | Receipts not valid for owners who need them | Confirm with the HOA's accountant; fallback is recording the pre-printed booklet OR number |
| Personal data (IDs, birth dates of minors, title numbers) falls under the Data Privacy Act of 2012 | Compliance exposure | Role masking, encrypted backups, privacy notice, retention rules, named data protection officer |
| Guardhouse internet or device is unreliable | Gate Check unusable at peak hours | Test the connection early; mobile data backup; offline mode deferred but planned |
| Scope creep from the resident portal and online payments | MVP slips | Hold them for release 2; change requests go through the board |
| One-developer dependency | Delays from absence | Two developers, code reviews, written setup notes |

### Assumptions

- One HOA (Green Meadows Phase 1), one office; staff-only system, no resident logins in the MVP.
- Property count in the low hundreds to about 1,000 (the prototype uses 30 sample lots in 5 blocks, 3 sections).
- Currency is Philippine peso; time zone Asia/Manila.
- Dues = lot area × ₱10 per sqm monthly, due on the 15th, 2% penalty on the carried-forward balance, as in the prototype.
- Standing thresholds: With Arrears below 3 months of regular dues overdue, Delinquent at 3 months or more.
- Payments are received at the office (cash, check, GCash, Maya, bank transfer) and entered by the Treasurer.
- Team: 2 Laravel developers, part-time QA and project lead; HOA availability of about 2 hours a week for reviews.

### Questions to answer before development starts

- [ ] How many properties, blocks and sections are there, and is every lot area confirmed?
- [ ] Is the 2% penalty simple (on the carried balance only) or compounding, and is there a grace period?
- [ ] Which items does a payment settle first: oldest statement, penalties, or charges? Can owners choose?
- [ ] How are advance payments and overpayments handled — credit to next month or refund?
- [ ] Does the HOA issue BIR-registered official receipts, and must the system print them or only record booklet numbers?
- [ ] Who handles cash: only the Treasurer, or also Office Staff as cashier? (Changes the matrix.)
- [ ] Who approves voids, waivers, violation escalations and facility bookings — Administrator alone, or a board officer?
- [ ] Does arrears block clearances, sticker issuance or facility bookings?
- [ ] Sticker rules: fee, renewal deadline, one sticker per vehicle per year, replacement for lost stickers?
- [ ] Facility rules: hours, max guests, cancellation and deposit refund policy?
- [ ] Which spreadsheets exist today, who maintains them, and what is the cut-over month for opening balances?
- [ ] Hosting: cloud VPS or an on-site server? Who pays and who maintains backups?
- [ ] Does the guardhouse have a tablet or PC and stable internet?
- [ ] Which reports does the board need monthly, and in what format?
- [ ] Should a Former owner's account summary show only their tenure?
- [ ] Who is the data protection officer, and how long are records of moved-out residents kept?
