# Delivery consignments

The ERP currently has vehicle loading/unloading but no Sales Order or Picking records. A delivery consignment therefore starts from one completed **loading** transfer and serves one customer. `sales_order_reference` is an optional external reference; it is not an ERP Sales Order foreign key.

## Records and dependencies

| Table | Key relationships | Purpose |
| --- | --- | --- |
| `delivery_consignments` | `loading_transfer_id` unique FK, `customer_id` FK, `created_by` FK | One customer delivery from one loading transfer. Stores an address snapshot and current status. Vehicle, warehouse and driver are read from the immutable loading transfer. |
| `delivery_consignment_lines` | `consignment_id` FK, `transfer_line_id` unique FK | One outcome and optional remark per loaded stock line. Delivered, damaged, missing and return quantities partition the loaded quantity. |
| `delivery_consignment_events` | `consignment_id` FK, `user_id` FK | Auditable status and proof actions. |
| `delivery_consignment_proofs` | `consignment_id` FK, `uploaded_by` FK | Independent private photo/signature attachments. |
| `delivery_returns` | `consignment_id` unique FK, optional `unloading_transfer_id` unique FK | One return note per delivery, pending until matched to a warehouse unloading transfer. |
| `delivery_return_lines` | `delivery_return_id` FK, `consignment_line_id` unique FK | Returned quantity for each delivery line. |

The independent, potentially repeated events and proofs are separate relations. Product, variant, lot and serial identities remain on existing transfer lines; consignments reference those lines instead of copying them. The customer address is intentionally captured as a historical delivery snapshot. This structure avoids multi-valued columns and keeps the new facts independently addressable.

## Stock and state rules

`loaded -> in_transit -> arrived -> delivered | partial | failed`. Arrival has its own status, timestamp and event and must be recorded before POD submission. Loading uses `DeliveryStockService`. For each line, `delivered + damaged + missing + return = loaded`. Delivered, damaged and missing units leave vehicle inventory. Return units remain on the vehicle, and a return note is created. After unloading the exact returned items to a warehouse, link that transfer to close the note. The product, lot, serial and quantity identities must match. A second completion cannot consume stock again.

Proof images are validated and stored on the private `local` disk. POD outcomes and proof database records commit together; stored files are removed if the transaction fails. Read access uses `delivery.view`; mutations use `delivery.transfer`.

## Integration boundary

The image's Sales Order, Picking, live route map and GPS stages require source records or location telemetry that do not exist in this ERP yet. The delivery dashboard therefore shows real loaded/in-transit/outcome records, and its optional sales order reference remains plain text. When Sales Orders are added, link them through a new nullable FK and explicit allocation records; do not infer an order from an invoice number or silently duplicate stock deductions. Route tracking likewise needs a route/stop model and an authorized position source before showing a map.
