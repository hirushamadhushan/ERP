<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    // Root is depth zero; allow five sub-category levels below it.
    public const MAX_DEPTH = 5;
    protected $fillable = ['category_type', 'name', 'code', 'description', 'parent_id'];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function rootCategoryId(): int
    {
        $category = $this;
        $seen = [];
        while ($category->parent_id && !isset($seen[$category->id])) {
            $seen[$category->id] = true;
            $parent = $category->parent;
            if (!$parent) break;
            $category = $parent;
        }
        return $category->id;
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

        $ordered = [];
        $visit = function ($parentId, int $depth, string $path, ?int $rootId = null) use (&$visit, &$ordered, $categories) {
            foreach ($categories->where('parent_id', $parentId) as $category) {
                $category->setAttribute('tree_depth', $depth);
                $category->setAttribute('tree_root_id', $rootId ?? $category->id);
                $category->setAttribute('tree_path', ltrim($path.' / '.$category->name, ' /'));
                $ordered[] = $category;
                $visit($category->id, $depth + 1, $category->tree_path, $rootId ?? $category->id);
            }
        };
        $visit(null, 0, '');

        return (new static)->newCollection($ordered);
    }

    public static function validateParent(?int $parentId, ?self $record = null): void
    {
        $all = static::all()->keyBy('id');
        $seen = $record ? [$record->id] : [];
        $depth = 0;
        for ($id = $parentId; $id !== null; $id = $all[$id]->parent_id) {
            if (in_array($id, $seen) || !isset($all[$id])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['parent_id' => 'Select a parent outside this category and its descendants.']);
            }
            $seen[] = $id;
            $depth++;
        }
        $height = function ($id) use (&$height, $all): int {
            $children = $all->where('parent_id', $id);
            return $children->isEmpty() ? 0 : 1 + $children->max(fn ($child) => $height($child->id));
        };
        if ($depth + ($record ? $height($record->id) : 0) > self::MAX_DEPTH) {
            throw \Illuminate\Validation\ValidationException::withMessages(['parent_id' => 'Only five sub-category levels are allowed below a main category.']);
        }
    }
}
