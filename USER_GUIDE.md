# Green Meadows HOA — User Knowledge Base

This guide covers the features currently available in the Green Meadows Phase 1 HOA application. It is for administrators, office staff, treasurers, and security guards. Your sidebar shows only the pages your role can use.

## Contents

- [Getting started](#getting-started)
- [Roles and access](#roles-and-access)
- [Property and people records](#property-and-people-records)
- [Billing and collections](#billing-and-collections)
- [Office work and facilities](#office-work-and-facilities)
- [Gate Check](#gate-check)
- [Search and notifications](#search-and-notifications)
- [Administration and CSV imports](#administration-and-csv-imports)
- [Common questions](#common-questions)
- [Before live use](#before-live-use)

## Getting started

1. Ask an Administrator to invite you. Public sign-up is unavailable. The invitation email contains a link to set your password.
2. Sign in with your email address and password. Use **Forgot password?** on the login page if needed.
3. Open the pages in the sidebar. Select your name/profile menu for account and security settings. You can change your password and set up two-factor authentication or a passkey there.
4. Sign out when leaving a shared office computer or guardhouse device. An idle session may also return you to the login page.

The device selector in the header lets you identify the office computer or tablet you are using. This label is included in the change history.

## Roles and access

| Role | Main work |
| --- | --- |
| **Administrator** | Manage users, settings, reference lists, imports, approvals, and all operational records. |
| **Office Staff** | Maintain properties and people; handle cases, violations, transactions, correspondence, and facility bookings. Can view billing but cannot record payments. |
| **Treasurer** | Generate statements, record payments, manage charges and financial records, and request adjustments or voids. |
| **Security Guard** | Use Gate Check and permitted household, vehicle, and request information. Financial amounts and private owner documents are hidden. |

An Administrator assigns one role to each user. The **Roles** page displays the current permissions; it does not edit them. If a page or action is missing, ask an Administrator to check your assigned role.

## Property and people records

**Properties** is the starting point for a household. Search by property code, block, lot, or status, then open a property profile. The profile brings its ownership, household, vehicles, billing, office activity, and change history together. Tabs depend on your role.

### Create a property

An Administrator or Office Staff member can select **Properties → Add property**, enter the block, lot, lot area, and any available address details, then save. The app assigns a property code such as `PRP-0001`. Use that code when linking imported people or vehicles to the property.

### Record ownership or a transfer

1. Open **Properties**, select the property, then open **Ownership**.
2. Enter the owner's name, ownership type, and start or transfer date. Phone and email can be added when available.
3. Select **Record ownership**.

The ownership history remains visible. A new sole owner ends the previous sole ownership instead of overwriting it. Check the property and effective date carefully before saving.

### Manage a household

On the **Household** tab, an Administrator or Office Staff member can move in a resident, record a move-out date, add family members, add house staff, and renew an active staff pass. Moving a resident out also deactivates linked household access records, including active staff and stickers; review them before recording the move-out.

The **Private documents** section accepts PDF, JPEG, or PNG files up to 10 MB for the current owner, current resident, or house staff. These files require authorized access and are unavailable to Security Guards. Upload only documents needed for HOA work.

### Register a vehicle and issue a sticker

On the **Vehicles** tab, register the vehicle and its plate and driver. Then select the vehicle, enter a sticker number and year, and choose **Issue sticker**. Renewal creates a new sticker record for the new year. Sticker issuance also creates a charge on the property account; confirm the current fee with the office before issuing.

The **Owners**, **Residents**, **Family Members**, **House Staff**, and **Vehicles & Stickers** sidebar pages help you find records. Open the linked property profile to make changes.

## Billing and collections

Only an Administrator or Treasurer can generate statements and record payments. Office Staff can inspect permitted billing information. Security Guards do not see it.

### Monthly statements

Open **Statements**, enter the year and month, then select **Generate statements**. Generating the same period again does not create duplicate statements. Select a statement code to open its PDF.

The starting settings are monthly dues of **₱10 per square meter of lot area**, due on the **15th**, with a **2% penalty on the carried balance**. An Administrator can change the configured rate, due day, penalty, and grace period in **Settings**. Check the current settings before explaining an amount to an owner.

A Treasurer can request a credit adjustment to a statement with an amount and reason. An Administrator reviews and approves it. Posted statements are corrected through this workflow rather than by changing their original figures.

### Record a payment and print its receipt

1. Open **Receipts** and select the property.
2. Enter the amount, payment date, method, and remarks. Available methods are Cash, Check, GCash, Maya, and Bank Transfer. Enter a reference number for a non-cash method.
3. Select **Record payment**. The app assigns a receipt number and applies the payment to older due items first. Any excess becomes advance credit.
4. Select the receipt number to open its PDF.

If a posted payment must be cancelled, the Treasurer submits **Request void** with a reason. An Administrator approves the void. Check the property, amount, date, and payment reference before posting; a payment cannot simply be edited afterward.

The generated PDF is an HOA receipt record. The HOA accountant should confirm any official receipt or tax requirements before it is used for that purpose.

### Other charges and account summary

Use **Charges** to record a permitted manual charge. Sticker, transaction, violation, and approved facility activity can also create charges automatically. A waiver requires a reason and Administrator approval; a partly paid charge cannot be waived through this action.

Use **Owner Account Summary** to review the owner's account. The property profile's **Billing** tab shows standing, outstanding amount, advance credit, statements, payments, and charges. Standing reflects overdue monthly statements: **Good Standing** means none are overdue, **With Arrears** means fewer than three are overdue, and **Delinquent** means three or more are overdue. Other unpaid charges still contribute to the amount outstanding.

## Office work and facilities

Each office record is linked to a property. Use the relevant **Community** page, select the property, complete the form, and save. Available actions vary by role.

| Page | What staff can do |
| --- | --- |
| **Transactions** | Record a service transaction and its fee; mark it complete after payment is handled. |
| **Requests & Complaints** | File a case with details and priority; record the action taken when resolving it. Guards can file and view their own entries. |
| **Violations** | Record a violation; issuing a notice creates related correspondence and any configured fine. |
| **Facility Use** | Request a date, time, purpose, and guest count. An Administrator approves or declines. Approval creates configured fees and deposits. Conflicting pending or approved bookings are rejected. |
| **Correspondence** | Record a notice, letter, demand letter, memo, email, or circular and acknowledge it when handled. |

For a facility deposit, the Treasurer or Administrator can record a refund after the event when the deposit has been paid. Enter the refund voucher or reference. **Refund deposit** records the refund in the app; staff must carry out the actual payment separately.

## Gate Check

The **Gate Check** page is available to Security Guards and Administrators. Search by plate number, sticker number, staff pass number, or household name, then select **Check**. The result displays the matching property and a status such as **VALID**, **EXPIRED**, **PASS OK**, **PASS EXP.**, **RESIDENT**, or **MOVED OUT**.

If the result is **NOT FOUND**, treat the person or vehicle as a visitor and follow the HOA's gate procedure. Gate Check is a lookup; this version does not record entry and exit events.

## Search and notifications

Use the header search field to find permitted records. Enter at least two characters. Results are filtered by your role, so a Security Guard will not receive financial results. Statement and receipt results can open their PDFs.

The bell in the header opens **Notifications**. Open a notice to review the related work, and mark it read when finished. Notifications can include urgent cases, monthly statement generation, facility approvals, and requests awaiting financial approval. Each user sees their own notifications.

## Administration and CSV imports

### Users, settings, and history

- **Users:** Invite a colleague, assign a role, or deactivate an account that should no longer sign in. An Administrator cannot deactivate their own account from this page.
- **Settings:** Set the HOA name and address used in printouts, dues rate, due day, penalty, grace period, and expiry warning window.
- **Master Data:** Maintain sections, streets, property types, transaction and charge types, violation types, request categories, and facilities with their configured fees or deposits.
- **Devices:** Name office computers and tablets so their activity can be identified in the audit log.
- **Audit Log:** Review the time, user, device, and details of recorded changes. Entries cannot be edited from the app.

### Import existing records

Only Administrators can use **Imports**. This version accepts **CSV files up to 2 MB**, with exact column headers. Select the data type, upload the CSV, and choose **Preview**. Fix every reported row error in the source file and preview it again. **Import** becomes available when the preview has no errors. Recent batches show what was imported.

| Import type | Required CSV header row |
| --- | --- |
| Properties | `block,lot,lot_area,house_no` |
| Owners | `property_code,name,ownership_type,started_on,phone,email` |
| Residents | `property_code,name,occupancy_type,moved_in_on,phone` |
| Vehicles | `property_code,driver_name,type,plate_no,make,model,color` |
| Opening Balances | `account_no,amount,as_of` |

Import **Properties first**. Afterward, use the property codes assigned by the app in owner, resident, and vehicle files. Use the assigned account numbers for opening balances. Enter dates as `YYYY-MM-DD`. Import opening balances only after checking the source totals and before normal billing activity for those properties; an opening balance is rejected if the account already has statements, payments, or charges. Preview and reconcile each batch before continuing.

For CSV imports, `ownership_type` accepts `Sole Owner` or `Co-Owner`; `occupancy_type` accepts `Owner`, `Tenant`, or `Caretaker`. These import values differ from some choices on the individual property form. Leave optional fields blank but keep their columns in the header.

The importer covers these five CSV types only. It does not accept Excel `.xlsx` files or import every sheet from the legacy workbook.

## Common questions

**Why can I not see a page, amount, or document?** Your role controls access. Ask an Administrator to verify your role if you believe access is missing. Security Guards cannot view balances, payments, or private owner documents.

**Why did my session return to the login page?** The session may have timed out. Sign in again, then reopen the page.

**Why did an import fail?** Check the exact header row, CSV format, dates, duplicates, and property or account codes shown in the preview errors. Correct the source file and preview again.

**Where can I find a PDF?** Select a statement code in **Statements** or a receipt number in **Receipts**. The PDF opens in a new browser tab; allow pop-ups for the app if your browser blocks it.

**Can I collect payments online or scan a gate QR code?** Those features are not in the current application. Record payments received through the HOA's existing channels and use the Gate Check lookup at the entrance.

## Before live use

The application still needs the HOA's real records, approved fees and policies, deployment, and an operational backup process before production use. An Administrator should confirm the settings and master data, import and reconcile the source records, and train each role on the workflows above. Keep the original source records until the imported totals and samples have been checked.
