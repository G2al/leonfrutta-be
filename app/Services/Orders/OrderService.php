<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Events\OrderSubmitted;
use App\Exceptions\OrderOperationNotAllowedException;
use App\Models\Order;
use App\Services\Phone\PhoneNumberNormalizer;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Single entry point for creating order requests and changing them:
 * status (new → confirmed | cancelled), amount and payment.
 */
class OrderService
{
    public const MAX_AMOUNT = 99_999_999.99;

    /** Up to 8 integer digits and 2 decimals: the range of a decimal(10,2) column. */
    private const AMOUNT_PATTERN = '/^\d{1,8}(\.\d{1,2})?$/';

    public function __construct(
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
    ) {}

    /**
     * @param  array{first_name: string, last_name: string, business_name: string, phone: string, message: string}  $customerRequest
     */
    public function submit(array $customerRequest): Order
    {
        $order = Order::create([
            ...$customerRequest,
            'phone' => $this->phoneNumberNormalizer->toE164($customerRequest['phone']),
        ]);

        OrderSubmitted::dispatch($order);

        return $order;
    }

    /**
     * Confirming an order always records how much it is worth.
     *
     * @throws OrderOperationNotAllowedException
     */
    public function confirm(Order $order, string|float $amount): Order
    {
        $amount = $this->normalizeAmount($amount);

        return $this->changeLocked($order, function (Order $lockedOrder) use ($amount): void {
            $this->ensureCanTransition($lockedOrder, OrderStatus::Confirmed);

            $lockedOrder->status = OrderStatus::Confirmed;
            $lockedOrder->amount = $amount;
            $lockedOrder->confirmed_at = now();
        });
    }

    /**
     * @throws OrderOperationNotAllowedException
     */
    public function cancel(Order $order): Order
    {
        return $this->changeLocked($order, function (Order $lockedOrder): void {
            $this->ensureCanTransition($lockedOrder, OrderStatus::Cancelled);

            $lockedOrder->status = OrderStatus::Cancelled;
            $lockedOrder->cancelled_at = now();
        });
    }

    /**
     * @throws OrderOperationNotAllowedException
     */
    public function updateAmount(Order $order, string|float $amount): Order
    {
        $amount = $this->normalizeAmount($amount);

        return $this->changeLocked($order, function (Order $lockedOrder) use ($amount): void {
            if (! $lockedOrder->hasEditableAmount()) {
                throw OrderOperationNotAllowedException::amountNotEditable($lockedOrder);
            }

            $lockedOrder->amount = $amount;
        });
    }

    /**
     * @throws OrderOperationNotAllowedException
     */
    public function markAsPaid(Order $order): Order
    {
        return $this->changeLocked($order, function (Order $lockedOrder): void {
            if (! $lockedOrder->canBeMarkedAsPaid()) {
                throw OrderOperationNotAllowedException::cannotBeMarkedAsPaid($lockedOrder);
            }

            $lockedOrder->paid_at = now();
        });
    }

    /**
     * Undo for a payment registered by mistake.
     *
     * @throws OrderOperationNotAllowedException
     */
    public function markAsUnpaid(Order $order): Order
    {
        return $this->changeLocked($order, function (Order $lockedOrder): void {
            if (! $lockedOrder->canBeMarkedAsUnpaid()) {
                throw OrderOperationNotAllowedException::cannotBeMarkedAsUnpaid($lockedOrder);
            }

            $lockedOrder->paid_at = null;
        });
    }

    private function ensureCanTransition(Order $order, OrderStatus $target): void
    {
        if (! $order->status->canTransitionTo($target)) {
            throw OrderOperationNotAllowedException::statusTransition($order, $target);
        }
    }

    /**
     * Amounts are kept as decimal strings with two digits ("45.50") to avoid
     * float rounding; they must be positive and fit the decimal(10,2) column.
     * A comma is accepted as decimal separator, as typed by Italian users.
     */
    private function normalizeAmount(string|float $amount): string
    {
        $normalized = is_string($amount) ? str_replace(',', '.', trim($amount)) : (string) $amount;

        if (! preg_match(self::AMOUNT_PATTERN, $normalized) || bccomp($normalized, '0', 2) <= 0) {
            throw new InvalidArgumentException("L'importo \"{$amount}\" non è valido.");
        }

        return bcadd($normalized, '0', 2);
    }

    /**
     * The row is locked and re-read inside the transaction, so concurrent clicks
     * (or two admin tabs) always see and change the real current state.
     *
     * @param  Closure(Order): void  $change
     */
    private function changeLocked(Order $order, Closure $change): Order
    {
        $updatedOrder = DB::transaction(function () use ($order, $change): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            $change($lockedOrder);
            $lockedOrder->save();

            return $lockedOrder;
        });

        return $order->setRawAttributes($updatedOrder->getAttributes(), sync: true);
    }
}
