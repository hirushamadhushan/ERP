# Delivery module

Delivery sits below Products. Vehicles, Drivers and Vehicle Store share the existing purple ERP layout.

## Workflow

1. Create a vehicle (number and name required; other descriptive fields optional).
2. Create an active driver and assign an active vehicle. Each driver and vehicle has at most one current assignment. Release an assignment before replacing its driver.
3. Vehicle Store → Loading: select a permitted warehouse, vehicle, product/variation, lot or serial, and quantity in base units.
4. Confirm to commit every line atomically and obtain a printable receipt.
5. Unloading reverses the direction into a selected warehouse. Inactive vehicles and expired lots may be unloaded so stock is not stranded.

Loading requires an active driver/vehicle and blocks an expired driver license or lot. Transfers cannot overdraw their source. Repeated identical request UUIDs return the original transfer. Changed payloads using the same UUID are rejected. Product and vehicle row locks serialize transfers. Completed records are immutable; correct a transfer with an opposite transfer and reference the original receipt.

## Tables and normalization

Every table has an explicit primary key; foreign keys restrict deletion of referenced inventory history.

| Table | Primary key | Relationships / purpose |
|---|---|---|
| delivery_vehicles | id | Unique number; vehicle attributes only |
| delivery_drivers | id | Driver attributes; optional unique license number |
| delivery_vehicle_stores | vehicle_id | Unique location_id → locations; one mobile store per vehicle |
| delivery_vehicle_assignments | vehicle_id | Unique driver_id → delivery_drivers; assigned_by → users |
| delivery_transfers | id | vehicle_id, warehouse_id → locations, driver_id, unique inventory_transaction_id; unique request_key |
| delivery_transfer_lines | id | transfer_id, stock_item_id → product_stock_items; transferred quantity |
| delivery_transfer_line_lots | line_id | lot_id → product_lots |
| delivery_transfer_line_serials | line_id + serial_id | Separate serial membership, no comma-separated IDs |

Independent sets (assignments, stores, transfer lines, serial membership) are separated. Product/variation descriptions, driver details, warehouse names, and lot prices are not copied into transfer headers. Existing product_stock_items map products and variations. Transfer driver_id records the driver at execution, independently of later assignments. This decomposition avoids independent multivalued facts in a single relation (4NF design intent); it does not claim an independent formal audit of the entire legacy database.

## Inventory integration

Vehicle stores are real locations so product totals include stock both in warehouses and vehicles. Loading changes custody, not total stock. Lots keep their original identity and receive paired negative/positive inventory_movements under the transfer's inventory_transaction. Serials move location without changing identity/status. Ordinary and variation stock currently use the existing opening_quantity balance columns; the immutable delivery lines provide the transfer audit trail. A future unified ledger can replace those legacy balance columns without changing Delivery's public workflow.

No separate vehicle balance cache is maintained. Product edits preserve variation quantities; vehicle opening-stock edits and bulk vehicle-location changes are blocked. Delivery history protects product/variation deletion and stock-method/unit changes.

## Access and deployment

Permissions: delivery.view, delivery.manage, delivery.transfer. Write routes require both view and the relevant action permission. Warehouse access follows user all_locations / location assignments. The permission middleware retains the application's existing Admin and legacy no-role behavior. Fleet visibility is module-wide for users granted delivery.view.

Run `php artisan migrate --force`, `npm run build`, and normal deployment cache commands. Tests use SQLite :memory: and do not write business records into the development database.

Current scope covers fleet, assignments, loading/unloading, balances, search, pagination and transfer receipts. Route planning, customer delivery orders, proof of delivery, fuel accounting and vehicle sales are separate future workflows.
