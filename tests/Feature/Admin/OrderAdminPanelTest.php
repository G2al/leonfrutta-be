<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Widgets\NewOrdersTable;
use App\Filament\Widgets\OrdersOverview;
use App\Filament\Widgets\RevenueOverview;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderRevenue;
use App\Services\WhatsApp\OrderWhatsAppLink;
use Filament\Actions\Testing\TestAction;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Js;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderAdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@leonfrutta.it']);
    }

    #[Test]
    public function guests_are_redirected_to_the_admin_login(): void
    {
        $order = Order::factory()->create();

        $this->get(OrderResource::getUrl('index'))->assertRedirect('/admin/login');
        $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertRedirect('/admin/login');
    }

    /**
     * Admin actions run through Livewire update requests: Filament must re-apply
     * its authentication middleware to them, not only to the initial page load.
     */
    #[Test]
    public function guests_cannot_run_order_actions_through_livewire_requests(): void
    {
        $this->assertContains(Authenticate::class, Livewire::getPersistentMiddleware());
    }

    #[Test]
    public function accounts_outside_the_company_cannot_open_the_panel(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'someone@example.com']))
            ->get(OrderResource::getUrl('index'))
            ->assertForbidden();
    }

    #[Test]
    public function the_root_url_redirects_to_the_admin_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    #[Test]
    public function the_admin_sees_the_order_requests_newest_first(): void
    {
        $older = Order::factory()->create(['created_at' => now()->subDay()]);
        $newer = Order::factory()->create();

        $this->actingAs($this->admin());

        $this->get(OrderResource::getUrl('index'))->assertOk()->assertSee('Richieste ordini');

        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    }

    #[Test]
    public function the_admin_can_search_and_filter_requests(): void
    {
        $pizzeria = Order::factory()->create(['business_name' => 'Pizzeria Da Mario']);
        $bar = Order::factory()->confirmed()->create(['business_name' => 'Bar Centrale']);

        $this->actingAs($this->admin());

        Livewire::test(ListOrders::class)
            ->searchTable('Pizzeria')
            ->assertCanSeeTableRecords([$pizzeria])
            ->assertCanNotSeeTableRecords([$bar]);

        Livewire::test(ListOrders::class)
            ->filterTable('status', OrderStatus::Confirmed->value)
            ->assertCanSeeTableRecords([$bar])
            ->assertCanNotSeeTableRecords([$pizzeria]);
    }

    #[Test]
    public function the_detail_page_shows_the_full_escaped_order_text(): void
    {
        $order = Order::factory()->create([
            'message' => "10 kg di pomodori\n5 kg di patate <script>alert('x')</script>",
        ]);

        $this->actingAs($this->admin())
            ->get(OrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertSee($order->business_name)
            ->assertSee('10 kg di pomodori')
            ->assertDontSee("<script>alert('x')</script>", escape: false);
    }

    #[Test]
    public function confirming_an_order_saves_it_and_opens_whatsapp(): void
    {
        $order = Order::factory()->create(['phone' => '+393331234567', 'first_name' => 'Mario']);

        $this->actingAs($this->admin());

        $component = Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertActionVisible('confirmOrder')
            ->assertActionHidden('openWhatsApp')
            ->callAction('confirmOrder', data: ['amount' => '45.50'])
            ->assertHasNoFormErrors();

        $whatsAppUrl = app(OrderWhatsAppLink::class)->confirmationUrl($order->fresh());
        $this->assertStringStartsWith('https://wa.me/393331234567?text=Buongiorno%20Mario', $whatsAppUrl);
        $this->assertStringContainsString(rawurlencode('Totale: 45,50'), $whatsAppUrl);

        $component
            ->assertJs('window.open('.Js::from($whatsAppUrl).', "_blank", "noopener")')
            ->assertNotified('Richiesta confermata')
            ->assertActionHidden('confirmOrder')
            ->assertActionHidden('cancelOrder')
            ->assertActionVisible('openWhatsApp');

        $order->refresh();
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame('45.50', $order->amount);
        $this->assertNotNull($order->confirmed_at);
    }

    #[Test]
    public function an_order_cannot_be_confirmed_without_a_valid_amount(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin());

        foreach (['' => 'required', '0' => 'min', '12.345' => 'decimal'] as $amount => $rule) {
            Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
                ->callAction('confirmOrder', data: ['amount' => (string) $amount])
                ->assertHasFormErrors(['amount' => $rule])
                ->assertNoJs();
        }

        $this->assertSame(OrderStatus::New, $order->fresh()->status);
    }

    #[Test]
    public function the_admin_can_correct_the_amount_and_register_the_payment(): void
    {
        $order = Order::factory()->confirmed('45.50')->create();

        $this->actingAs($this->admin());

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertActionHidden('markAsUnpaid')
            ->assertActionVisible('editAmount')
            ->callAction('editAmount', data: ['amount' => '48.20'])
            ->assertHasNoFormErrors()
            ->assertNotified('Importo aggiornato')
            ->callAction('markAsPaid')
            ->assertNotified('Pagamento registrato')
            ->assertActionHidden('markAsPaid')
            ->assertActionHidden('editAmount')
            ->assertActionVisible('markAsUnpaid');

        $order->refresh();
        $this->assertSame('48.20', $order->amount);
        $this->assertTrue($order->isPaid());
    }

    #[Test]
    public function a_payment_registered_by_mistake_can_be_removed(): void
    {
        $order = Order::factory()->paid()->create();

        $this->actingAs($this->admin());

        Livewire::test(ListOrders::class)
            ->callAction(TestAction::make('markAsUnpaid')->table($order))
            ->assertNotified('Pagamento rimosso');

        $this->assertFalse($order->fresh()->isPaid());
    }

    #[Test]
    public function payment_actions_are_hidden_on_new_and_cancelled_orders(): void
    {
        $this->actingAs($this->admin());

        foreach ([Order::factory()->create(), Order::factory()->cancelled()->create()] as $order) {
            Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
                ->assertActionHidden('markAsPaid')
                ->assertActionHidden('markAsUnpaid')
                ->assertActionHidden('editAmount');
        }
    }

    #[Test]
    public function the_admin_can_filter_paid_and_unpaid_orders(): void
    {
        $paid = Order::factory()->paid()->create();
        $unpaid = Order::factory()->confirmed()->create();
        $new = Order::factory()->create();

        $this->actingAs($this->admin());

        Livewire::test(ListOrders::class)
            ->filterTable('paid', true)
            ->assertCanSeeTableRecords([$paid])
            ->assertCanNotSeeTableRecords([$unpaid, $new]);

        Livewire::test(ListOrders::class)
            ->filterTable('paid', false)
            ->assertCanSeeTableRecords([$unpaid])
            ->assertCanNotSeeTableRecords([$paid, $new]);
    }

    #[Test]
    public function the_whatsapp_action_reopens_the_chat_without_changing_the_status(): void
    {
        $order = Order::factory()->confirmed()->create(['phone' => '+393331234567']);

        $this->actingAs($this->admin());

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertActionHasUrl('openWhatsApp', 'https://wa.me/393331234567')
            ->assertActionShouldOpenUrlInNewTab('openWhatsApp');

        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
    }

    #[Test]
    public function cancelling_an_order_saves_it_without_opening_whatsapp(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin());

        Livewire::test(ListOrders::class)
            ->callAction(TestAction::make('cancelOrder')->table($order))
            ->assertNotified('Richiesta annullata')
            ->assertNoJs();

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertNull($order->confirmed_at);
    }

    #[Test]
    public function confirmed_orders_cannot_be_cancelled_from_the_panel(): void
    {
        $order = Order::factory()->confirmed()->create();

        $this->actingAs($this->admin());

        Livewire::test(ListOrders::class)
            ->assertActionHidden(TestAction::make('cancelOrder')->table($order))
            ->assertActionHidden(TestAction::make('confirmOrder')->table($order));
    }

    #[Test]
    public function the_dashboard_shows_real_counters_and_pending_requests(): void
    {
        Order::factory()->count(3)->create();
        Order::factory()->count(2)->confirmed()->create();
        Order::factory()->cancelled()->create();

        $this->assertSame(['new' => 3, 'confirmed' => 2, 'cancelled' => 1], Order::countByStatus());

        $this->actingAs($this->admin());

        Livewire::test(OrdersOverview::class)->assertOk()->assertSee(['Totali', '6', 'Nuovo', '3']);
        Livewire::test(NewOrdersTable::class)
            ->assertCanSeeTableRecords(Order::where('status', OrderStatus::New)->get())
            ->assertCountTableRecords(3);
    }

    #[Test]
    public function the_dashboard_shows_revenue_in_the_italian_calendar(): void
    {
        // 23:30 in Rome on 3 November is still 22:30 UTC of the same day.
        Carbon::setTestNow(Carbon::parse('2026-11-03 23:30:00', 'Europe/Rome'));

        Order::factory()->paid()->create(['amount' => '100.00', 'paid_at' => now()]);
        Order::factory()->paid()->create(['amount' => '20.50', 'paid_at' => Carbon::parse('2026-11-01 09:00:00', 'Europe/Rome')->utc()]);
        // Stored in UTC: 22:00 UTC on 31 October is 23:00 in Rome, so it still belongs to October.
        Order::factory()->paid()->create(['amount' => '999.00', 'confirmed_at' => '2026-10-20 10:00:00', 'paid_at' => '2026-10-31 22:00:00']);
        Order::factory()->confirmed('30.00')->create();
        Order::factory()->create();

        $revenue = app(OrderRevenue::class);

        $this->assertSame('100.00', $revenue->collectedToday());
        $this->assertSame('120.50', $revenue->collectedThisMonth());
        $this->assertSame('150.50', $revenue->confirmedThisMonth());
        $this->assertSame('30.00', $revenue->outstanding());

        $this->actingAs($this->admin());

        Livewire::test(RevenueOverview::class)
            ->assertOk()
            ->assertSee(['Incassato oggi', 'Incassato questo mese', 'Da incassare']);
    }

    #[Test]
    public function orders_cannot_be_created_or_edited_by_hand(): void
    {
        $order = Order::factory()->create();

        $this->assertFalse(OrderResource::canCreate());
        $this->assertFalse(OrderResource::canEdit($order));
        $this->assertFalse(OrderResource::hasPage('create'));
        $this->assertFalse(OrderResource::hasPage('edit'));
    }

    #[Test]
    public function the_admin_can_permanently_delete_an_order_from_its_detail_page(): void
    {
        $order = Order::factory()->paid()->create();

        $this->actingAs($this->admin());

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertActionVisible('delete')
            ->callAction('delete')
            ->assertNotified('Richiesta eliminata')
            ->assertRedirect(OrderResource::getUrl('index'));

        $this->assertModelMissing($order);
        $this->getJson("/api/v1/orders/{$order->tracking_token}")->assertNotFound();
    }

    #[Test]
    public function the_admin_can_delete_orders_from_the_table_in_any_status(): void
    {
        $orders = collect([
            Order::factory()->create(),
            Order::factory()->confirmed()->create(),
            Order::factory()->cancelled()->create(),
        ]);
        $kept = Order::factory()->create();

        $this->actingAs($this->admin());

        Livewire::test(ListOrders::class)
            ->callAction(TestAction::make('delete')->table($orders->first()))
            ->assertNotified('Richiesta eliminata');

        Livewire::test(ListOrders::class)
            ->selectTableRecords($orders->skip(1)->pluck('id')->all())
            ->callAction(TestAction::make('delete')->table()->bulk());

        $orders->each(fn (Order $order) => $this->assertModelMissing($order));
        $this->assertModelExists($kept);
    }
}
