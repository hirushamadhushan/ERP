<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariationTemplate extends Model
{
    use HasFactory;
    private array $pendingValues = [];

    protected $fillable = [
        'name',
        'values',
    ];
    protected $appends = ['values'];
    public function valueRecords() { return $this->hasMany(VariationTemplateValue::class)->orderBy('sort_order'); }
    public function getValuesAttribute(): array { return $this->valueRecords->pluck('value')->all(); }
    public function setValuesAttribute($values): void { $this->pendingValues = array_values((array) $values); }
    protected static function booted(): void
    {
        static::saved(function (self $template) {
            if ($template->pendingValues !== []) $template->syncValues($template->pendingValues);
        });
    }
    public function syncValues(array $values): void
    {
        foreach (array_values(array_unique($values)) as $order => $value) {
            $this->valueRecords()->updateOrCreate(['value' => $value], ['sort_order' => $order]);
        }
        $this->valueRecords()->whereNotIn('value', $values)->delete();
        $this->unsetRelation('valueRecords');
    }
}
