<?php

declare(strict_types=1);

namespace App\Modules\Payments\Jobs;

use App\Modules\Payments\Models\TapTransaction;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled every 15 minutes. Finds tap_transactions stuck in 'initiated'
 * for > 30 minutes and resolves their real status from Tap API.
 */
class CheckStalePaymentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PaymentService $paymentService): void
    {
        $staleTransactions = TapTransaction::where('status', 'initiated')
            ->where('created_at', '<', now()->subMinutes(30))
            ->whereNotNull('tap_charge_id')
            ->get();

        foreach ($staleTransactions as $transaction) {
            try {
                $paymentService->handleChargeResult($transaction->tap_charge_id);
            } catch (\Throwable $e) {
                Log::warning('Failed to resolve stale payment', [
                    'tap_charge_id' => $transaction->tap_charge_id,
                    'error' => $e->getMessage(),
                ]);
                // Continue to next — retry on next scheduled run
            }
        }

        if ($staleTransactions->isNotEmpty()) {
            Log::info('Checked stale payments', ['count' => $staleTransactions->count()]);
        }
    }
}
