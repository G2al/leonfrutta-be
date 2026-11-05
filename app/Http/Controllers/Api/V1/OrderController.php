<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Orders\OrderService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->submit($request->validated());

        return OrderResource::make($order)
            ->additional([
                'success' => true,
                'message' => 'La tua richiesta è stata inviata con successo.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * The order is found by its secret tracking token, never by its id.
     */
    public function show(Order $order): JsonResponse
    {
        return OrderResource::make($order)
            ->additional([
                'success' => true,
                'message' => 'Dettagli della richiesta.',
            ])
            ->response();
    }
}
