<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\DeliveryVehicle;
use App\Models\Product;
use App\Models\SalesDispatch;
use App\Models\SalesDispatchItem;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesDispatchSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure Vehicle PK-5656 exists
        $vehicle = DeliveryVehicle::firstOrCreate(
            ['number' => 'PK-5656'],
            ['name' => 'PK-5656', 'model' => 'Isuzu Elf', 'is_active' => true]
        );

        $otherVehicles = DeliveryVehicle::pluck('id', 'number')->toArray();

        // 2. Ensure Chamika Samarakoon customer exists
        $customer = Contact::firstOrCreate(
            ['name' => 'Chamika Samarakoon', 'type' => 'customer'],
            ['contact_id' => 'CUST-0810', 'status' => 'active', 'mobile' => '0771234567']
        );

        // 3. Ensure Dammika Perera user exists
        $firstRole = \App\Models\Role::first();
        $salesperson = User::firstOrCreate(
            ['email' => 'dammika@codezaerp.com'],
            ['name' => 'Dammika Perera', 'password' => bcrypt('password'), 'role_id' => $firstRole ? $firstRole->id : null, 'status' => 'active']
        );

        // Clear existing dispatches if re-seeding
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        SalesDispatchItem::truncate();
        SalesDispatch::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // The exact top 10 rows from Image 1
        $exactRows = [
            [
                'dispatch_number' => '0241',
                'order_number' => null,
                'invoice_number' => '0810',
                'invoice_date' => '2026-10-26',
                'dispatch_date' => '2026-10-26',
                'status' => 'Delivered',
                'customer_name' => 'Chamika Samarakoon',
                'salesperson_name' => 'Dammika Perera',
                'vehicle_number' => 'PK-5656',
                'vehicle_id' => $vehicle->id,
                'items' => [
                    ['item_code' => '0012386', 'item_name' => 'Seat', 'inv_qty' => 1, 'qty' => 1],
                ],
            ],
            [
                'dispatch_number' => '0240',
                'order_number' => null,
                'invoice_number' => '0797',
                'invoice_date' => '2026-10-22',
                'dispatch_date' => '2026-10-22',
                'status' => 'Pending',
                'customer_name' => 'Sunil Traders',
                'salesperson_name' => 'Dammika Perera',
                'vehicle_number' => 'WP-CAB-4587',
                'items' => [
                    ['item_code' => '0012450', 'item_name' => 'Brake Pad Set', 'inv_qty' => 4, 'qty' => 4],
                ],
            ],
            [
                'dispatch_number' => '0239',
                'order_number' => '0110',
                'invoice_number' => '0755',
                'invoice_date' => '2026-10-12',
                'dispatch_date' => '2026-10-12',
                'status' => 'Dispatched',
                'customer_name' => 'Nimal Perera',
                'salesperson_name' => 'Mr kasun perera',
                'vehicle_number' => 'CP-NA-3098',
                'items' => [
                    ['item_code' => '0012110', 'item_name' => 'Shock Absorber Rear', 'inv_qty' => 2, 'qty' => 2],
                    ['item_code' => '0012112', 'item_name' => 'Coil Spring', 'inv_qty' => 2, 'qty' => 2],
                ],
            ],
            [
                'dispatch_number' => '0237',
                'order_number' => null,
                'invoice_number' => '0749',
                'invoice_date' => '2026-10-07',
                'dispatch_date' => '2026-10-07',
                'status' => 'Dispatched',
                'customer_name' => 'Kasun Perera',
                'salesperson_name' => 'user1',
                'vehicle_number' => 'SP-LL-8452',
                'items' => [
                    ['item_code' => '0012890', 'item_name' => 'Oil Filter Premium', 'inv_qty' => 10, 'qty' => 10],
                ],
            ],
            [
                'dispatch_number' => '0236',
                'order_number' => null,
                'invoice_number' => '0742',
                'invoice_date' => '2026-10-03',
                'dispatch_date' => '2026-10-03',
                'status' => 'Delivered',
                'customer_name' => 'hirusha madushan',
                'salesperson_name' => 'Dammika Perera',
                'vehicle_number' => 'NW-DAA-5196',
                'items' => [
                    ['item_code' => '0012386', 'item_name' => 'Seat', 'inv_qty' => 2, 'qty' => 2],
                ],
            ],
            [
                'dispatch_number' => '0235',
                'order_number' => null,
                'invoice_number' => '0731',
                'invoice_date' => '2026-09-30',
                'dispatch_date' => '2026-09-30',
                'status' => 'Pending',
                'customer_name' => 'Nimali Fernando',
                'salesperson_name' => 'venura',
                'vehicle_number' => 'WP-LD-7621',
                'items' => [
                    ['item_code' => '0012999', 'item_name' => 'Clutch Plate Assembly', 'inv_qty' => 1, 'qty' => 1],
                ],
            ],
            [
                'dispatch_number' => '0234',
                'order_number' => null,
                'invoice_number' => '0728',
                'invoice_date' => '2026-09-30',
                'dispatch_date' => '2026-09-30',
                'status' => 'Delivered',
                'customer_name' => 'Chamika Samarakoon',
                'salesperson_name' => 'Dammika Perera',
                'vehicle_number' => 'PK-5656',
                'items' => [
                    ['item_code' => '0012550', 'item_name' => 'Air Filter', 'inv_qty' => 5, 'qty' => 5],
                ],
            ],
            [
                'dispatch_number' => '0233',
                'order_number' => '0107',
                'invoice_number' => '0726',
                'invoice_date' => '2026-09-30',
                'dispatch_date' => '2026-09-30',
                'status' => 'Pending',
                'customer_name' => 'Sunil Traders',
                'salesperson_name' => 'Sithum',
                'vehicle_number' => 'WP-CAB-4587',
                'items' => [
                    ['item_code' => '0012678', 'item_name' => 'Engine Oil 5W-30 (4L)', 'inv_qty' => 3, 'qty' => 3],
                ],
            ],
            [
                'dispatch_number' => '0232',
                'order_number' => null,
                'invoice_number' => '0723',
                'invoice_date' => '2026-09-30',
                'dispatch_date' => '2026-09-30',
                'status' => 'Pending',
                'customer_name' => 'Nimal Perera',
                'salesperson_name' => 'user1',
                'vehicle_number' => 'CP-NA-3098',
                'items' => [
                    ['item_code' => '0012700', 'item_name' => 'Headlight Bulb H4', 'inv_qty' => 8, 'qty' => 8],
                ],
            ],
            [
                'dispatch_number' => '0230',
                'order_number' => null,
                'invoice_number' => '0722',
                'invoice_date' => '2026-09-30',
                'dispatch_date' => '2026-09-30',
                'status' => 'Pending',
                'customer_name' => 'Kasun Perera',
                'salesperson_name' => 'Dammika Perera',
                'vehicle_number' => 'PK-5656',
                'items' => [
                    ['item_code' => '0012386', 'item_name' => 'Seat', 'inv_qty' => 1, 'qty' => 1],
                ],
            ],
        ];

        foreach ($exactRows as $row) {
            $vId = $row['vehicle_id'] ?? ($otherVehicles[$row['vehicle_number']] ?? $vehicle->id);
            $disp = SalesDispatch::create([
                'dispatch_number' => $row['dispatch_number'],
                'order_number' => $row['order_number'],
                'invoice_number' => $row['invoice_number'],
                'invoice_date' => $row['invoice_date'],
                'dispatch_date' => $row['dispatch_date'],
                'status' => $row['status'],
                'customer_name' => $row['customer_name'],
                'salesperson_name' => $row['salesperson_name'],
                'vehicle_number' => $row['vehicle_number'],
                'delivery_vehicle_id' => $vId,
                'customer_id' => $customer->id,
                'salesperson_id' => $salesperson->id,
            ]);

            foreach ($row['items'] as $item) {
                SalesDispatchItem::create([
                    'sales_dispatch_id' => $disp->id,
                    'item_code' => $item['item_code'],
                    'item_name' => $item['item_name'],
                    'inv_qty' => $item['inv_qty'],
                    'qty' => $item['qty'],
                ]);
            }
        }

        // Generate additional historical records descending down to 213 total entries
        $customersList = ['Chamika Samarakoon', 'Sunil Traders', 'Nimal Perera', 'Kasun Perera', 'hirusha madushan', 'Nimali Fernando'];
        $salesList = ['Dammika Perera', 'user1', 'Sithum', 'venura', 'Mr kasun perera'];
        $vehicleList = array_keys($otherVehicles) ?: ['PK-5656', 'WP-CAB-4587', 'CP-NA-3098'];
        $statuses = ['Delivered', 'Delivered', 'Pending', 'Dispatched', 'Delivered'];
        $itemCatalog = [
            ['code' => '0012386', 'name' => 'Seat'],
            ['code' => '0012450', 'name' => 'Brake Pad Set'],
            ['code' => '0012110', 'name' => 'Shock Absorber Rear'],
            ['code' => '0012890', 'name' => 'Oil Filter Premium'],
            ['code' => '0012550', 'name' => 'Air Filter'],
            ['code' => '0012678', 'name' => 'Engine Oil 5W-30 (4L)'],
            ['code' => '0012999', 'name' => 'Clutch Plate Assembly'],
        ];

        $currentNumber = 229;
        $invSeq = 720;
        $orderSeq = 105;
        $baseDate = Carbon::parse('2026-09-29');

        for ($i = 11; $i <= 213; $i++) {
            $dNum = str_pad($currentNumber, 4, '0', STR_PAD_LEFT);
            $invNum = str_pad($invSeq, 4, '0', STR_PAD_LEFT);
            $hasOrder = ($i % 4 === 0);
            $orderNum = $hasOrder ? str_pad($orderSeq, 4, '0', STR_PAD_LEFT) : null;
            if ($hasOrder) $orderSeq--;

            $date = $baseDate->copy()->subDays(intdiv($i - 10, 4))->toDateString();
            $status = $statuses[$i % count($statuses)];
            $cust = $customersList[$i % count($customersList)];
            $sales = $salesList[$i % count($salesList)];
            $veh = $vehicleList[$i % count($vehicleList)];
            $vehId = $otherVehicles[$veh] ?? $vehicle->id;

            $disp = SalesDispatch::create([
                'dispatch_number' => $dNum,
                'order_number' => $orderNum,
                'invoice_number' => $invNum,
                'invoice_date' => $date,
                'dispatch_date' => $date,
                'status' => $status,
                'customer_name' => $cust,
                'salesperson_name' => $sales,
                'vehicle_number' => $veh,
                'delivery_vehicle_id' => $vehId,
                'customer_id' => $customer->id,
                'salesperson_id' => $salesperson->id,
            ]);

            $catItem = $itemCatalog[$i % count($itemCatalog)];
            $qty = ($i % 5) + 1;

            SalesDispatchItem::create([
                'sales_dispatch_id' => $disp->id,
                'item_code' => $catItem['code'],
                'item_name' => $catItem['name'],
                'inv_qty' => $qty,
                'qty' => $qty,
            ]);

            $currentNumber--;
            $invSeq--;
        }
    }
}
