<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCategoryRequest;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        return view('products.reference', [
            'kind' => 'categories',
            'singular' => 'Category',
            'records' => Category::tree(),
        ]);
    }

    public function store(SaveCategoryRequest $request)
    {
        $validated = $request->validated();

        $this->databaseTransaction(
            fn () => Category::create($validated),
            'A category with this name already exists.',
            'name'
        );

        return redirect()->route('products.categories.index')->with('success', 'Category added successfully.');
    }

    public function update(SaveCategoryRequest $request, Category $record)
    {
        $validated = $request->validated();

        $this->databaseTransaction(
            fn () => $record->update($validated),
            'A category with this name already exists.',
            'name'
        );

        return redirect()->route('products.categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $record)
    {
        if ($record->children()->exists()) {
            return back()->with('category_error', 'This category has sub-categories. Move or delete them before deleting it.');
        }

        $this->databaseTransaction(
            fn () => $record->delete(),
            'This category is used by one or more products and cannot be deleted.'
        );

        return redirect()->route('products.categories.index')->with('success', 'Category deleted successfully.');
    }

    public function api()
    {
        return response()->json(Category::tree()->map(fn (Category $category) => [
            'id' => $category->id,
            'name' => $category->name,
            'short_code' => $category->code,
            'category_type' => $category->category_type,
            'parent_id' => $category->parent_id,
        ]));
    }

}
