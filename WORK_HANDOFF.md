# Work handoff — 2026-09-11

## User constraints
- Never reset the application database. An earlier mistaken test reset deleted data; the user accepted that loss and explicitly forbade repeating it. No backup is available.
- Use isolated SQLite :memory: databases for tests with explicit connection guards. Never run migrate:fresh, refresh or seed against the application database.
- Apply only targeted additive migrations when needed. Preserve existing data.
- Keep Nexus ERP styling, purple active navigation, Bootstrap icons and mobile support.
- Do not spawn agents unless explicitly requested.

## Completed
- Removed hardcoded login bypass; login verifies database username/email and password hash. The user1 account was provisioned and verified. Do not reset its credentials.
- Sidebar has single User Management and Contacts links. Their subpages are in the top header with active states; narrow screens can scroll the header navigation. Sidebar has a mobile toggle.
- Contacts pages: Customers, Suppliers, Commission (agents and commission percentage).
- Shared contact Add/View/Edit/Delete forms, filters, DataTables exports, custom fields and shipping details.
- Customers table ends with Total Sale Due, Total Sell Return Due, Custom Field 1–10. Includes Customer Group; Status/Assigned to removed from this table.
- Suppliers table ends with Total Purchase Due, Total Purchase Return Due, Custom Field 1–10. Status/Assigned to removed from this table.
- Customer Groups CRUD, percentage/selling-price-group-name modes, customer group dropdown integration, rename propagation and deletion protection for assigned groups.
- Percentage info tooltip works on hover/focus, dismisses with Escape and stays inside the dialog; checked at 1365/390/320px.
- Import Contacts at /contacts/import: upload UTF-8 CSV, download header-only template, instructions for all 27 reference columns, max 2 MB/1,000 rows.
- Import supports 1=Customer, 2=Supplier, 3=Both. Both is one record visible in customer/supplier lists and editable through the shared form. Imported name parts combine into Name. Date of birth is persisted.
- Import validates required supplier fields, dates and amounts; duplicate IDs rejected, no overwrites, atomic save and row-number errors. BOM handling verified.

## Files
- resources/views/layouts/app.blade.php
- resources/views/contacts/{index,form,field,scripts,import}.blade.php
- resources/views/customer-groups/index.blade.php
- app/Http/Controllers/{ContactController,CustomerGroupController,ContactImportController}.php
- app/Models/{Contact,CustomerGroup}.php
- app/Services/ContactCsvImport.php
- routes/web.php
- tests/Feature/{ContactsTest,CustomerGroupsTest,ContactImportTest}.php

## Database and checks
- Targeted migrations through 2026_09_11_150000_add_date_of_birth_to_contacts.php have been applied.
- Latest relevant run: 22 tests, 291 assertions passed, using isolated in-memory databases.
- Blade compilation passed; browser checks passed for desktop/mobile import layout, 27 instruction rows, download and empty-file rejection. No contacts were imported during browser checks.
- Temporary verification scripts were removed.
- PHP test command: php vendor/phpunit/phpunit/phpunit --filter='ContactImportTest|ContactsTest|CustomerGroupsTest' --do-not-cache-result
- Standard artisan test previously failed due to sandbox cwd handling; direct PHPUnit worked.

## Known boundaries
- Sales/Purchases/product pricing integration is not implemented. Due/return/advance balances are stored fields, not automatically calculated from transactions.
- Customer-group percentage and selling-price-group name are stored configuration only; no automatic price application or separate selling price list module.
- Import accepts CSV only (not XLSX); reference template includes Custom Fields 1–4, while manual forms support 1–10.
- No further task specified yet. User plans to resume tomorrow.
