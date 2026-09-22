<?php
namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

trait HasCustomFieldValues
{
    protected ?array $pendingCustomFields = null;

    public function customFieldValues()
    {
        $model=$this instanceof \App\Models\Product ? \App\Models\ProductCustomFieldValue::class : \App\Models\ContactCustomFieldValue::class;
        return $this->hasMany($model)->orderBy('display_order');
    }

    public function getCustomFieldsAttribute(): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable($this instanceof \App\Models\Product ? 'product_custom_field_values' : 'contact_custom_field_values')) return [];
        return $this->pendingCustomFields ?? $this->customFieldValues->pluck('value','field_key')->all();
    }

    public function setCustomFieldsAttribute($values): void
    {
        $this->pendingCustomFields=$values ?? [];
    }

    public function save(array $options = [])
    {
        return DB::transaction(function() use($options) {
            $saved=parent::save($options);
            if ($saved && $this->pendingCustomFields!==null) {
                $this->customFieldValues()->delete();
                $position=0;
                foreach ($this->pendingCustomFields as $key=>$value) $this->customFieldValues()->create(['field_key'=>(string)$key,'value'=>$value,'display_order'=>$position++]);
                $this->pendingCustomFields=null;
                $this->unsetRelation('customFieldValues');
            }
            return $saved;
        });
    }
}
