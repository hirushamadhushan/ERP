<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCategoryRequest;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        return view('products.reference', ['kind' => 'categories', 'singular' => 'Category', 'records' => Category::orderBy('name')->get()]);
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
        $this->databaseTransaction(
            fn () => $record->delete(),
            'This category is used by one or more products and cannot be deleted.'
        );

        return redirect()->route('products.categories.index')->with('success', 'Category deleted successfully.');
    }

}
