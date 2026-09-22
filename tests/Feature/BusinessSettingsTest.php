<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BusinessSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_settings_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/business/settings');

        $response->assertStatus(200);
        $response->assertSee('Business Settings');
        $response->assertSee('Financial year start month:');
        preg_match('/<div[^>]*id="on-expiry-field"[^>]*>/', $response->getContent(), $expiryField);
        $this->assertStringContainsString('hidden', $expiryField[0]);
        preg_match('/<select[^>]*id="expiry_mode"[^>]*>/', $response->getContent(), $expirySelect);
        $this->assertStringContainsString('disabled', $expirySelect[0]);
        $response->assertSee('Stop Selling n days before')
            ->assertSee('Specify action that needs to be done on product expiry.')
            ->assertSee('Based on selected Unit it will show sub units for it.')
            ->assertSee('Enable this to add rack details of a product');
        preg_match('/<input[^>]*id="expiry_days"[^>]*>/', $response->getContent(), $expiryDays);
        $this->assertStringContainsString('disabled', $expiryDays[0]);
    }

    public function test_business_settings_can_be_saved(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('logo.png', 10, 'image/png');

        $response = $this->actingAs($user)->post('/business/settings', [
            'business_name' => 'Nexus Super Store',
            'start_date' => '2026-01-01',
            'default_profit_percent' => 35.5,
            'currency' => 'Sri Lanka - Rupees(LKR)',
            'currency_symbol_placement' => 'Before amount',
            'time_zone' => 'Asia/Colombo',
            'financial_year_start_month' => 'January',
            'stock_accounting_method' => 'FIFO (First In First Out)',
            'transaction_edit_days' => 45,
            'date_format' => 'mm/dd/yyyy',
            'time_format' => '12 Hour',
            'currency_precision' => 2,
            'quantity_precision' => 2,
            'logo' => $file,
        ]);

        $response->assertRedirect('/business/settings');
        $response->assertSessionHas('status', 'Business settings updated successfully.');

        $setting = BusinessSetting::current();
        $this->assertEquals('Nexus Super Store', $setting->business_name);
        $this->assertEquals(35.5, (float) $setting->default_profit_percent);
        $this->assertEquals('Asia/Colombo', $setting->time_zone);
        $this->assertEquals('Sri Lanka - Rupees(LKR)', $setting->currency);
        $this->assertStringStartsWith('data:image/png;base64,', $setting->logo_path);
    }

    public function test_product_settings_are_saved_and_displayed(): void
    {
        $user = User::factory()->create();
        $productSettings = [
            'sku_prefix' => 'NEX', 'expiry_enabled' => '1', 'expiry_mode' => 'manufacturing_period',
            'on_expiry' => 'stop_selling', 'expiry_grace_days' => '3', 'default_unit_id' => '',
            'enable_brands' => '1', 'enable_categories' => '1', 'enable_subcategories' => '0',
            'enable_price_tax' => '1', 'enable_our_price' => '0', 'enable_sub_units' => '0',
            'enable_racks' => '1', 'enable_row' => '0', 'enable_position' => '0',
            'enable_warranty' => '1', 'enable_secondary_unit' => '0', 'enable_serial_numbers' => '1',
        ];

        $this->actingAs($user)->post('/business/settings', [
            'business_name' => 'Nexus Super Store', 'start_date' => '2026-01-01',
            'default_profit_percent' => 25, 'currency' => 'Sri Lanka - Rupees(LKR)',
            'currency_symbol_placement' => 'Before amount', 'time_zone' => 'Asia/Colombo',
            'financial_year_start_month' => 'January',
            'stock_accounting_method' => 'FIFO (First In First Out)',
            'transaction_edit_days' => 30, 'date_format' => 'mm/dd/yyyy',
            'time_format' => '24 Hour', 'currency_precision' => 2, 'quantity_precision' => 2,
            'product_settings' => $productSettings,
        ])->assertRedirect('/business/settings')->assertSessionHasNoErrors();

        $settings = BusinessSetting::current()->fresh('productSettings');
        $this->assertSame('NEX', $settings->productSettings->sku_prefix);
        $this->assertFalse($settings->productSettings->enable_subcategories);
        $page = $this->actingAs($user)->get('/business/settings')
            ->assertOk()
            ->assertSee('name="product_settings[sku_prefix]"', false)
            ->assertSee('value="NEX"', false);
        preg_match('/<div[^>]*id="on-expiry-field"[^>]*>/', $page->getContent(), $expiryField);
        $this->assertStringNotContainsString('hidden', $expiryField[0]);
        preg_match('/<select[^>]*id="expiry_mode"[^>]*>/', $page->getContent(), $expirySelect);
        $this->assertStringNotContainsString('disabled', $expirySelect[0]);
        preg_match('/<input[^>]*id="expiry_days"[^>]*>/', $page->getContent(), $expiryDays);
        $this->assertStringNotContainsString('disabled', $expiryDays[0]);
    }
}
