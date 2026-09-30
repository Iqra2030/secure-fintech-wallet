<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use App\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class WalletController
{
    private function transactions(int $owner)
    {
        // Explicit customer-facing projection: no replay keys or internal hashes.
        return DB::table('transfers as t')
            ->join('users as sender', 'sender.id', '=', 't.sender_id')
            ->join('users as receiver', 'receiver.id', '=', 't.receiver_id')
            ->where(fn ($q) => $q->where('t.sender_id', $owner)->orWhere('t.receiver_id', $owner))
            ->orderByDesc('t.created_at')->limit(50)
            ->get(['t.id', 't.sender_id', 'sender.name as sender_name', 't.receiver_id',
                'receiver.name as receiver_name', 't.amount_minor', 't.status', 't.created_at']);
    }

    public function dashboard(Request $request)
    {
        $owner = $request->user()->id;
        return view('dashboard', [
            'wallet' => DB::table('wallets')->where('user_id', $owner)->first(['user_id', 'balance_minor']),
            'customers' => DB::table('users')->whereIn('id', DB::table('beneficiaries')
                ->select('beneficiary_user_id')->where('user_id', $owner))->orderBy('name')->get(['id', 'name', 'email']),
            'transfers' => $this->transactions($owner),
            'entries' => DB::table('ledger_entries')->where('user_id', $owner)->orderByDesc('id')->limit(20)
                ->get(['created_at', 'description', 'transfer_id', 'payment_event_id', 'amount_minor']),
        ]);
    }

    public function show(int $owner)
    {
        if (!config('lab.vulnerable') && Gate::denies('view-wallet', $owner)) {
            Audit::write('wallet.read', 'denied', (string) $owner);
            abort(403);
        }
        $wallet = DB::table('wallets')->where('user_id', $owner)->first(['user_id', 'balance_minor']);
        abort_unless($wallet, 404);
        return response()->json(['wallet' => $wallet, 'transactions' => $this->transactions($owner)]);
    }

    public function transfer(Request $request, TransferService $service)
    {
        if (!config('lab.vulnerable')) {
            $key = 'transfer:'.$request->user()->id;
            if (RateLimiter::tooManyAttempts($key, 10)) {
                Audit::write('transfer', 'throttled');
                abort(429, 'Transfer rate limit reached.');
            }
            RateLimiter::hit($key, 60);
        }
        try {
            $data = $request->validate([
                'receiver_id' => 'required|integer|exists:users,id',
                'amount' => config('lab.vulnerable')
                    ? 'required|regex:/^-?[0-9]{1,7}(\.[0-9]{1,2})?$/'
                    : 'required|regex:/^[0-9]{1,7}(\.[0-9]{1,2})?$/|numeric|gt:0|lte:1000000',
                'idempotency_key' => 'required|string|max:100',
            ]);
        } catch (ValidationException $error) {
            // Validation occurs before the service transaction, so audit it here.
            Audit::failure('transfer', 'validation_rejected');
            throw $error;
        }

        // Money is parsed as decimal text into integer paisa, never as a float.
        $negative = str_starts_with($data['amount'], '-');
        $parts = explode('.', ltrim($data['amount'], '-'));
        $minor = ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
        if ($negative) {
            $minor = -$minor;
        }
        // The session supplies sender identity; the service enforces self-transfer rules.
        $transfer = $service->send($request->user()->id, (int) $data['receiver_id'], $minor, $data['idempotency_key']);
        $publicTransfer = array_intersect_key((array) $transfer, array_flip([
            'id', 'sender_id', 'receiver_id', 'amount_minor', 'status', 'created_at',
        ]));
        return $request->expectsJson()
            ? response()->json($publicTransfer)
            : redirect('/dashboard')->with('success', 'Transfer completed. Reference: '.$transfer->id);
    }
}
