<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

abstract class Controller
{
    /**
     * Run a write operation atomically and translate integrity conflicts into a
     * message the user can act on. Unexpected failures are rethrown so the
     * global exception handler can log them and return a safe reference ID.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $operation
     * @return TResult
     *
     * @throws Throwable
     */
    protected function databaseTransaction(
        callable $operation,
        string $conflictMessage = 'This change conflicts with existing data.',
        string $errorField = 'operation'
    ): mixed {
        try {
            // Retrying deadlocks is safe because the complete operation is atomic.
            return DB::transaction($operation, 3);
        } catch (QueryException $exception) {
            if ($this->isIntegrityConstraintViolation($exception)) {
                throw ValidationException::withMessages([
                    $errorField => [$conflictMessage],
                ]);
            }

            throw $exception;
        }
    }

    private function isIntegrityConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        // SQLSTATE class 23 covers unique, foreign-key, check and not-null conflicts.
        return str_starts_with($sqlState, '23');
    }
}
