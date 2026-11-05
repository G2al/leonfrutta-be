<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The administrator has confirmed an order request and set its amount.
 */
class OrderConfirmed
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
    ) {}
}
