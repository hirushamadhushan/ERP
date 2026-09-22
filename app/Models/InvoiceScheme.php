<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InvoiceScheme extends Model
{
    protected $fillable = ['name','format','prefix','start_number','current_number','number_of_digits','is_default'];
    protected function casts(): array { return ['is_default'=>'boolean','start_number'=>'integer','current_number'=>'integer','number_of_digits'=>'integer']; }
    public function locations() { return $this->hasMany(Location::class); }
    public function getInvoiceCountAttribute(): int { return max(0, $this->current_number - $this->start_number); }
    public function preview(?int $year = null): string
    {
        $number = str_pad((string)$this->start_number, $this->number_of_digits, '0', STR_PAD_LEFT);
        return ($this->prefix ?: '#').($this->format === 'year_number' ? ($year ?? now()->year).'-' : '').$number;
    }
}
