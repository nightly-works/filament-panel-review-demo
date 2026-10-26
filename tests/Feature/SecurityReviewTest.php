<?php

namespace Tests\Feature;

use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Invoice;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * One test per finding of docs/security-review.md. Each attack is written as a support user or a
 * stranger would send it; the test passes only when the attack is refused and normal use still works.
 * Run against the app as delivered (tag as-delivered), every test here fails: scripts/prove-flaws.sh.
 */
class SecurityReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    public function test_1_strangers_cannot_sign_up_into_the_back_office(): void
    {
        // A self-registered account has no role.
        $stranger = User::factory()->create();

        $this->get('/admin/register')->assertNotFound();
        $this->actingAs($stranger)->get('/admin')->assertForbidden();

        $this->actingAs(User::factory()->support()->create())->get('/admin')->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    }

    public function test_2_support_cannot_promote_itself_to_admin(): void
    {
        $support = User::factory()->support()->create();
        $this->actingAs($support);

        Livewire::test(ListUsers::class)->assertForbidden();
        Livewire::test(EditUser::class, ['record' => $support->getRouteKey()])->assertForbidden();
        $this->assertSame('support', $support->fresh()->role);

        // Admins still manage users.
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Livewire::test(EditUser::class, ['record' => $support->getRouteKey()])
            ->fillForm(['role' => 'admin'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('admin', $support->fresh()->role);
    }

    public function test_3_support_cannot_refund_through_the_custom_action(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'paid']);

        $this->actingAs(User::factory()->support()->create());
        // The two Livewire calls the browser makes for a row action, sent even though the button is hidden.
        $list = Livewire::test(ListInvoices::class)
            ->mountAction(TestAction::make('refund')->table($invoice))
            ->call('callMountedAction');
        $this->assertSame('paid', $invoice->fresh()->status);
        $list->assertActionHidden(TestAction::make('refund')->table($invoice));

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ListInvoices::class)
            ->callAction(TestAction::make('refund')->table($invoice));
        $this->assertSame('refunded', $invoice->fresh()->status);
    }

    public function test_4_support_cannot_change_status_through_the_inline_column(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'sent']);

        // What the browser sends when the select in the table row changes.
        $this->actingAs(User::factory()->support()->create());
        Livewire::test(ListInvoices::class)
            ->call('updateTableColumnState', 'status', (string) $invoice->getKey(), 'paid');
        $this->assertSame('sent', $invoice->fresh()->status);

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ListInvoices::class)
            ->call('updateTableColumnState', 'status', (string) $invoice->getKey(), 'paid');
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_admins_cannot_bulk_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();
        $leaver = User::factory()->support()->create();
        $this->actingAs($admin);

        // Bulk actions check deleteAny() unless told to check each record against delete().
        Livewire::test(ListUsers::class)->callTableBulkAction('delete', [$admin, $leaver]);

        $this->assertModelExists($admin);
        $this->assertModelMissing($leaver);
    }

    public function test_5_support_still_reads_invoices(): void
    {
        $invoices = Invoice::factory()->count(3)->create();

        $this->actingAs(User::factory()->support()->create());
        Livewire::test(ListInvoices::class)->assertCanSeeTableRecords($invoices);
    }
}
