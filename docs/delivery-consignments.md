# Delivery consignments

The ERP currently has vehicle loading/unloading but no order or picking records. A delivery consignment starts from one completed **loading** transfer and serves one customer. Order management remains outside the Delivery module.

## Records and dependencies

| Table | Key relationships | Purpose |
| --- | --- | --- |
| `delivery_consignments` | `loading_transfer_id` unique FK, `customer_id` FK, `created_by` FK | One customer delivery from one loading transfer. Stores an address snapshot and current status. Vehicle, warehouse and driver are read from the immutable loading transfer. |
| `delivery_consignment_lines` | `consignment_id` FK, `transfer_line_id` unique FK | One outcome and optional remark per loaded stock line. Delivered, damaged and missing quantities partition the loaded quantity. |
| `delivery_consignment_events` | `consignment_id` FK, `user_id` FK | Auditable status and proof actions. |
| `delivery_consignment_proofs` | `consignment_id` FK, `uploaded_by` FK | Independent private photo/signature attachments. |
| `delivery_consignment_serial_outcomes` | `consignment_line_id` + `serial_id` unique | The delivered, damaged or missing result for each loaded serial number. |
| `delivery_damage_dispositions` | consignment line, quarantine location and inventory transaction FKs | Traceable damaged stock moved into the warehouse's quarantine location. |
| `delivery_routes` / `delivery_route_stops` | vehicle, ordered consignment stops | Multiple customer deliveries carried by one vehicle, each retaining an independent allocation and POD. |
| `delivery_pod_corrections` | consignment, requester, approver and reversal transaction FKs | Supervisor-approved POD correction audit and inventory reversal. |

The independent, potentially repeated events and proofs are separate relations. Product, variant, lot and serial identities remain on existing transfer lines; consignments reference those lines instead of copying them. The customer address is intentionally captured as a historical delivery snapshot. This structure avoids multi-valued columns and keeps the new facts independently addressable.

## Stock and state rules

`loaded -> in_transit -> arrived -> delivered | partial | failed`, with `cancelled` as an audited terminal state before completion. Arrival has its own status, timestamp and event and must be recorded before POD submission. Loading uses `DeliveryStockService`. For each line, `delivered + damaged + missing = loaded`. Serial-tracked lines additionally require exactly one outcome for every loaded serial. Every outcome quantity leaves vehicle inventory; damaged stock simultaneously enters the source warehouse's quarantine location. A second completion cannot consume stock again.

Cancellation deliberately leaves physical stock on the vehicle. An authorized unloading or a corrected replacement delivery must account for that stock.

Proof images are validated and stored on the private `local` disk with captions, uploader/time, SHA-256 checksum and a seven-year retention date. Incorrect files can be hidden by `delivery.proof.manage`; the scheduled purge removes their binary only after retention expires while preserving audit metadata. A receiver signature or proof photo is required to complete POD, and every damaged or missing quantity requires a line remark. Receiver phone, optional identity reference and paired GPS coordinates are validated. Operational permissions are separated into `delivery.create`, `delivery.dispatch`, `delivery.arrive`, `delivery.pod`, `delivery.correct`, `delivery.proof.manage`, and `delivery.route.manage`.

A completed POD correction must be requested with a reason and approved by a different supervisor. Approval creates a `vehicle_delivery_reversal` transaction, restores stock to the vehicle, clears the operational outcome, retains the original JSON snapshot, and reopens the delivery at `arrived` for a new POD.

## Integration boundary

Order management and picking remain outside Delivery. Routes group separate loaded consignments for the same vehicle into ordered customer stops, keeping stock allocations and PODs independent. GPS is evidence captured at receipt time rather than continuous vehicle tracking.
