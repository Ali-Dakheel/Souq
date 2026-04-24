<?php

declare(strict_types=1);

namespace App\Modules\Payments\Jobs;

use App\Modules\Payments\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Processes a Tap webhook payload asynchronously.
 * The webhook endpoint returns 200 immediately and dispatches this job.
 * Idempotent — safe to run multiple times for the same charge.
 * ShouldBeUnique prevents duplicate jobs when Tap retries the webhook.
 */
class ProcessTapWebhookJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly string $tapChargeId,
    ) {}

    /**
     * Unique job key — prevents concurrent processing of the same charge.
     * Tap retries webhooks twice; this ensures only one job runs per charge.
     */
    public function uniqueId(): string
    {
        return "tap_webhook_{$this->tapChargeId}";
    }

    public function handle(PaymentService $paymentService): void
    {
        try {
            $paymentService->handleChargeResult($this->tapChargeId);
        } catch (\Throwable $e) {
            Log::error('Tap webhook processing failed', [
                'tap_charge_id' => $this->tapChargeId,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw so Laravel retries
        }
    }
}
