<?php

namespace App\Exceptions;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every error raised under /api as the same JSON envelope:
 * { "success": false, "message": "...", "errors"?: {...} }.
 *
 * Messages are generic on purpose: stack traces and internals are never
 * exposed, the exception is still logged by Laravel's reporter.
 */
class ApiExceptionRenderer
{
    public function __invoke(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $exception instanceof ValidationException => $this->validationFailed($exception),
            $exception instanceof ThrottleRequestsException => $this->tooManyRequests($exception),
            $exception instanceof HttpExceptionInterface => $this->httpError($exception),
            default => $this->unexpectedError(),
        };
    }

    private function validationFailed(ValidationException $exception): JsonResponse
    {
        return $this->error(
            'I dati inviati non sono validi. Controlla i campi evidenziati.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ['errors' => $exception->errors()],
        );
    }

    private function tooManyRequests(ThrottleRequestsException $exception): JsonResponse
    {
        return $this->error(
            'Hai inviato troppe richieste. Riprova tra qualche istante.',
            Response::HTTP_TOO_MANY_REQUESTS,
            headers: $exception->getHeaders(),
        );
    }

    private function httpError(HttpExceptionInterface $exception): JsonResponse
    {
        $message = match ($exception->getStatusCode()) {
            Response::HTTP_NOT_FOUND => 'La risorsa richiesta non esiste.',
            Response::HTTP_METHOD_NOT_ALLOWED => 'Metodo HTTP non consentito per questa risorsa.',
            default => 'Impossibile elaborare la richiesta.',
        };

        return $this->error($message, $exception->getStatusCode(), headers: $exception->getHeaders());
    }

    private function unexpectedError(): JsonResponse
    {
        return $this->error(
            'Si è verificato un errore inatteso. Riprova più tardi.',
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, string>  $headers
     */
    private function error(string $message, int $status, array $extra = [], array $headers = []): JsonResponse
    {
        return new JsonResponse(['success' => false, 'message' => $message, ...$extra], $status, $headers);
    }
}
