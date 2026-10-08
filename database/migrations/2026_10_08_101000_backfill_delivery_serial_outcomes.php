<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $rows = DB::table('delivery_consignment_lines as dcl')
            ->join('delivery_transfer_line_serials as dtls', 'dtls.line_id', '=', 'dcl.transfer_line_id')
            ->selectRaw('dcl.id as consignment_line_id, dtls.serial_id, NULL as outcome, NULL as remarks')
            ->get()->map(fn ($row) => (array) $row)->all();
        foreach (array_chunk($rows, 500) as $chunk) DB::table('delivery_consignment_serial_outcomes')->insertOrIgnore($chunk);
    }

    public function down(): void {}
};
