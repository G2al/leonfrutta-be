<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A free-text order request sent by a customer through the public API.
 *
 * Status, amount, payment and the lifecycle timestamps are deliberately not
 * mass assignable: they are changed only by App\Services\Orders\OrderService.
 *
 * The tracking token is a random UUID given to the customer: it is the only
 * way to read an order publicly, so sequential ids are never exposed.
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'business_name',
        'phone',
        'message',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => OrderStatus::New->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * The id stays an auto-increment: only the tracking token is a UUID.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['tracking_token'];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => "{$this->first_name} {$this->last_name}");
    }

    protected function paymentStatus(): Attribute
    {
        return Attribute::get(fn (): PaymentStatus => $this->isPaid() ? PaymentStatus::Paid : PaymentStatus::Unpaid);
    }

    /**
     * Number of orders for every status, in a single query.
     *
     * @return array<string, int> keyed by OrderStatus value, statuses without orders count 0
     */
    public static function countByStatus(): array
    {
        $counts = static::query()
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(OrderStatus::cases())
            ->mapWithKeys(fn (OrderStatus $status): array => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    public function isNew(): bool
    {
        return $this->status === OrderStatus::New;
    }

    public function isConfirmed(): bool
    {
        return $this->status === OrderStatus::Confirmed;
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    /**
     * The amount can be corrected (e.g. after weighing) until the order is paid.
     */
    public function hasEditableAmount(): bool
    {
        return $this->isConfirmed() && ! $this->isPaid();
    }

    public function canBeMarkedAsPaid(): bool
    {
        return $this->isConfirmed() && ! $this->isPaid() && $this->amount !== null;
    }

    public function canBeMarkedAsUnpaid(): bool
    {
        return $this->isConfirmed() && $this->isPaid();
    }
}
