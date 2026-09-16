# Error handling

- `bootstrap/app.php` registers the shared exception response in `app/Support/ErrorResponse.php`.
- Validation keeps Laravel field errors and redirects. Passwords, tokens, and two-factor secrets are excluded from flashed input.
- Exceptions return safe HTML or JSON with the original failure status. Database integrity conflicts use 409 and connection failures use 503.
- Unexpected errors include a reference linked to Laravel's exception log context. Look in `storage/logs/laravel.log`, or the configured logging channel. SQL, traces, and exception messages are not returned to users, including when debug mode is enabled.
- The error page is standalone, so it does not require authentication, database access, or CDN styles.
- `public/js/error-handling.js` handles network failures, invalid JSON, session expiry, and server messages. Existing jQuery field validation remains in place.
- Product saves, quick-reference creation, contact loading, and serial Excel import use the shared request helper. Product fields and selected files stay on the page after a failed AJAX save.
- Writes are not automatically retried because a lost response does not prove that the original write failed.
- Product attachment cleanup failures are logged without masking the original failure or reporting an already committed save as failed.
- Controller write operations use `Controller::databaseTransaction()` for atomic commits, deadlock retries, and consistent integrity-conflict messages.
- Domain validation remains beside the business rule that it protects. Unexpected exceptions are allowed to reach the central handler, which avoids duplicate catches, duplicate logs, and leaked implementation details.
- Comments document business invariants and recovery decisions. Routine framework calls are left self-explanatory so comments do not drift away from the code.

Guarded SQLite in-memory feature tests cover exception responses, HTTP headers, validation, AJAX destinations, and existing workflows. Browser simulations cover failed product saves, session expiry, restored buttons, and offline requests.
