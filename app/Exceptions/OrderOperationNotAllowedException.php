<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use App\Models\Order;
use DomainException;

/**
 * Raised when an order is asked to do something its current state forbids:
 * a wrong status transition, a payment on an unconfirmed order, and so on.
 */
class OrderOperationNotAllowedException extends DomainException
{
    public static function statusTransition(Order $order, OrderStatus $target): self
    {
        return new self(sprintf(
            'La richiesta #%d è già nello stato "%s" e non può passare allo stato "%s".',
            $order->getKey(),
            $order->status->getLabel(),
            $target->getLabel(),
        ));
    }

    public static function amountNotEditable(Order $order): self
    {
        return new self(sprintf(
            'L\'importo della richiesta #%d si può modificare solo se è confermata e non ancora pagata.',
            $order->getKey(),
        ));
    }

    public static function cannotBeMarkedAsPaid(Order $order): self
    {
        return new self(sprintf(
            'La richiesta #%d si può segnare come pagata solo se è confermata, ha un importo e non è già pagata.',
            $order->getKey(),
        ));
    }

    public static function cannotBeMarkedAsUnpaid(Order $order): self
    {
        return new self(sprintf(
            'La richiesta #%d non risulta pagata.',
            $order->getKey(),
        ));
    }
}
