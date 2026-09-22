<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InvoiceLayout extends Model
{
    protected $fillable = ['name','design','logo_path','header_text','footer_text','is_default'];
    protected function casts(): array { return ['is_default'=>'boolean']; }
    public function labels() { return $this->hasMany(InvoiceLayoutLabel::class); }
    public function options() { return $this->hasMany(InvoiceLayoutOption::class); }
    public function posLocations() { return $this->hasMany(Location::class, 'invoice_layout_pos_id'); }
    public function saleLocations() { return $this->hasMany(Location::class, 'invoice_layout_sale_id'); }
    public function label(string $key, string $default=''): string { return (string)($this->labels->firstWhere('key',$key)?->value ?? $default); }
    public function option(string $key): bool { return (bool)($this->options->firstWhere('key',$key)?->enabled ?? false); }
}
