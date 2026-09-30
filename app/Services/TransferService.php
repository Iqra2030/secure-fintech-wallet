<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class TransferService
{
    public function send(int $sender, int $receiver, int $amount, string $key, ?Closure $afterDebit = null): object
    {
        $hash = hash('sha256', json_encode([$sender, $receiver, $amount]));
        $operation = function () use ($sender, $receiver, $amount, $key, $hash, $afterDebit) {
            // Lock in a stable order, then make the funds/idempotency decisions.
            $query = DB::table('wallets')->whereIn('user_id', [$sender, $receiver])->orderBy('user_id');
            if (!config('lab.vulnerable')) {
                $query->lockForUpdate();
            }
            $wallets = $query->get()->keyBy('user_id');
            if (!isset($wallets[$sender], $wallets[$receiver])) {
                throw ValidationException::withMessages(['receiver_id' => 'Wallet not found.']);
            }

            if (!config('lab.vulnerable')) {
                $existing = DB::table('transfers')->where('sender_id', $sender)
                    ->where('idempotency_key', $key)->first();
                if ($existing) {
                    abort_unless(hash_equals($existing->request_hash, $hash), 409, 'Idempotency key reused with different details.');
                    return $existing;
                }
                if ($amount <= 0 || $amount > 100000000 || $sender === $receiver) {
                    throw ValidationException::withMessages(['amount' => 'Invalid transfer.']);
                }
            }

            $beneficiary = DB::table('beneficiaries')->where('user_id', $sender)
                ->where('beneficiary_user_id', $receiver);
            if (!config('lab.vulnerable')) {
                $beneficiary->lockForUpdate();
            }
            if (!$beneficiary->first()) {
                throw ValidationException::withMessages(['receiver_id' => 'Add this customer as a beneficiary before sending money.']);
            }
            if ($wallets[$sender]->balance_minor < $amount) {
                throw ValidationException::withMessages(['amount' => 'Insufficient funds.']);
            }

            $id = (string) Str::uuid();
            DB::table('wallets')->where('user_id', $sender)->decrement('balance_minor', $amount, ['updated_at' => now()]);
            // Fault injection is available to tests only, never to HTTP input.
            if ($afterDebit) {
                $afterDebit();
            }
            DB::table('wallets')->where('user_id', $receiver)->increment('balance_minor', $amount, ['updated_at' => now()]);
            DB::table('transfers')->insert([
                'id' => $id, 'sender_id' => $sender, 'receiver_id' => $receiver,
                'amount_minor' => $amount, 'idempotency_key' => $key, 'request_hash' => $hash,
                'status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('ledger_entries')->insert([
                ['user_id' => $sender, 'transfer_id' => $id, 'amount_minor' => -$amount, 'description' => 'Transfer sent', 'created_at' => now()],
                ['user_id' => $receiver, 'transfer_id' => $id, 'amount_minor' => $amount, 'description' => 'Transfer received', 'created_at' => now()],
            ]);
            // Completion evidence commits with the financial records.
            Audit::write('transfer', 'completed', $id);
            return DB::table('transfers')->where('id', $id)->first();
        };

        try {
            // The deliberate vulnerable comparison retains non-atomic writes.
            return config('lab.vulnerable') ? $operation() : DB::transaction($operation, 3);
        } catch (Throwable $error) {
            // DB::transaction has already rolled back before failure is recorded.
            // Never log the submitted payload, operation key or exception message.
            $reason = $error instanceof ValidationException ? 'validation_rejected' : 'processing_failed';
            if ($error instanceof HttpExceptionInterface && $error->getStatusCode() === 409) {
                $reason = 'idempotency_conflict';
            }
            Audit::failure('transfer', $reason, $sender);
            throw $error;
        }
    }
}
