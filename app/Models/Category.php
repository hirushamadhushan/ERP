<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['category_type', 'name', 'code', 'description', 'parent_id'];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function displayName(): string
    {
        return $this->name.($this->code ? ' - '.$this->code : '');
    }

    public static function forDropdown(string $type = 'product'): Collection
    {
        return static::query()
            ->where('category_type', $type)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (self $category) => [$category->id => $category->displayName()]);
    }

    public static function tree(string $type = 'product'): Collection
    {
        $categories = static::query()
            ->where('category_type', $type)
            ->orderBy('name')
            ->get();

        $ordered = $categories->whereNull('parent_id')->flatMap(function (self $category) use ($categories) {
            return collect([$category])->merge($categories->where('parent_id', $category->id));
        })->values()->all();

        return (new static)->newCollection($ordered);
    }
}
