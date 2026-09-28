<?php

namespace App\Http\Controllers;

use App\Models\PaymentAccount;
use App\Models\PaymentAccountDeposit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a cash deposit or an internal deposit between two payment accounts.
 * Account balances and the audit record always change as one database action.
 */
class PaymentAccountDepositController extends Controller
{
    public function store(Request $request)
    {
        // Amount is restricted to two decimal places so balance arithmetic
        // remains compatible with the DECIMAL(18,2) database columns.
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'account_id' => ['required', 'exists:payment_accounts,id'],
            'from_account_id' => ['nullable', 'different:account_id', 'exists:payment_accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'date' => ['required', 'date_format:Y-m-d\TH:i'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($data): void {
            $sourceAccountId = $data['from_account_id'] ?? null;
            $existing = PaymentAccountDeposit::where('request_id', $data['request_id'])->first();

            if ($existing) {
                // Retrying the same browser request is safe. Reusing its UUID
                // with different financial data must be rejected, never treated
                // as a successful deposit.
                $sameRequest = $existing->created_by === auth()->id()
                    && $existing->account_id == $data['account_id']
                    && $existing->from_account_id == $sourceAccountId
                    && PaymentAccountTransferController::cents($existing->amount)
                        === PaymentAccountTransferController::cents($data['amount']);

                if (! $sameRequest) {
                    throw ValidationException::withMessages([
                        'request_id' => 'This deposit request was already used.',
                    ]);
                }

                return;
            }

            // Lock both accounts before checking funds or changing balances.
            // This prevents two simultaneous deposits from spending the same
            // source balance twice.
            $accountIds = array_filter([$data['account_id'], $sourceAccountId]);
            $accounts = PaymentAccount::whereIn('id', $accountIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $target = $accounts->get($data['account_id']);
            $source = $sourceAccountId ? $accounts->get($sourceAccountId) : null;

            if (! $target?->is_active || ($source && ! $source->is_active)) {
                throw ValidationException::withMessages([
                    'account_id' => 'Select active accounts.',
                ]);
            }

            // Compare integer cents, not PHP floats, to avoid rounding errors
            // when validating that an internal source account has enough funds.
            if ($source && PaymentAccountTransferController::cents($source->current_balance)
                < PaymentAccountTransferController::cents($data['amount'])) {
                throw ValidationException::withMessages([
                    'amount' => 'The source account has insufficient funds.',
                ]);
            }

            PaymentAccountDeposit::create([
                'request_id' => $data['request_id'],
                'account_id' => $target->id,
                'from_account_id' => $source?->id,
                'amount' => $data['amount'],
                'deposited_at' => str_replace('T', ' ', $data['date']).':00',
                'note' => $data['note'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // SQL decimal arithmetic keeps the stored balance exact and avoids
            // stale Eloquent model values overwriting concurrent updates.
            DB::update(
                'UPDATE payment_accounts SET current_balance = current_balance + ?, updated_at = ? WHERE id = ?',
                [$data['amount'], now(), $target->id]
            );

            if ($source) {
                DB::update(
                    'UPDATE payment_accounts SET current_balance = current_balance - ?, updated_at = ? WHERE id = ?',
                    [$data['amount'], now(), $source->id]
                );
            }
        });

        return response()->json(['message' => 'Deposit completed successfully.']);
    }
}
