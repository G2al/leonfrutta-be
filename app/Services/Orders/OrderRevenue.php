<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Revenue figures of confirmed orders, computed in the business timezone:
 * "today" and "this month" follow the Italian calendar even though dates
 * are stored in UTC.
 */
class OrderRevenue
{
    public function __construct(
        private readonly string $timezone,
    ) {}

    /**
     * Money actually received today.
     */
    public function collectedToday(): string
    {
        $today = Carbon::now($this->timezone);

        return $this->sumPaidBetween($today->copy()->startOfDay(), $today->copy()->endOfDay());
    }

    /**
     * Money actually received in the current month.
     */
    public function collectedThisMonth(): string
    {
        $now = Carbon::now($this->timezone);

        return $this->sumPaidBetween($now->copy()->startOfMonth(), $now->copy()->endOfMonth());
    }

    /**
     * Value of the orders confirmed in the current month, paid or not.
     */
    public function confirmedThisMonth(): string
    {
        $now = Carbon::now($this->timezone);

        return $this->sum($this->confirmedOrders()->whereBetween('confirmed_at', [
            $now->copy()->startOfMonth()->utc(),
            $now->copy()->endOfMonth()->utc(),
        ]));
    }

    /**
     * Confirmed orders still waiting to be paid.
     */
    public function outstanding(): string
    {
        return $this->sum($this->confirmedOrders()->whereNull('paid_at'));
    }

    private function sumPaidBetween(Carbon $from, Carbon $until): string
    {
        return $this->sum($this->confirmedOrders()->whereBetween('paid_at', [$from->utc(), $until->utc()]));
    }

    /**
     * @return Builder<Order>
     */
    private function confirmedOrders(): Builder
    {
        return Order::query()->where('status', OrderStatus::Confirmed);
    }

    /**
     * @param  Builder<Order>  $query
     */
    private function sum(Builder $query): string
    {
        return number_format((float) $query->sum('amount'), 2, '.', '');
    }
}
