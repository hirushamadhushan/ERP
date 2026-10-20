<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\InvoiceScheme;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\TaxRate;
use App\Models\PaymentAccount;
use App\Models\Role;
use App\Models\User;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SalesInvoicesTest extends TestCase
{
    public function test_line_discounts_are_validated_saved_and_posted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = Contact::create(['type'=>'customer','contact_id'=>'DISCOUNT-CUSTOMER','entity_type'=>'individual','name'=>'Discount customer','mobile'=>'0770000001','status'=>'active']);
        $product = Product::create(['name'=>'Discount product','code'=>'DISCOUNT-P','selling_price'=>100,'purchase_price'=>50,'our_price'=>100,'is_active'=>true,'not_for_selling'=>false]);
        $tax = TaxRate::create(['name'=>'Discount VAT','amount'=>18,'is_tax_group'=>false,'for_tax_group'=>false]);
        $payload = ['customer_id'=>$customer->id,'invoice_date'=>'2026-10-20','journal'=>'Sales','tax_display'=>'exclusive',
            'lines'=>[['line_type'=>'product','product_id'=>$product->id,'description'=>'Discount product','quantity'=>2,'unit_price'=>100,'discount_percent'=>10,'tax_rate_id'=>$tax->id]]];
        $this->post('/invoices', $payload)->assertSessionHasNoErrors();
        $invoice = SalesInvoice::latest('id')->firstOrFail();
        $this->assertSame('212.40', $invoice->total_amount);
        $this->assertEquals(20, $invoice->lines()->first()->discount_amount);
        $this->assertEquals(10, $invoice->lines()->first()->discount_percent);
        $this->get(route('invoices.edit', $invoice))->assertOk()->assertSee('discount_percent');
        $this->post(route('invoices.post', $invoice))->assertSessionHasNoErrors();
        $this->assertEquals(212.40, $invoice->journalItems()->sum('debit'));
        $this->assertEquals(212.40, $invoice->journalItems()->sum('credit'));

        $payload['tax_display'] = 'inclusive';
        $payload['lines'][0]['unit_price'] = 118;
        $this->post('/invoices', $payload)->assertSessionHasNoErrors();
        $inclusive = SalesInvoice::latest('id')->firstOrFail();
        $this->assertSame('180.00', $inclusive->untaxed_amount);
        $this->assertSame('32.40', $inclusive->tax_amount);
        foreach ([-1, 101] as $invalid) {
            $payload['lines'][0]['discount_percent'] = $invalid;
            $this->post('/invoices', $payload)->assertSessionHasErrors('lines.0.discount_percent');
        }
        $payload['lines'][0]['discount_percent'] = 100;
        $this->post('/invoices', $payload)->assertSessionHasNoErrors();
        $this->assertSame('0.00', SalesInvoice::latest('id')->firstOrFail()->total_amount);
    }

    public function createApplication()
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== ':memory:') throw new \RuntimeException('Invoice tests require an in-memory database.');
        $this->artisan('migrate', ['--force'=>true])->assertExitCode(0);
    }

    public function test_transactions_navigation_has_invoice_and_sales_tabs(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('invoices.index'))->assertOk()
            ->assertSee('Transactions')
            ->assertSee('Invoices')
            ->assertSee('Sales');

        $this->get(route('sales.index'))->assertOk()
            ->assertSee('Create orders and convert them into customer invoices.');
    }

    public function test_commission_agent_field_follows_business_setting(): void
    {
        $this->actingAs(User::factory()->create());

        BusinessSetting::current()->update(['enable_commission_agent' => false]);
        $this->get(route('invoices.create'))->assertOk()->assertDontSee('Commission Agent');

        BusinessSetting::current()->update(['enable_commission_agent' => true]);
        $this->get(route('invoices.create'))->assertOk()->assertSee('Commission Agent');
    }

    public function test_odoo_invoice_screen_saves_totals_and_posts(): void
    {
        $user=User::factory()->create(); $this->actingAs($user);
        $customer=Contact::create(['type'=>'customer','contact_id'=>'CUS-1','entity_type'=>'individual','name'=>'Hirusha','mobile'=>'0770000000','status'=>'active']);
        $tax=TaxRate::create(['name'=>'VAT 18','amount'=>18,'is_tax_group'=>false,'for_tax_group'=>false]);
        $product=Product::create(['name'=>'Test Product','code'=>'P-1','selling_price'=>100,'purchase_price'=>50,'our_price'=>100,'tax_rate_id'=>$tax->id,'is_active'=>true,'not_for_selling'=>false]);
        InvoiceScheme::where('is_default', true)->update(['prefix'=>'INV/2026/','start_number'=>1,'current_number'=>1,'number_of_digits'=>5]);

        $this->get('/invoices/create')->assertOk()->assertSee('Invoice Lines')->assertSee('Journal Items')
            ->assertSee('INV/2026/00001')
            ->assertSee('data-tab="journal"', false)->assertSee('id="draft-journal-rows"', false)
            ->assertSee('Add a line')->assertSee('Add a section')->assertSee('Add a note')->assertSee('Catalog')
            ->assertSee('Back to Invoice')->assertSee('Amount Due')->assertSee('Send message')
            ->assertSee('AppPage.ready', false)->assertSee('No products selected')
            ->assertSee('Spreadsheet')->assertSee('catalog-section-total', false)
            ->assertSee('Immediate Payment')->assertSee('30% Now, Balance 60 Days')
            ->assertSee('payment-term-options', false)->assertSee('list="payment-term-options"', false);
        $this->assertSame(1, InvoiceScheme::where('is_default', true)->firstOrFail()->current_number);
        $payload=['customer_id'=>$customer->id,'invoice_date'=>'2026-10-15','journal'=>'Sales','tax_display'=>'exclusive',
            'salesperson_id'=>$user->id,'payment_reference'=>'PO-100','delivery_date'=>'2026-10-16','incoterm'=>'FOB',
            'incoterm_location'=>'Colombo','fiscal_position'=>'Local','payment_method'=>'Bank Transfer','auto_post'=>0,
            'lines'=>[['line_type'=>'product','product_id'=>$product->id,'description'=>'Test Product','quantity'=>2,'unit_price'=>100,'tax_rate_id'=>$tax->id]]];
        $this->post('/invoices',$payload)->assertSessionHasNoErrors();
        $invoice=SalesInvoice::firstOrFail();
        $this->assertSame('INV/2026/00001',$invoice->invoice_number);
        $this->assertSame('236.00',$invoice->total_amount);
        $this->assertSame('PO-100',$invoice->payment_reference);
        $this->assertSame('FOB',$invoice->incoterm);
        $this->post(route('invoices.post',$invoice))->assertSessionHasNoErrors();
        $this->assertSame('posted',$invoice->fresh()->status);
        $this->assertSame('236.00',$invoice->fresh()->balance_due);
        $this->assertSame('236.00',number_format((float)$invoice->journalItems()->sum('debit'),2,'.',''));
        $this->assertSame('236.00',number_format((float)$invoice->journalItems()->sum('credit'),2,'.',''));
        $this->get(route('invoices.edit',$invoice))->assertOk()
            ->assertSee('data-tab="journal"', false)
            ->assertSee('id="tab-journal"', false)
            ->assertSee('Account Receivable')
            ->assertSee('Product Sales')
            ->assertSee('Analytic Distribution')
            ->assertDontSee('<fieldset disabled', false);
        $account=PaymentAccount::create(['name'=>'Main Bank','account_number'=>'001','opening_balance'=>0,'current_balance'=>0,'is_active'=>true,'created_by'=>$user->id]);
        $this->post(route('invoices.payments.store',$invoice),['payment_account_id'=>$account->id,'payment_date'=>'2026-10-15','amount'=>100,'method'=>'Bank Transfer'])->assertSessionHasNoErrors();
        $this->assertSame('partial',$invoice->fresh()->status);
        $this->assertSame('136.00',$invoice->fresh()->balance_due);
        $this->assertSame('100.00',$invoice->installments()->first()->settled_amount);
        $this->assertDatabaseHas('sales_invoice_journal_items',['sales_invoice_id'=>$invoice->id,'account_code'=>'001','debit'=>100,'credit'=>0]);
        $this->assertDatabaseHas('sales_invoice_journal_items',['sales_invoice_id'=>$invoice->id,'account_code'=>'121000','debit'=>0,'credit'=>100]);
        $this->post(route('invoices.payments.store',$invoice),['payment_account_id'=>$account->id,'payment_date'=>'2026-10-15','amount'=>136,'method'=>'Bank Transfer'])->assertSessionHasNoErrors();
        $this->assertSame('paid',$invoice->fresh()->status);
        $this->assertSame('236.00',$account->fresh()->current_balance);
        $this->post(route('invoices.reset-draft',$invoice))->assertSessionHasErrors('invoice');
        $this->put(route('invoices.update',$invoice),$payload)->assertSessionHasErrors('invoice');
    }

    public function test_posted_invoice_exposes_delivery_preview_credit_and_activity_workflows(): void
    {
        $user=User::factory()->create(); $this->actingAs($user);
        $customer=Contact::create(['type'=>'customer','contact_id'=>'CUS-2','entity_type'=>'individual','name'=>'Asha','email'=>'asha@example.com','mobile'=>'0770000001','status'=>'active']);
        $customer->forceFill(['advance_balance'=>40])->save();
        $product=Product::create(['name'=>'Consulting','code'=>'SV-1','selling_price'=>100,'purchase_price'=>0,'our_price'=>100,'is_active'=>true,'not_for_selling'=>false]);
        $this->post(route('invoices.store'),['customer_id'=>$customer->id,'invoice_date'=>'2026-10-15','journal'=>'Sales','tax_display'=>'exclusive','lines'=>[['line_type'=>'product','product_id'=>$product->id,'description'=>'Consulting','quantity'=>1,'unit_price'=>100]]])->assertSessionHasNoErrors();
        $invoice=SalesInvoice::firstOrFail();
        $this->post(route('invoices.post',$invoice))->assertSessionHasNoErrors();
        $this->get(route('invoices.edit',$invoice))->assertOk()->assertSee('Print &amp; Send',false)->assertSee('id="invoice-preview-panel"',false)->assertSee('Outstanding credits')->assertSee('Send message')->assertSee('Log note');
        $this->get(route('invoices.pdf',$invoice))->assertOk()->assertHeader('content-type','application/pdf');
        $this->post(route('invoices.credits.apply',$invoice))->assertSessionHasNoErrors();
        $this->assertSame('60.00',$invoice->fresh()->balance_due);
        $this->assertSame('0.00',$customer->fresh()->advance_balance);
        $this->assertDatabaseHas('sales_invoice_credit_allocations',['sales_invoice_id'=>$invoice->id,'amount'=>40]);
        $this->post(route('invoices.reset-draft',$invoice))->assertSessionHasErrors('invoice');
        $this->post(route('invoices.activities.store',$invoice),['type'=>'note','message'=>'Customer called about payment.'])->assertSessionHasNoErrors();
        $this->get(route('invoices.edit',$invoice))->assertSee('Customer called about payment.');
        Mail::fake();
        $this->app['config']->set('mail.default','smtp');
        $this->post(route('invoices.send',$invoice),['by_email'=>1,'to'=>'asha@example.com','subject'=>'Invoice '.$invoice->invoice_number,'message'=>'Please review.'])->assertRedirect()->assertSessionHasNoErrors();
        Mail::assertSent(\App\Mail\SalesInvoiceMail::class,fn($mail)=>str_starts_with($mail->invoicePdf,'%PDF-'));
        $this->assertDatabaseHas('sales_invoice_activities',['sales_invoice_id'=>$invoice->id,'type'=>'email']);
    }

    public function test_credit_note_reverses_balanced_journal_and_reduces_amount_due(): void
    {
        $user=User::factory()->create(); $this->actingAs($user);
        $customer=Contact::create(['type'=>'customer','contact_id'=>'CUS-3','entity_type'=>'individual','name'=>'Nimal','mobile'=>'0770000002','status'=>'active']);
        $tax=TaxRate::create(['name'=>'VAT 10','amount'=>10,'is_tax_group'=>false,'for_tax_group'=>false]);
        $product=Product::create(['name'=>'Service','code'=>'SV-2','selling_price'=>100,'purchase_price'=>0,'our_price'=>100,'tax_rate_id'=>$tax->id,'is_active'=>true,'not_for_selling'=>false]);
        $this->post(route('invoices.store'),['customer_id'=>$customer->id,'invoice_date'=>'2026-10-15','journal'=>'Sales','tax_display'=>'exclusive','lines'=>[['line_type'=>'product','product_id'=>$product->id,'description'=>'Service','quantity'=>1,'unit_price'=>100,'tax_rate_id'=>$tax->id]]])->assertSessionHasNoErrors();
        $invoice=SalesInvoice::firstOrFail();
        $this->post(route('invoices.post',$invoice))->assertSessionHasNoErrors();
        $this->get(route('invoices.edit',$invoice))->assertSee('Credit Note');
        $this->post(route('invoices.credit-notes.store',$invoice),['amount'=>55,'reason'=>'Partial service cancellation'])->assertSessionHasNoErrors();
        $this->assertSame('55.00',$invoice->fresh()->balance_due);
        $this->assertSame('partial',$invoice->fresh()->status);
        $this->assertSame('55.00',$invoice->fresh()->credited_amount);
        $this->assertSame(110.0,(float)$invoice->journalItems()->sum('debit'));
        $this->assertSame(110.0,(float)$invoice->journalItems()->sum('credit'));
        $note=$invoice->creditNotes()->with(['lines','journalItems'])->firstOrFail();
        $this->assertSame(55.0,(float)$note->journalItems->sum('debit'));
        $this->assertSame(55.0,(float)$note->journalItems->sum('credit'));
        $this->assertSame(50.0,(float)$note->untaxed_amount);
        $this->assertSame(5.0,(float)$note->tax_amount);
        $this->assertCount(1,$note->lines);
        $this->get(route('invoices.credit-notes.show',[$invoice,$note]))->assertOk()->assertSee($note->credit_number);
        $this->get(route('invoices.credit-notes.pdf',[$invoice,$note]))->assertOk()->assertHeader('content-type','application/pdf');
        $this->post(route('invoices.credit-notes.store',$invoice),['amount'=>55,'reason'=>'Remaining service cancellation'])->assertSessionHasNoErrors();
        $this->assertSame('credited',$invoice->fresh()->status);
        $this->assertSame('0.00',$invoice->fresh()->balance_due);
        $this->post(route('invoices.reset-draft',$invoice))->assertSessionHasErrors('invoice');
    }

    public function test_invoice_permissions_protect_financial_actions(): void
    {
        $user=User::factory()->create();
        $role=Role::create(['name'=>'Invoice Viewer']);
        $role->syncPermissions(['invoice.view']);
        $user->update(['role_id'=>$role->id]);
        $this->actingAs($user);

        $this->get(route('invoices.index'))->assertOk();
        $this->get(route('invoices.create'))->assertForbidden();

        $role->syncPermissions(['invoice.view','invoice.create']);
        $user->unsetRelation('assignedRole');
        $this->get(route('invoices.create'))->assertOk();
        $this->post(route('invoices.store'),[])->assertSessionHasErrors();
    }

    public function test_split_payment_terms_create_due_dated_receivable_installments(): void
    {
        $user=User::factory()->create();$this->actingAs($user);
        $customer=Contact::create(['type'=>'customer','contact_id'=>'CUS-4','entity_type'=>'individual','name'=>'Split Customer','mobile'=>'0770000004','status'=>'active']);
        $product=Product::create(['name'=>'Annual Service','code'=>'SV-3','selling_price'=>1000,'purchase_price'=>0,'our_price'=>1000,'is_active'=>true,'not_for_selling'=>false]);
        $this->post(route('invoices.store'),['customer_id'=>$customer->id,'invoice_date'=>'2026-10-15','payment_term'=>'30% Now, Balance 60 Days','journal'=>'Sales','tax_display'=>'exclusive','lines'=>[['line_type'=>'product','product_id'=>$product->id,'description'=>'Annual Service','quantity'=>1,'unit_price'=>1000]]])->assertSessionHasNoErrors();
        $invoice=SalesInvoice::firstOrFail();
        $this->assertSame('2026-10-15',$invoice->installments()->where('sequence',1)->firstOrFail()->due_date->format('Y-m-d'));
        $this->assertSame('300.00',$invoice->installments()->where('sequence',1)->firstOrFail()->amount);
        $this->assertSame('2026-12-14',$invoice->installments()->where('sequence',2)->firstOrFail()->due_date->format('Y-m-d'));
        $this->assertSame('700.00',$invoice->installments()->where('sequence',2)->firstOrFail()->amount);
        $this->post(route('invoices.post',$invoice))->assertSessionHasNoErrors();
        $this->assertSame(2,$invoice->journalItems()->where('account_code','121000')->count());
        $this->assertSame('1000.00',number_format((float)$invoice->journalItems()->where('account_code','121000')->sum('debit'),2,'.',''));
    }

    public function test_auto_post_uses_configured_ledger_accounts(): void
    {
        $user=User::factory()->create();$this->actingAs($user);
        BusinessSetting::current()->update(['invoice_receivable_account_code'=>'110100','invoice_revenue_account_code'=>'410100','invoice_tax_account_code'=>'220100','invoice_advance_account_code'=>'210100']);
        $customer=Contact::create(['type'=>'customer','contact_id'=>'CUS-5','entity_type'=>'individual','name'=>'Scheduled Customer','mobile'=>'0770000005','status'=>'active']);
        $tax=TaxRate::create(['name'=>'VAT 5','amount'=>5,'is_tax_group'=>false,'for_tax_group'=>false]);
        $product=Product::create(['name'=>'Scheduled Service','code'=>'SV-4','selling_price'=>200,'purchase_price'=>0,'our_price'=>200,'tax_rate_id'=>$tax->id,'is_active'=>true,'not_for_selling'=>false]);
        $this->post(route('invoices.store'),['customer_id'=>$customer->id,'invoice_date'=>today()->toDateString(),'journal'=>'Sales','tax_display'=>'exclusive','auto_post'=>1,'lines'=>[['line_type'=>'product','product_id'=>$product->id,'description'=>'Scheduled Service','quantity'=>1,'unit_price'=>200,'tax_rate_id'=>$tax->id]]])->assertSessionHasNoErrors();
        $invoice=SalesInvoice::firstOrFail();
        $this->artisan('invoices:auto-post')->assertExitCode(0);
        $this->assertSame('posted',$invoice->fresh()->status);
        $this->assertDatabaseHas('sales_invoice_journal_items',['sales_invoice_id'=>$invoice->id,'account_code'=>'110100','debit'=>210]);
        $this->assertDatabaseHas('sales_invoice_journal_items',['sales_invoice_id'=>$invoice->id,'account_code'=>'410100','credit'=>200]);
        $this->assertDatabaseHas('sales_invoice_journal_items',['sales_invoice_id'=>$invoice->id,'account_code'=>'220100','credit'=>10]);
    }
}
