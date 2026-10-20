<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCategoryRequest;
use App\Models\Category;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CategoryController extends Controller
{
    public function index()
    {
        try {
            return view('products.reference', [
                'kind' => 'categories',
                'singular' => 'Category',
                'records' => Category::tree(),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to load categories index: '.$e->getMessage());

            return back()->with('error', 'Unable to load categories.');
        }
    }

    public function store(SaveCategoryRequest $request)
    {
        try {
            $validated = $request->validated();

            $this->databaseTransaction(
                fn () => Category::create($validated),
                'A category with this name already exists.',
                'name'
            );

            return redirect()->route('products.categories.index')->with('success', 'Category added successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to store category: '.$e->getMessage());

            return back()->withInput()->with('error', 'Unable to add category. Please try again.');
        }
    }

    public function update(SaveCategoryRequest $request, Category $record)
    {
        try {
            $validated = $request->validated();

            $this->databaseTransaction(
                fn () => $record->update($validated),
                'A category with this name already exists.',
                'name'
            );

            return redirect()->route('products.categories.index')->with('success', 'Category updated successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to update category ID '.$record->id.': '.$e->getMessage());

            return back()->withInput()->with('error', 'Unable to update category. Please try again.');
        }
    }

    public function destroy(Category $record)
    {
        try {
            if ($record->children()->exists()) {
                return back()->with('category_error', 'This category has sub-categories. Move or delete them before deleting it.');
            }

            $this->databaseTransaction(
                fn () => $record->delete(),
                'This category is used by one or more products and cannot be deleted.'
            );

            return redirect()->route('products.categories.index')->with('success', 'Category deleted successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to delete category ID '.$record->id.': '.$e->getMessage());

            return back()->with('error', 'This category is used by one or more products and cannot be deleted.');
        }
    }

    public function api()
    {
        try {
            return response()->json(Category::tree()->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'short_code' => $category->code,
                'category_type' => $category->category_type,
                'parent_id' => $category->parent_id,
            ]));
        } catch (Throwable $e) {
            Log::error('Failed to load categories API: '.$e->getMessage());

            return response()->json(['error' => 'Unable to load categories.'], 500);
        }
    }
}
