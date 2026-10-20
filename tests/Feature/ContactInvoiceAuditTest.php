<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\PaymentAccount;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Regression checks for the gaps found during the Contacts/Invoices review.
// Failures document missing behavior; these checks never use the live database.
class ContactInvoiceAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);
        DB::purge('sqlite');
        if (DB::connection()->getDatabaseName() !== ':memory:') throw new \RuntimeException('Audit requires an in-memory database.');
        $this->artisan('migrate',['--force'=>true])->assertExitCode(0);
        $this->actingAs(User::factory()->create());
    }

    private function customer(): Contact
    {
        return Contact::create(['contact_id'=>'AUDIT-CUSTOMER','type'=>'customer','entity_type'=>'individual','name'=>'Audit Customer','mobile'=>'0770000000','status'=>'active']);
    }

    private function payload(Contact $customer): array
    {
        $product=Product::create(['name'=>'Audit Product','code'=>'AUDIT-PRODUCT','selling_price'=>100,'purchase_price'=>0,'our_price'=>100,'is_active'=>true,'not_for_selling'=>false]);
        return ['customer_id'=>$customer->id,'invoice_date'=>today()->toDateString(),'journal'=>'Sales','tax_display'=>'exclusive','lines'=>[['line_type'=>'product','product_id'=>$product->id,'description'=>'Audit product sale','quantity'=>1,'unit_price'=>100]]];
    }

    private function postedInvoice(Contact $customer): SalesInvoice
    {
        $this->post(route('invoices.store'),$this->payload($customer))->assertSessionHasNoErrors();
        $invoice=SalesInvoice::firstOrFail();
        $this->post(route('invoices.post',$invoice))->assertSessionHasNoErrors();
        return $invoice->fresh();
    }

    public function test_create_permission_alone_cannot_confirm_an_invoice(): void
    {
        $role=Role::create(['name'=>'Audit Invoice Creator']);
        $role->syncPermissions(['invoice.create']);
        auth()->user()->update(['role_id'=>$role->id]);
        auth()->user()->unsetRelation('assignedRole');
        $this->post(route('invoices.store'),$this->payload($this->customer())+['intent'=>'confirm'])->assertForbidden();
    }

    public function test_customer_due_includes_posted_invoice_balance(): void
    {
        $customer=$this->customer();
        $this->postedInvoice($customer);
        $this->assertSame('100.00',$customer->fresh()->due_balance);
    }

    public function test_posting_updates_customer_last_sale_date(): void
    {
        $customer=$this->customer();
        $invoice=$this->postedInvoice($customer);
        $this->assertSame($invoice->invoice_date->toDateString(),$customer->fresh()->last_sale_at?->toDateString());
    }

    public function test_sales_history_displays_existing_invoice(): void
    {
        $customer=$this->customer();
        $invoice=$this->postedInvoice($customer);
        $this->get(route('contacts.customers.sales-history',$customer))->assertOk()->assertSee($invoice->invoice_number);
    }

    public function test_posting_checks_customer_credit_limit(): void
    {
        $customer=$this->customer();
        $customer->update(['credit_limit'=>50]);
        $this->post(route('invoices.store'),$this->payload($customer))->assertSessionHasNoErrors();
        $this->post(route('invoices.post',SalesInvoice::firstOrFail()))->assertSessionHasErrors();
    }

    public function test_payment_and_credit_note_update_customer_due(): void
    {
        $customer=$this->customer();
        $invoice=$this->postedInvoice($customer);
        $account=PaymentAccount::create(['name'=>'Audit Bank','account_number'=>'AUDIT-001','opening_balance'=>0,'current_balance'=>0,'is_active'=>true]);
        $this->post(route('invoices.payments.store',$invoice),['payment_account_id'=>$account->id,'payment_date'=>today()->toDateString(),'amount'=>40])->assertSessionHasNoErrors();
        $this->assertSame('60.00',$customer->fresh()->due_balance);
        $this->post(route('invoices.credit-notes.store',$invoice),['amount'=>60,'reason'=>'Audit cancellation'])->assertSessionHasNoErrors();
        $this->assertSame('0.00',$customer->fresh()->due_balance);
    }

    public function test_reset_removes_invoice_from_due_and_last_sale(): void
    {
        $customer=$this->customer();
        $invoice=$this->postedInvoice($customer);
        $this->post(route('invoices.reset-draft',$invoice))->assertSessionHasNoErrors();
        $this->assertSame('0.00',$customer->fresh()->due_balance);
        $this->assertNull($customer->fresh()->last_sale_at);
    }

    public function test_create_permission_cannot_schedule_automatic_posting(): void
    {
        $role=Role::create(['name'=>'Audit Draft Creator']);$role->syncPermissions(['invoice.create']);
        auth()->user()->update(['role_id'=>$role->id]);auth()->user()->unsetRelation('assignedRole');
        $this->post(route('invoices.store'),$this->payload($this->customer())+['auto_post'=>1])->assertForbidden();
        $this->assertDatabaseCount('sales_invoices',0);
    }

    public function test_contact_opening_balance_is_collectible_without_double_counting_invoices(): void
    {
        $this->post(route('contacts.store'),['contact_id'=>'AUDIT-OPENING','type'=>'customer','entity_type'=>'individual','name'=>'Opening Customer','mobile'=>'0770000001','status'=>'active','opening_balance'=>75,'shipping_address'=>'Audit delivery address'])->assertSessionHasNoErrors();
        $customer=Contact::where('contact_id','AUDIT-OPENING')->firstOrFail();
        $this->assertSame('75.00',$customer->due_balance);
        $invoice=$this->postedInvoice($customer);
        $this->assertSame('175.00',$customer->fresh()->due_balance);
        $this->postJson(route('contacts.customers.due-payments',$customer),['request_id'=>(string)\Illuminate\Support\Str::uuid(),'amount'=>100,'payment_method'=>'Cash','paid_at'=>now()->toISOString()])->assertUnprocessable();
        $this->assertSame('100.00',$invoice->fresh()->balance_due);
        $this->postJson(route('contacts.customers.due-payments',$customer),['request_id'=>(string)\Illuminate\Support\Str::uuid(),'amount'=>25,'payment_method'=>'Cash','paid_at'=>now()->toISOString()])->assertOk();
        $this->assertSame('150.00',$customer->fresh()->due_balance);
    }
}
