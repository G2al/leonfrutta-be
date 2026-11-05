<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A customer has just sent a new order request through the public API.
 */
class OrderSubmitted
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
    ) {}
}
