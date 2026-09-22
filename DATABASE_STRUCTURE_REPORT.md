# Current audit update ? 2026-09-22

The current live database contains **36 tables and 36 declared foreign-key column relationships**, with **0 orphan rows** at the time of the migration audit. All migrations, including payment transfers, are applied.

**The whole schema is not strictly 3NF.** Redundant account type/subtype, product category/subcategory, and serial product/variant relationships remain. See [DATABASE_NORMALIZATION.md](DATABASE_NORMALIZATION.md) for dependencies, recommendations, and verification results.

The report below is the historical September 18 assessment. Its 32-table count and blanket statements about normalization are superseded. JSON/serialized fields or missing foreign keys alone do not prove failure of 1NF/3NF; the current audit distinguishes these from concrete dependency issues.

---

# `nexus_erp` database structure and normalization evidence

Audit date: 2026-09-18  
Database engine: MySQL  
Evidence source: live `information_schema` metadata and live row-integrity queries  
Tables audited: 32  
Declared foreign keys audited: 28  
Orphan rows across declared foreign keys: **0**

The migration audit used a temporary machine-readable snapshot; that generated artifact was removed after verification.

## Conclusion

The fixed core business relationships are normalized and referentially valid. They use primary keys, foreign keys, unique constraints, and junction tables correctly. The database as a whole is **not strictly 100% 3NF/1NF** because it intentionally contains flexible JSON/serialized payloads and a few logical references without foreign keys.

Items preventing a strict “all tables normalized” result:

1. `contacts.custom_fields` and `products.custom_fields` store flexible arrays in `longtext` JSON. These are deliberate extension fields, but strict 1NF would place each custom field value in a child table.
2. Laravel infrastructure tables (`jobs`, `failed_jobs`, `job_batches`, `sessions`, `cache`) contain opaque payload/value columns by framework design.
3. `sessions.user_id` is indexed but has no declared foreign key to `users.id`.
4. `product_serial_numbers.sold_transaction_id` has no referenced transaction table yet.
5. `locations.price_group` is a string. If price groups become reusable managed entities, it should become a foreign key to a dedicated lookup table.
6. Product inclusive price and margin columns are stored calculated values. This is useful for transaction/display snapshots, but application code must keep them consistent with their source prices and tax.
7. Images stored as base64 `longtext` are a storage concern rather than a relationship-normalization problem.

## Relationship cardinalities

### One-to-one (1:1)

| Parent | Child | Evidence |
|---|---|---|
| `business_settings` | `business_product_settings` | FK `business_setting_id`; unique index on that FK |

### One-to-many (1:M)

| One side | Many side | Foreign key | Delete behavior |
|---|---|---|---|
| `roles` | `users` | `users.role_id` | RESTRICT |
| `roles` | `role_permissions` | `role_permissions.role_id` | CASCADE |
| `users` | `contacts` | `contacts.assigned_to` | SET NULL |
| `users` | `passkeys` | `passkeys.user_id` | CASCADE |
| `customer_groups` | `contacts` | `contacts.customer_group_id` | SET NULL |
| `units` | `products` | `products.unit_id` | RESTRICT |
| `units` | child/sub-units | `units.base_unit_id` | RESTRICT |
| `units` | `business_product_settings` | `default_unit_id` | SET NULL |
| `brands` | `products` | `products.brand_id` | SET NULL |
| `categories` | `products` as category | `products.category_id` | SET NULL |
| `categories` | `products` as subcategory | `products.subcategory_id` | SET NULL |
| parent `categories` | child `categories` | `categories.parent_id` | SET NULL |
| `products` | `product_variants` | `product_variants.product_id` | CASCADE |
| `variation_templates` | `variation_template_values` | `variation_template_id` | CASCADE |
| `variation_template_values` | `product_variants` | `variation_template_value_id` | RESTRICT |
| `products` | `product_serial_numbers` | `product_id` | RESTRICT |
| `locations` | `product_serial_numbers` | `location_id` | RESTRICT |
| `product_variants` | `product_serial_numbers` | `product_variant_id` | RESTRICT |
| `invoice_schemes` | `locations` | `invoice_scheme_id` | RESTRICT |
| `invoice_layouts` | `locations` as POS layout | `invoice_layout_pos_id` | RESTRICT |
| `invoice_layouts` | `locations` as sale layout | `invoice_layout_sale_id` | RESTRICT |
| `invoice_layouts` | `invoice_layout_labels` | `invoice_layout_id` | CASCADE |
| `invoice_layouts` | `invoice_layout_options` | `invoice_layout_id` | CASCADE |

Logical 1:M relation without an FK: `users` to `sessions` through `sessions.user_id`.

### Many-to-many (N:M)

| Side A | Junction table | Side B | Duplicate prevention |
|---|---|---|---|
| `products` | `location_product` | `locations` | Composite PK `(product_id, location_id)` |
| combo `products` | `combo_product_items` | component `products` | Unique `(combo_product_id, item_product_id)` |

`location_product.opening_quantity` and `combo_product_items.quantity` correctly belong to the relationship row.

## Live table structure

Key notation: `PK` primary key, `UQ` unique, `FK` foreign key, `?` nullable.

### Business tables

- `brands`: `id PK`, `name UQ`, `description?`, timestamps.
- `business_settings`: `id PK`, business identity, date/currency/time-zone, accounting, precision and logo settings, timestamps.
- `business_product_settings`: `id PK`, `business_setting_id FK UQ`, `default_unit_id FK?`, SKU/expiry configuration and product feature flags, timestamps.
- `categories`: `id PK`, `name UQ`, `code?`, `description?`, `parent_id FK?`, timestamps.
- `contacts`: `id PK`, `contact_id UQ`, type/entity/name/contact/address/balance fields, `assigned_to FK?`, `customer_group_id FK?`, `custom_fields` JSON text, timestamps.
- `customer_groups`: `id PK`, `name UQ`, calculation type/percentage and selling-price group, timestamps.
- `locations`: `id PK`, `code UQ`, name/address fields, price group, `invoice_scheme_id FK`, `invoice_layout_pos_id FK`, `invoice_layout_sale_id FK`, active flag, timestamps.
- `invoice_schemes`: `id PK`, `name UQ`, format, optional prefix, start/current number, digit count, default flag, timestamps.
- `invoice_layouts`: `id PK`, `name UQ`, default flag, timestamps.
- `invoice_layout_labels`: `id PK`, `invoice_layout_id FK`, label key/value; unique `(invoice_layout_id, key)`.
- `invoice_layout_options`: `id PK`, `invoice_layout_id FK`, option key/enabled flag; unique `(invoice_layout_id, key)`.
- `products`: `id PK`, `code UQ`, `sku_key UQ`, `unit_id FK`, `brand_id FK?`, `category_id FK?`, `subcategory_id FK?`, type/stock/tax/price/attachment/custom fields, timestamps.
- `product_variants`: `id PK`, `product_id FK`, `variation_template_value_id FK`, `sku UQ`, price/image fields, timestamps; unique `(product_id, variation_template_value_id)`.
- `product_serial_numbers`: `id PK`, `product_id FK`, `location_id FK`, `product_variant_id FK?`, `serial_number UQ`, `serial_key UQ`, status, `sold_transaction_id?`, timestamps.
- `roles`: `id PK`, `name UQ`, `description?`, timestamps.
- `role_permissions`: `id PK`, `role_id FK`, permission; unique `(role_id, permission)`.
- `units`: `id PK`, `name UQ`, `short_name UQ`, decimal flag, `base_unit_id FK?`, multiplier, timestamps.
- `users`: `id PK`, `username UQ`, `email UQ`, `role_id FK?`, identity/authentication/status/two-factor fields, timestamps.
- `variation_templates`: `id PK`, `name UQ`, timestamps.
- `variation_template_values`: `id PK`, `variation_template_id FK`, value, sort order; unique `(variation_template_id, value)`.
- `warranties`: `id PK`, `name UQ`, description, duration and duration type, timestamps. No product FK currently exists.

### Junction tables

- `location_product`: composite PK/FKs `(product_id, location_id)`, `opening_quantity`.
- `combo_product_items`: `id PK`, `combo_product_id FK`, `item_product_id FK`, quantity, timestamps; unique product pair.

### Laravel infrastructure tables

- `cache`: `key PK`, value, expiration.
- `cache_locks`: `key PK`, owner, expiration.
- `failed_jobs`: `id PK`, `uuid UQ`, connection/queue/payload/exception/failure time.
- `jobs`: `id PK`, queue/payload/attempt and scheduling fields.
- `job_batches`: `id PK`, batch counters, failed IDs/options and lifecycle times.
- `migrations`: `id PK`, migration name and batch.
- `passkeys`: `id PK`, `user_id FK`, `credential_id UQ`, credential/name/use timestamps.
- `password_reset_tokens`: `email PK`, token and creation time.
- `sessions`: `id PK`, indexed `user_id?`, IP/user-agent/payload/activity; no user FK.

## Integrity evidence

All 28 declared foreign-key joins returned zero child rows without a matching parent. This includes user roles, role permissions, assigned contacts, customer groups, product references, categories, units, variations, serial numbers, passkeys, business settings, invoice schemes/layout labels/options, combo items, and product/location assignments.

