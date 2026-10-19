<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection(config('subscriptions.connection'));
        $connection->table('user_packages')->whereNotNull('end_date')->orderBy('id')
            ->chunkById(500, function (Collection $records) use ($connection): void {
                foreach ($records as $record) {
                    $end = Carbon::parse($record->end_date);
                    $graceEnd = $record->grace_expires_at === null
                        ? $end->copy()->addDays(7) : Carbon::parse($record->grace_expires_at);
                    $connection->table('user_packages')->where('id', $record->id)->update([
                        'grace_period_days' => max(0, (int) ceil($end->diffInDays($graceEnd, false))),
                        'grace_expires_at' => $graceEnd,
                    ]);
                }
            });
    }

    /** Preserve historical deadlines on rollback; discarding entitlements is unsafe. */
    public function down(): void {}
};
