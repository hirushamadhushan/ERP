<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePaymentAccountRequest;
use App\Http\Requests\SavePaymentAccountTypeRequest;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountDeposit;
use App\Models\PaymentAccountType;
use App\Models\PaymentAccountTransfer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Class PaymentAccountController
 *
 * Manages bank accounts, cash registers, and general ledgers for cash flow tracking.
 * Ensures double-entry integrity, immutable deposit/transfer trails, and atomic balance updates.
 */
class PaymentAccountController extends Controller
{
    /**
     * Display account transaction book (general ledger) with calculated running balance.
     *
     * @param PaymentAccount $paymentAccount
     * @return \Illuminate\View\View
     */
    public function book(PaymentAccount $paymentAccount)
    {
        try {
            $paymentAccount->load(['type', 'subType', 'creator']);
            $opening = PaymentAccountTransferController::cents($paymentAccount->opening_balance);

            $entries = collect([[
                'date' => Carbon::parse($paymentAccount->opening_balance_at ?? $paymentAccount->created_at),
                'description' => 'Opening Balance',
                'debit' => max(0, $opening),
                'credit' => max(0, -$opening),
                'creator' => $paymentAccount->creator?->name ?? 'System',
                'note' => '',
                'transfer' => null,
            ]]);

            $transfers = PaymentAccountTransfer::with(['source', 'destination', 'creator'])
                ->where('from_account_id', $paymentAccount->id)
                ->orWhere('to_account_id', $paymentAccount->id)
                ->orderBy('id')
                ->get();

            foreach ($transfers as $transfer) {
                $outgoing = $transfer->from_account_id == $paymentAccount->id;
                $amount = PaymentAccountTransferController::cents($transfer->amount);
                $entries->push([
                    'date' => $transfer->transferred_at,
                    'description' => 'Fund Transfer ' . ($outgoing ? 'to ' . $transfer->destination->name : 'from ' . $transfer->source->name),
                    'debit' => $outgoing ? 0 : $amount,
                    'credit' => $outgoing ? $amount : 0,
                    'creator' => $transfer->creator?->name ?? 'System',
                    'note' => $transfer->note,
                    'transfer' => $transfer,
                ]);
            }

            $deposits = PaymentAccountDeposit::with(['account', 'source', 'creator'])
                ->where(fn ($query) => $query->where('account_id', $paymentAccount->id)->orWhere('from_account_id', $paymentAccount->id))
                ->orderBy('id')
                ->get();

            foreach ($deposits as $deposit) {
                $target = $deposit->account_id == $paymentAccount->id;
                $entries->push([
                    'date' => $deposit->deposited_at,
                    'description' => $target ? 'Deposit' . ($deposit->source ? ' from ' . $deposit->source->name : '') : 'Deposit to ' . $deposit->account->name,
                    'debit' => $target ? PaymentAccountTransferController::cents($deposit->amount) : 0,
                    'credit' => $target ? 0 : PaymentAccountTransferController::cents($deposit->amount),
                    'creator' => $deposit->creator?->name ?? 'System',
                    'note' => $deposit->note,
                    'transfer' => null,
                ]);
            }

            $balance = 0;
            $entries = $entries->sortBy('date')->values()->map(function ($entry) use (&$balance) {
                $balance += $entry['debit'] - $entry['credit'];
                return $entry + ['balance' => $balance];
            });

            return view('payment-accounts.book', [
                'account' => $paymentAccount,
                'entries' => $entries,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to load payment account ledger book', [
                'account_id' => $paymentAccount->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Unable to retrieve account ledger at this time.');
        }
    }

    /**
     * Update the opening balance for a specific payment account.
     *
     * @param Request $request
     * @param PaymentAccount $paymentAccount
     * @return RedirectResponse|JsonResponse
     */
    public function updateOpeningBalance(Request $request, PaymentAccount $paymentAccount): RedirectResponse|JsonResponse
    {
        try {
            $data = $request->validate([
                'amount' => ['required', 'numeric', 'regex:/^-?\d{1,12}(\.\d{1,2})?$/'],
                'date' => ['required', 'date_format:Y-m-d\TH:i'],
            ]);

            DB::transaction(function () use ($paymentAccount, $data) {
                $account = PaymentAccount::whereKey($paymentAccount->id)->lockForUpdate()->firstOrFail();
                // Let the database perform decimal arithmetic without binary floating-point rounding.
                DB::update(
                    'UPDATE payment_accounts SET current_balance = current_balance - opening_balance + ?, opening_balance = ?, opening_balance_at = ?, updated_at = ? WHERE id = ?',
                    [
                        $data['amount'],
                        $data['amount'],
                        str_replace('T', ' ', $data['date']) . ':00',
                        now(),
                        $account->id,
                    ]
                );
            });

            return $this->result('Opening balance updated successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to update opening balance for payment account', [
                'account_id' => $paymentAccount->id,
                'error' => $e->getMessage(),
            ]);

            return $this->failure('Unable to update opening balance. Please try again.');
        }
    }

    /**
     * List all payment accounts and top-level account types.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        try {
            return view('payment-accounts.index', [
                'accounts' => PaymentAccount::with(['type', 'subType', 'details', 'creator'])->latest()->get(),
                'types' => PaymentAccountType::with('children')->whereNull('parent_id')->orderBy('name')->get(),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to load payment accounts list', ['error' => $e->getMessage()]);
            return back()->with('error', 'Unable to load payment accounts.');
        }
    }

    /**
     * Retrieve hierarchical account type options for form selectors.
     *
     * @return JsonResponse
     */
    public function typeOptions(): JsonResponse
    {
        try {
            $types = PaymentAccountType::with('children:id,parent_id,name')
                ->whereNull('parent_id')
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json($types);
        } catch (Throwable $e) {
            Log::error('Failed to retrieve payment account type options', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Unable to fetch account types'], 500);
        }
    }

    /**
     * Store a newly created payment account.
     *
     * @param SavePaymentAccountRequest $request
     * @return RedirectResponse|JsonResponse
     */
    public function store(SavePaymentAccountRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $this->persist(new PaymentAccount, $request);
            return $this->result('Payment account added successfully.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to store payment account', [
                'error' => $e->getMessage(),
                'payload' => $request->except(['_token']),
            ]);

            return $this->failure('Failed to create payment account. Please try again.');
        }
    }

    /**
     * Update an existing payment account.
     *
     * @param SavePaymentAccountRequest $request
     * @param PaymentAccount $paymentAccount
     * @return RedirectResponse|JsonResponse
     */
    public function update(SavePaymentAccountRequest $request, PaymentAccount $paymentAccount): RedirectResponse|JsonResponse
    {
        try {
            $this->persist($paymentAccount, $request);
            return $this->result('Payment account updated successfully.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to update payment account', [
                'account_id' => $paymentAccount->id,
                'error' => $e->getMessage(),
            ]);

            return $this->failure('Failed to update payment account. Please try again.');
        }
    }

    /**
     * Delete a payment account if it has no associated transfers or deposits.
     *
     * @param PaymentAccount $paymentAccount
     * @return RedirectResponse|JsonResponse
     */
    public function destroy(PaymentAccount $paymentAccount): RedirectResponse|JsonResponse
    {
        try {
            if (PaymentAccountTransfer::where('from_account_id', $paymentAccount->id)->orWhere('to_account_id', $paymentAccount->id)->exists()) {
                return $this->failure('Accounts with fund transfers cannot be deleted.');
            }
            if (PaymentAccountDeposit::where('account_id', $paymentAccount->id)->orWhere('from_account_id', $paymentAccount->id)->exists()) {
                return $this->failure('Accounts with deposits cannot be deleted.');
            }

            $paymentAccount->delete();
            return $this->result('Payment account deleted successfully.');
        } catch (Throwable $e) {
            Log::error('Failed to delete payment account', [
                'account_id' => $paymentAccount->id,
                'error' => $e->getMessage(),
            ]);

            return $this->failure('Failed to delete payment account.');
        }
    }

    /**
     * Close (deactivate) a payment account.
     *
     * @param PaymentAccount $paymentAccount
     * @return RedirectResponse|JsonResponse
     */
    public function close(PaymentAccount $paymentAccount): RedirectResponse|JsonResponse
    {
        try {
            $paymentAccount->update(['is_active' => false]);
            return $this->result('Payment account closed successfully.');
        } catch (Throwable $e) {
            Log::error('Failed to close payment account', [
                'account_id' => $paymentAccount->id,
                'error' => $e->getMessage(),
            ]);

            return $this->failure('Failed to close payment account.');
        }
    }

    /**
     * Store a new account type or subtype.
     *
     * @param SavePaymentAccountTypeRequest $request
     * @return RedirectResponse|JsonResponse
     */
    public function storeType(SavePaymentAccountTypeRequest $request): RedirectResponse|JsonResponse
    {
        try {
            PaymentAccountType::create($request->validated());
            return $this->result('Account type added successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to store payment account type', ['error' => $e->getMessage()]);
            return $this->failure('Failed to create account type.');
        }
    }

    /**
     * Update an existing account type or subtype.
     *
     * @param SavePaymentAccountTypeRequest $request
     * @param PaymentAccountType $paymentAccountType
     * @return RedirectResponse|JsonResponse
     */
    public function updateType(SavePaymentAccountTypeRequest $request, PaymentAccountType $paymentAccountType): RedirectResponse|JsonResponse
    {
        try {
            $paymentAccountType->update($request->validated());
            return $this->result('Account type updated successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to update payment account type', [
                'type_id' => $paymentAccountType->id,
                'error' => $e->getMessage(),
            ]);

            return $this->failure('Failed to update account type.');
        }
    }

    /**
     * Delete an account type if it has no child subtypes or linked accounts.
     *
     * @param PaymentAccountType $paymentAccountType
     * @return RedirectResponse|JsonResponse
     */
    public function destroyType(PaymentAccountType $paymentAccountType): RedirectResponse|JsonResponse
    {
        try {
            if ($paymentAccountType->children()->exists() || $paymentAccountType->accounts()->exists()) {
                return $this->failure('This account type is linked to an account or sub account type and cannot be deleted.');
            }

            $paymentAccountType->delete();
            return $this->result('Account type deleted successfully.');
        } catch (Throwable $e) {
            Log::error('Failed to delete payment account type', [
                'type_id' => $paymentAccountType->id,
                'error' => $e->getMessage(),
            ]);

            return $this->failure('Failed to delete account type.');
        }
    }

    /**
     * Persist account attributes and custom key-value details within an atomic transaction.
     *
     * @param PaymentAccount $account
     * @param SavePaymentAccountRequest $request
     * @return void
     */
    private function persist(PaymentAccount $account, SavePaymentAccountRequest $request): void
    {
        $data = $request->validated();
        $details = $data['details'] ?? [];
        unset($data['details']);

        if (filled($data['payment_account_sub_type_id'] ?? null)) {
            $subType = PaymentAccountType::findOrFail($data['payment_account_sub_type_id']);
            abort_unless((int) $subType->parent_id === (int) ($data['payment_account_type_id'] ?? 0), 422, 'The selected account subtype does not belong to the selected account type.');
        }

        DB::transaction(function () use ($account, $data, $details) {
            if ($account->exists) {
                $account = PaymentAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
                DB::update('UPDATE payment_accounts SET current_balance = current_balance - opening_balance + ? WHERE id = ?', [$data['opening_balance'], $account->id]);
            }
            $data['is_active'] = (bool) ($data['is_active'] ?? ($account->exists ? $account->is_active : true));
            if (! $account->exists) {
                $data['created_by'] = auth()->id();
                $data['current_balance'] = $data['opening_balance'];
            }
            $account->fill($data)->save();
            $account->details()->delete();
            $account->details()->createMany(
                collect($details)
                    ->take(2)
                    ->filter(fn ($detail) => filled($detail['label'] ?? null) && filled($detail['value'] ?? null))
                    ->values()
                    ->map(fn ($detail, $index) => [
                        'label' => $detail['label'],
                        'value' => $detail['value'],
                        'display_order' => $index + 1,
                    ])
                    ->all()
            );
        });
    }

    /**
     * Return consistent success responses for AJAX and standard browser requests.
     *
     * @param string $message
     * @return RedirectResponse|JsonResponse
     */
    private function result(string $message): RedirectResponse|JsonResponse
    {
        return request()->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('status', $message);
    }

    /**
     * Return consistent failure responses for AJAX and standard browser requests.
     *
     * @param string $message
     * @return RedirectResponse|JsonResponse
     */
    private function failure(string $message): RedirectResponse|JsonResponse
    {
        return request()->expectsJson()
            ? response()->json(['message' => $message], 422)
            : back()->with('error', $message);
    }
}
