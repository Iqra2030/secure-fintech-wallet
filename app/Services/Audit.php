<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class Audit
{
    public static function write(string $action, string $outcome, ?string $reference = null): void
    {
        DB::table('audit_events')->insert([
            'actor_id' => auth()->id(), 'action' => $action, 'outcome' => $outcome,
            'reference' => $reference, 'ip' => request()->ip(), 'created_at' => now(),
        ]);
    }

    public static function failure(string $action, string $reason, ?int $actor = null): void
    {
        $record = [
            'actor_id' => $actor ?? auth()->id(), 'action' => $action,
            'outcome' => $reason, 'reference' => null,
            'ip' => request()->ip(), 'created_at' => now(),
        ];
        try {
            DB::table('audit_events')->insert($record);
        } catch (Throwable $auditError) {
            // An unavailable database must not mask the original transfer error.
            // This fallback is operational evidence, not tamper-proof storage.
            Log::warning('Financial operation audit unavailable', [
                'actor_id' => $record['actor_id'], 'action' => $action, 'outcome' => $reason,
            ]);
        }
    }
}
