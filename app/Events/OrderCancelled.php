<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The administrator has cancelled an order request.
 */
class OrderCancelled
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
    ) {}
}
