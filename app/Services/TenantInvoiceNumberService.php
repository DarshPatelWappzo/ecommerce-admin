<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantInvoiceNumberService
{
    public function __construct(private readonly TenantInvoiceSnapshotBuilder $snapshots) {}

    /** Called inside the issuance transaction after locking the order. */
    public function next(Carbon $date): array
    {
        $year = $date->month >= 4 ? $date->year : $date->year - 1;
        $period = $year . '-' . ($year + 1);
        $prefix = $this->snapshots->settings()['prefix'] ?? config('invoices.prefix');
        if (! is_string($prefix) || ! preg_match('/^[A-Z0-9]{1,3}$/', $prefix)) {
            throw ValidationException::withMessages(['number' => 'Invoice prefix must contain 1–3 uppercase letters or digits.']);
        }
        $table = DB::connection('tenant')->table('invoice_sequences');
        $table->insertOrIgnore(['period' => $period, 'last_number' => 0]);
        $sequence = (clone $table)->where('period', $period)->lockForUpdate()->first();
        $next = $sequence->last_number + 1;
        if ($next > 999999) {
            throw ValidationException::withMessages(['number' => 'The invoice sequence for this financial year is exhausted.']);
        }
        $number = $prefix . '/' . substr((string) $year, -2) . substr((string) ($year + 1), -2) . '/' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        (clone $table)->where('period', $period)->update(['last_number' => $next]);

        return ['number' => $number, 'period' => $period];
    }
}
