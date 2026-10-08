<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillOfMaterial extends Model
{
    protected $table = 'bills_of_materials';
    protected $fillable = ['code', 'product_id', 'output_quantity', 'is_active', 'notes', 'created_by'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function product() { return $this->belongsTo(Product::class); }
    public function items() { return $this->hasMany(BillOfMaterialItem::class)->orderBy('position'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
