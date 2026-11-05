<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The administrator has registered the payment of a confirmed order.
 */
class OrderPaid
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
    ) {}
}
