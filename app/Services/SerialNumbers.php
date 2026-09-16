<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSerialNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SerialNumbers
{
    public function generate(array $data): array
    {
        $rows = [];
        for ($i = 0; $i < $data['count']; $i++) {
            $number = str_pad((string) ($data['start_number'] + $i), $data['padding'], '0', STR_PAD_LEFT);
            $serial = implode($data['separator'] ?? '', array_filter([$data['prefix'] ?? '', $data['middle_fix'] ?? '', $number, $data['post_fix'] ?? ''], fn ($part) => $part !== ''));
            $rows[] = ['product_id' => $data['product_id'], 'location_id' => $data['location_id'], 'variation' => 'Default', 'serial_number' => $serial];
        }

        return $this->validateRows($rows);
    }

    public function validateRows(array $rows): array
    {
        if (! count($rows) || count($rows) > 1000) {
            throw ValidationException::withMessages(['serials' => 'Provide between 1 and 1,000 serial numbers.']);
        }
        $seen = [];
        foreach ($rows as $i => $row) {
            $validator = Validator::make($row, [
                'product_id' => 'required|integer|exists:products,id',
                'location_id' => 'required|integer|exists:locations,id',
                'serial_number' => ['required', 'string', 'max:100', 'regex:/^[!-~]+$/D'],
            ]);
            if ($validator->fails()) {
                throw ValidationException::withMessages(['serials' => 'Row '.($i + 2).': '.implode(' ', $validator->errors()->all())]);
            }
            $product = Product::findOrFail($row['product_id']);
            if (! $product->enable_serial || ! $product->manage_stock) {
                throw ValidationException::withMessages(['serials' => 'Row '.($i + 2).': Enable serial tracking and Manage Stock on this product first.']);
            }
            if (! $product->locations()->where('locations.id', $row['location_id'])->exists()) {
                throw ValidationException::withMessages(['serials' => 'Row '.($i + 2).': This location is not assigned to product '.$product->code.'. Edit the product locations first.']);
            }
            $key = strtolower($row['serial_number']);
            if (isset($seen[$key]) || ProductSerialNumber::whereRaw('LOWER(serial_number) = ?', [$key])->exists()) {
                throw ValidationException::withMessages(['serials' => 'Row '.($i + 2).': duplicate serial number '.$row['serial_number'].'. No records were saved.']);
            }
            $seen[$key] = true;
        }

        return $rows;
    }

    public function save(array $rows): void
    {
        try {
            DB::transaction(function () use ($rows) {
                Product::whereIn('id', array_column($rows, 'product_id'))->orderBy('id')->lockForUpdate()->get();
                $this->validateRows($rows);
                foreach ($rows as $row) {
                    ProductSerialNumber::create($row);
                }
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['serials' => 'A serial number already exists. No records were saved.']);
        }
    }
}
