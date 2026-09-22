<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

class SerialNumberSpreadsheet
{
    public function createTemplate(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'serial-template-');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Serial Numbers');
        $writer->addRow(Row::fromValues(['product_sku', 'location_code', 'serial_number']));
        $textStyle = (new Style)->setFormat('@');
        for ($row = 0; $row < 1000; $row++) {
            $writer->addRow(new Row([
                Cell::fromValue(null, $textStyle),
                Cell::fromValue(null, $textStyle),
                Cell::fromValue(null, $textStyle),
            ]));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Products');
        $writer->addRow(Row::fromValues(['product_id', 'product_sku', 'product_name', 'assigned_location_codes']));
        foreach (Product::with('locations')->where('enable_serial', true)->where('manage_stock', true)->orderBy('name')->get() as $product) {
            $writer->addRow(new Row(array_map(
                fn ($value) => new StringCell((string) $value, null),
                [$product->id, $product->code, $product->name, $product->locations->pluck('code')->implode(', ')]
            )));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Locations');
        $writer->addRow(Row::fromValues(['location_id', 'location_code', 'location_name']));
        foreach (Location::orderBy('name')->get() as $location) {
            $writer->addRow(new Row(array_map(
                fn ($value) => new StringCell((string) $value, null),
                [$location->id, $location->code, $location->name]
            )));
        }
        $writer->close();

        return $path;
    }

    public function read(UploadedFile $file): array
    {
        $xlsx = strtolower($file->getClientOriginalExtension()) === 'xlsx';
        if ($xlsx) {
            $this->validateWorkbookSize($file);
        }

        $reader = $xlsx ? new Reader : new \OpenSpout\Reader\CSV\Reader;
        $rows = [];
        try {
            $reader->open($file->getRealPath());
            foreach ($reader->getSheetIterator() as $sheet) {
                $bySku = null;
                foreach ($sheet->getRowIterator() as $row) {
                    $values = $row->toArray();
                    if ($bySku === null) {
                        $bySku = $this->validateHeader($values);

                        continue;
                    }
                    if (! array_filter($values, fn ($value) => $value !== null && $value !== '')) {
                        continue;
                    }
                    $rows[] = $this->mapRow($row, $values, $bySku, count($rows));
                }
                break;
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['file' => 'Cannot read this file. Upload a valid, unencrypted XLSX or UTF-8 CSV file.']);
        } finally {
            $reader->close();
        }

        return $rows;
    }

    private function validateWorkbookSize(UploadedFile $file): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            throw ValidationException::withMessages(['file' => 'Invalid Excel workbook.']);
        }
        $size = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $size += $zip->statIndex($index)['size'];
        }
        $zip->close();
        if ($size > 50 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'The expanded workbook exceeds 50 MB.']);
        }
    }

    private function validateHeader(array $values): bool
    {
        $bySku = in_array($values, [
            ['product_sku', 'location_code', 'serial_number'],
            ['product_sku', 'location_code', 'serial_id'],
        ], true);
        $byId = in_array($values, [
            ['product_id', 'location_id', 'serial_number'],
            ['product_id', 'location_id', 'serial_id'],
        ], true);
        if (! $bySku && ! $byId) {
            throw ValidationException::withMessages(['file' => 'Use product_sku, location_code, serial_number, or the older product_id, location_id, serial_number headers.']);
        }

        return $bySku;
    }

    private function mapRow(Row $row, array $values, bool $bySku, int $rowCount): array
    {
        if (count($values) !== 3 || $rowCount >= 1000) {
            throw ValidationException::withMessages(['file' => 'Use exactly three columns and a maximum of 1,000 rows.']);
        }
        foreach ($row->getCells() as $cell) {
            if ($cell instanceof FormulaCell) {
                throw ValidationException::withMessages(['file' => 'Formula cells are not supported. Paste values as text.']);
            }
        }
        if (! is_string($values[2])) {
            throw ValidationException::withMessages(['file' => 'Serial numbers must be Excel Text cells, preserving leading zeros and long numbers.']);
        }
        if ($bySku) {
            if (! is_string($values[0]) || ! is_string($values[1])) {
                throw ValidationException::withMessages(['file' => 'Format SKU and location codes as Text in Excel.']);
            }
            $values[0] = Product::where('sku_key', strtolower(trim($values[0])))->value('id');
            $values[1] = Location::where('code', trim($values[1]))->value('id');
        }

        return [
            'product_id' => $values[0],
            'location_id' => $values[1],
            'serial_number' => trim($values[2]),
            'product_variant_id' => null,
        ];
    }
}
