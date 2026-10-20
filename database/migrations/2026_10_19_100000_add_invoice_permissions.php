<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private array $permissions = ['invoice.view','invoice.create','invoice.edit','invoice.post','invoice.payment','invoice.credit','invoice.send'];

    public function up(): void
    {
        if (! Schema::hasTable('role_permissions')) return;
        $roleIds = DB::table('role_permissions')->whereIn('permission', ['customer.view','system.settings'])->pluck('role_id')->unique();
        foreach ($roleIds as $roleId) foreach ($this->permissions as $permission) {
            DB::table('role_permissions')->insertOrIgnore(['role_id'=>$roleId,'permission'=>$permission]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('role_permissions')) DB::table('role_permissions')->whereIn('permission',$this->permissions)->delete();
    }
};
