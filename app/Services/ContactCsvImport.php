<?php

namespace App\Services;

use App\Models\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContactCsvImport
{
    public const COLUMNS = [
        'Contact type', 'Prefix', 'First Name', 'Middle name', 'Last Name', 'Business Name',
        'Contact ID', 'Tax number', 'Opening Balance', 'Pay term', 'Pay term period',
        'Credit Limit', 'Email', 'Mobile', 'Alternate contact number', 'Landline',
        'City', 'State', 'Country', 'Address line 1', 'Address line 2', 'Zip Code',
        'Date of birth', 'Custom Field 1', 'Custom Field 2', 'Custom Field 3', 'Custom Field 4',
    ];

    public function import(string $path): int
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw ValidationException::withMessages(['file' => 'Unable to read the uploaded file.']);
        }
        $rows = [];
        $errors = [];
        $ids = [];
        try {
            // Skip the UTF-8 BOM before CSV parsing so a quoted first header is parsed correctly.
            if (fread($stream, 3) !== "\xEF\xBB\xBF") {
                rewind($stream);
            }
            $header = fgetcsv($stream, 0, ',', '"', '');
            if ($header !== self::COLUMNS) {
                throw ValidationException::withMessages(['file' => 'The CSV header must match the downloaded template, in the same order.']);
            }
            $rowNumber = 1;
            while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $rowNumber++;
                if ($rowNumber > 1001) {
                    throw ValidationException::withMessages(['file' => 'Import at most 1,000 rows per file.']);
                }
                if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                if (count($values) !== count(self::COLUMNS)) {
                    $errors[] = "Row {$rowNumber}: expected 27 columns.";

                    continue;
                }
                if (! mb_check_encoding(implode('', $values), 'UTF-8')) {
                    $errors[] = "Row {$rowNumber}: save the file as UTF-8 CSV.";

                    continue;
                }
                $values = array_map(fn ($value) => trim((string) $value) === '' ? null : trim((string) $value), $values);
                $data = array_combine(self::COLUMNS, $values);
                $requiredForSupplier = in_array($data['Contact type'], ['2', '3']);
                $rules = [
                    'Contact type' => ['required', Rule::in(['1', '2', '3'])],
                    'First Name' => ['required', 'string', 'max:255'],
                    'Business Name' => [Rule::requiredIf($requiredForSupplier), 'nullable', 'string', 'max:255'],
                    'Contact ID' => ['nullable', 'string', 'max:60', Rule::unique('contacts', 'contact_id')],
                    'Opening Balance' => ['nullable', 'numeric', 'between:0,9999999999999.99', 'decimal:0,2'],
                    'Credit Limit' => ['nullable', 'numeric', 'between:0,9999999999999.99', 'decimal:0,2'],
                    'Pay term' => [Rule::requiredIf($requiredForSupplier || $data['Pay term period'] !== null), 'nullable', 'integer', 'between:0,100000'],
                    'Pay term period' => [Rule::requiredIf($requiredForSupplier || $data['Pay term'] !== null), 'nullable', Rule::in(['days', 'months'])],
                    'Email' => ['nullable', 'email', 'max:255'],
                    'Mobile' => ['required', 'string', 'max:50'],
                    'Date of birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                ];
                foreach (['Prefix' => 50, 'Middle name' => 255, 'Last Name' => 255, 'Tax number' => 100, 'Alternate contact number' => 50, 'Landline' => 50, 'City' => 100, 'State' => 100, 'Country' => 100, 'Address line 1' => 255, 'Address line 2' => 255, 'Zip Code' => 30, 'Custom Field 1' => 255, 'Custom Field 2' => 255, 'Custom Field 3' => 255, 'Custom Field 4' => 255] as $column => $max) {
                    $rules[$column] = ['nullable', 'string', 'max:'.$max];
                }
                $validator = Validator::make($data, $rules);
                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $error) {
                        $errors[] = "Row {$rowNumber}: {$error}";
                    }

                    continue;
                }
                $name = implode(' ', array_filter([$data['Prefix'], $data['First Name'], $data['Middle name'], $data['Last Name']], fn ($value) => $value !== null));
                if (mb_strlen($name) > 255) {
                    $errors[] = "Row {$rowNumber}: the combined name cannot exceed 255 characters.";

                    continue;
                }
                if ($data['Contact ID'] !== null) {
                    $key = mb_strtolower($data['Contact ID']);
                    if (isset($ids[$key])) {
                        $errors[] = "Row {$rowNumber}: Contact ID is repeated in this file.";

                        continue;
                    }
                    $ids[$key] = true;
                }
                $rows[] = [
                    'type' => ['1' => 'customer', '2' => 'supplier', '3' => 'both'][$data['Contact type']],
                    'name' => $name, 'entity_type' => $data['Business Name'] !== null ? 'business' : 'individual',
                    'business_name' => $data['Business Name'], 'contact_id' => $data['Contact ID'] ?? 'C-'.Str::upper((string) Str::ulid()),
                    'tax_number' => $data['Tax number'], 'opening_balance' => $data['Opening Balance'] ?? 0,
                    'pay_term' => $data['Pay term'], 'pay_term_unit' => $data['Pay term period'],
                    'credit_limit' => $data['Credit Limit'], 'email' => $data['Email'], 'mobile' => $data['Mobile'],
                    'alternate_number' => $data['Alternate contact number'], 'landline' => $data['Landline'],
                    'city' => $data['City'], 'state' => $data['State'], 'country' => $data['Country'],
                    'address_line_1' => $data['Address line 1'], 'address_line_2' => $data['Address line 2'],
                    'zip_code' => $data['Zip Code'], 'date_of_birth' => $data['Date of birth'],
                    'custom_fields' => array_slice($values, 23, 4), 'status' => 'active',
                ];
            }
        } finally {
            fclose($stream);
        }
        if ($errors) {
            throw ValidationException::withMessages(['file' => array_slice($errors, 0, 50)]);
        }
        if (! $rows) {
            throw ValidationException::withMessages(['file' => 'The file has no contact rows. Add data below the header.']);
        }
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                Contact::create($row);
            }
        });

        return count($rows);
    }
}
