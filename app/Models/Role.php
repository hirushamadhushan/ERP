<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];
    protected $appends = ['permissions'];

    public function permissionRecords() { return $this->hasMany(RolePermission::class); }
    public function users() { return $this->hasMany(User::class); }
    public function getPermissionsAttribute(): array { return $this->permissionRecords->pluck('permission')->all(); }
    public function syncPermissions(array $permissions): void
    {
        $this->permissionRecords()->delete();
        $this->permissionRecords()->createMany(collect($permissions)->unique()->map(fn ($permission) => ['permission' => $permission])->all());
        $this->unsetRelation('permissionRecords');
    }
}
