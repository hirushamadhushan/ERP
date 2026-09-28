<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Converts redirect-and-flash form responses into JSON for opted-in AJAX forms.
 * Normal browser submissions keep Laravel's original redirect behaviour.
 */
final class AsyncFormResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        // Opt-in only: ordinary navigation and existing JSON endpoints keep their contracts.
        if ($request->header('X-Async-Form') !== '1' || ! $response instanceof RedirectResponse || ! $request->user()) {
            return $response;
        }

        $session = $request->session();
        // Some legacy forms use named flash errors instead of the validation
        // error bag; return both shapes as a consistent 422 response.
        foreach (['error', 'unit_error', 'category_error', 'group_error'] as $key) {
            if ($session->has($key)) {
                return response()->json(['message' => $session->pull($key)], 422);
            }
        }
        if ($session->has('errors')) {
            $errors = $session->pull('errors')->getBag('default')->messages();
            return response()->json(['message' => 'Please check the form fields.', 'errors' => $errors], 422);
        }
        return response()->json([
            'message' => $session->pull('success') ?? $session->pull('status') ?? 'Changes saved.',
            'redirect' => $response->getTargetUrl(),
        ]);
    }
}
