<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ErrorResponse
{
    public static function reference(Request $request): string
    {
        if (! $request->attributes->has('error_reference')) {
            $request->attributes->set('error_reference', (string) Str::uuid());
        }

        return $request->attributes->get('error_reference');
    }

    public static function render(Response $response, Throwable $exception, Request $request): Response
    {
        $status = $response->getStatusCode();
        // Preserve Laravel validation redirects, field errors, and authentication redirects.
        if ($status < 400 || $status === 422) {
            return $response;
        }
        if ($exception instanceof QueryException) {
            $state = (string) ($exception->errorInfo[0] ?? '');
            $driverCode = (int) ($exception->errorInfo[1] ?? 0);
            if (str_starts_with($state, '23')) {
                $status = 409;
            } elseif (str_starts_with($state, '08') || in_array($driverCode, [2002, 2006, 2013], true)) {
                $status = 503;
            }
        }
        $messages = [
            400 => ['Invalid request', 'This request could not be processed. Check the information and try again.'],
            401 => ['Sign in required', 'Your session has ended. Sign in again before continuing.'],
            403 => ['Access denied', 'You do not have permission to perform this action.'],
            404 => ['Not found', 'This page or record no longer exists.'],
            405 => ['Action unavailable', 'This action is not available at this address. Return to the page and try again.'],
            409 => ['Record conflict', 'A duplicate or linked record prevents this change. Check the current records before trying again.'],
            413 => ['File too large', 'The upload exceeds the server limit. Choose a smaller file.'],
            419 => ['Session expired', 'Your session expired. Reload the form and sign in again if needed.'],
            429 => ['Too many requests', 'Please wait a moment before trying again.'],
            503 => ['Temporarily unavailable', 'The service is temporarily unavailable. Please try again later.'],
        ];
        [$title, $message] = $messages[$status] ?? ['Something went wrong', 'We could not complete this request. Check the current records before trying again.'];
        $reference = $status >= 500 ? self::reference($request) : null;
        $headers = ['Cache-Control' => 'no-store'];
        foreach (['Retry-After', 'Allow'] as $header) {
            if ($response->headers->has($header)) {
                $headers[$header] = $response->headers->get($header);
            }
        }
        if ($request->expectsJson()) {
            return response()->json(['status' => 'error', 'message' => $message, 'reference' => $reference], $status, $headers);
        }

        // Standalone view: no database, authentication or application-layout dependencies.
        return response()->view('errors.friendly', compact('status', 'title', 'message', 'reference'), $status, $headers);
    }
}
