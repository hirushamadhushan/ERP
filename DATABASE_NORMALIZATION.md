# Database normalization audit ? 2026-09-22

## Verdict

The concrete business dependencies identified in the previous audit have now been migrated to a 4NF-oriented design. The live database no longer stores duplicate parent/subtype or parent/subcategory facts, and variant-backed serials no longer duplicate their product. This is a schema design assessment, not a formal mathematical proof for framework payload tables.

Live `nexus_erp`: 36 tables, 36 declared foreign-key column relationships, zero orphan rows. All migrations are applied. Six targeted consistency checks returned zero violations. These establish current data consistency, not proof of 3NF.

## Changes applied

| Relation | Dependency / issue | Recommended design |
|---|---|---|
| `payment_accounts.selected_type_id` | One selected type determines its parent through `payment_account_types.parent_id`. | UI still shows parent/subtype; model derives them. |
| `products.selected_category_id` | One selected category determines its parent through `categories.parent_id`. | UI still shows category/subcategory; model derives them. |
| `product_serial_numbers` | Variant determines its product. | Variant-backed serials keep `product_variant_id` and leave direct `product_id` null; direct serials use `product_id`. A database check requires exactly one. |
| Calculated prices | Inclusive price and margin are derived from source price/tax. | Removed duplicated stored columns; models calculate them. |
| Custom fields | Repeated JSON arrays were independent field facts. | `product_custom_field_values` and `contact_custom_field_values` child tables store one field per row. |

The first three dependencies apply to rows with a child/variant selected; nullable fields do not remove the update-anomaly risk. A surrogate primary key and separate foreign keys alone do not eliminate these dependencies.

## Denormalization and modeling decisions (distinct from strict 3NF violations)

- `payment_accounts.current_balance` is a maintained aggregate of opening balance and movements. This cross-table cached total is denormalization, not by itself a non-key-to-non-key dependency inside the account relation. It needs reconciliation. Current balances match opening balance + inbound transfers - outbound transfers for every live account. Future POS movements must extend that reconciliation.
- `contacts.custom_fields` and `products.custom_fields` hold structured JSON. If their individual fields are modeled as separate business facts, use child rows. JSON or TEXT storage alone does not automatically prove a 1NF violation; atomicity depends on the intended domain.
- Framework session/cache/job payloads, passkey credentials, and two-factor recovery data are opaque infrastructure values; do not infer failed business normalization merely from their serialization.
- `sessions.user_id` and `product_serial_numbers.sold_transaction_id` lack declared foreign keys. This is a referential-integrity gap, not independently a 3NF violation. The sold-transaction target has not been implemented yet.
- `locations.price_group` and `customer_groups.selling_price_group` should become references if managed price-group entities are introduced.
- Base64 images and rich-text invoice headers/footers are storage/content choices, not normalization violations.

## Payment transfer assessment

`payment_account_transfers` stores one transfer fact: source/destination account FKs, amount, date, note, optional document metadata and creator FK. Debit and credit book rows are derived from that record; they are not separately duplicated. Its primary key and unique request ID are candidate keys. No non-key transitive dependency was identified under the current business rules.

`payment_account_details` stores label/value facts as child rows with a unique `(payment_account_id, label)` constraint. `payment_account_types` stores its hierarchy through a self-reference. These structures are appropriate, but do not fix the redundant type/subtype pair on `payment_accounts`.

## Verification and scope

- `php artisan migrate:status`: every migration was applied.
- The migration was verified against a pre-migration snapshot before the temporary audit scripts were removed.

This was an audit. No application schema or business records were changed. A normalization refactor must migrate existing data and update validation, models, forms, queries, and regression tests together.
