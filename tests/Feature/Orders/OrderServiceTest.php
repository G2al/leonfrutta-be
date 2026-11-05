<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\OrderOperationNotAllowedException;
use App\Models\Order;
use App\Services\Orders\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderService = app(OrderService::class);
    }

    #[Test]
    public function new_orders_get_a_unique_random_tracking_token(): void
    {
        [$first, $second] = Order::factory()->count(2)->create();

        $this->assertTrue(str($first->tracking_token)->isUuid());
        $this->assertNotSame($first->tracking_token, $second->tracking_token);
        $this->assertIsInt($first->id);
    }

    #[Test]
    public function a_new_order_can_be_confirmed_with_its_amount(): void
    {
        Carbon::setTestNow('2026-11-03 10:30:00');
        $order = Order::factory()->create();

        $this->orderService->confirm($order, '45,5');

        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame('45.50', $order->amount);
        $this->assertTrue($order->confirmed_at->equalTo(now()));
        $this->assertNull($order->cancelled_at);
        $this->assertFalse($order->isPaid());
        $this->assertSame('45.50', $order->fresh()->amount);
    }

    /**
     * @return array<string, array{string|float}>
     */
    public static function invalidAmounts(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-10'],
            'letters' => ['dieci'],
            'three decimals' => ['10.555'],
            'too large' => ['100000000'],
            'scientific notation' => ['1e5'],
        ];
    }

    #[Test]
    #[DataProvider('invalidAmounts')]
    public function an_order_cannot_be_confirmed_with_an_invalid_amount(string|float $amount): void
    {
        $order = Order::factory()->create();

        try {
            $this->orderService->confirm($order, $amount);
            $this->fail('An invalid amount must be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertSame(OrderStatus::New, $order->fresh()->status);
        }
    }

    #[Test]
    public function a_new_order_can_be_cancelled(): void
    {
        $order = Order::factory()->create();

        $this->orderService->cancel($order);

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->cancelled_at);
        $this->assertNull($order->fresh()->confirmed_at);
    }

    #[Test]
    public function an_order_cannot_be_confirmed_twice(): void
    {
        $order = Order::factory()->confirmed('20.00')->create(['confirmed_at' => '2026-11-01 08:00:00']);

        try {
            $this->orderService->confirm($order, '99.00');
            $this->fail('A confirmed order must not be confirmed again.');
        } catch (OrderOperationNotAllowedException) {
            $this->assertSame('2026-11-01 08:00:00', $order->fresh()->confirmed_at->format('Y-m-d H:i:s'));
            $this->assertSame('20.00', $order->fresh()->amount);
        }
    }

    #[Test]
    public function a_confirmed_order_cannot_be_cancelled(): void
    {
        $order = Order::factory()->confirmed()->create();

        $this->expectException(OrderOperationNotAllowedException::class);

        try {
            $this->orderService->cancel($order);
        } finally {
            $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
            $this->assertNull($order->fresh()->cancelled_at);
        }
    }

    #[Test]
    public function a_cancelled_order_cannot_be_confirmed(): void
    {
        $order = Order::factory()->cancelled()->create();

        $this->expectException(OrderOperationNotAllowedException::class);

        $this->orderService->confirm($order, '10');
    }

    #[Test]
    public function the_current_database_status_wins_over_a_stale_model(): void
    {
        $order = Order::factory()->create();
        $staleCopy = Order::find($order->id);

        $this->orderService->confirm($order, '10');

        // Another admin tab still shows the order as new: the service re-reads the locked row.
        $this->assertSame(OrderStatus::New, $staleCopy->status);
        $this->expectException(OrderOperationNotAllowedException::class);

        $this->orderService->cancel($staleCopy);
    }

    #[Test]
    public function the_amount_can_be_corrected_until_the_order_is_paid(): void
    {
        $order = Order::factory()->confirmed('45.50')->create();

        $this->orderService->updateAmount($order, '48.20');
        $this->assertSame('48.20', $order->fresh()->amount);

        $this->orderService->markAsPaid($order);

        $this->expectException(OrderOperationNotAllowedException::class);
        $this->orderService->updateAmount($order, '50.00');
    }

    #[Test]
    public function the_amount_of_new_or_cancelled_orders_cannot_be_set_directly(): void
    {
        foreach ([Order::factory()->create(), Order::factory()->cancelled()->create()] as $order) {
            try {
                $this->orderService->updateAmount($order, '10');
                $this->fail('Only confirmed orders have an amount.');
            } catch (OrderOperationNotAllowedException) {
                $this->assertNull($order->fresh()->amount);
            }
        }
    }

    #[Test]
    public function a_confirmed_order_can_be_marked_as_paid_and_back(): void
    {
        Carbon::setTestNow('2026-11-03 18:00:00');
        $order = Order::factory()->confirmed()->create();

        $this->orderService->markAsPaid($order);
        $this->assertTrue($order->fresh()->isPaid());
        $this->assertTrue($order->fresh()->paid_at->equalTo(now()));

        $this->orderService->markAsUnpaid($order);
        $this->assertFalse($order->fresh()->isPaid());
    }

    #[Test]
    public function an_order_cannot_be_paid_twice(): void
    {
        $order = Order::factory()->paid()->create(['paid_at' => '2026-11-01 09:00:00']);

        try {
            $this->orderService->markAsPaid($order);
            $this->fail('A paid order must not be paid again.');
        } catch (OrderOperationNotAllowedException) {
            $this->assertSame('2026-11-01 09:00:00', $order->fresh()->paid_at->format('Y-m-d H:i:s'));
        }
    }

    #[Test]
    public function only_confirmed_orders_can_be_paid(): void
    {
        foreach ([Order::factory()->create(), Order::factory()->cancelled()->create()] as $order) {
            try {
                $this->orderService->markAsPaid($order);
                $this->fail('Only confirmed orders can be paid.');
            } catch (OrderOperationNotAllowedException) {
                $this->assertNull($order->fresh()->paid_at);
            }
        }
    }

    #[Test]
    public function an_unpaid_order_cannot_be_marked_as_unpaid(): void
    {
        $this->expectException(OrderOperationNotAllowedException::class);

        $this->orderService->markAsUnpaid(Order::factory()->confirmed()->create());
    }
}
