<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Jobs\ProcessTapWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * POST /api/v1/webhooks/tap
     *
     * Receives Tap webhook payloads. Verifies HMAC-SHA256 signature
     * before dispatching the processing job.
     *
     * Returns 200 immediately — Tap retries only twice.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Step 1: Verify webhook signature
        if (! $this->verifySignature($request)) {
            Log::warning('Tap webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Step 2: Extract charge ID from payload
        $chargeId = $request->input('id');

        if (! $chargeId) {
            Log::warning('Tap webhook missing charge ID', [
                'status'   => $request->input('status'),
                'currency' => $request->input('currency'),
            ]);
            return response()->json(['error' => 'Missing charge ID'], 400);
        }

        // Step 3: Dispatch async job for processing
        ProcessTapWebhookJob::dispatch($chargeId);

        Log::info('Tap webhook received and queued', ['charge_id' => $chargeId]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Verify Tap webhook HMAC-SHA256 signature.
     *
     * Tap computes the hashstring as:
     *   HMAC-SHA256( "x_id{id}x_amount{amount}x_currency{currency}x_status{status}", secret )
     *
     * Amount is normalized to 3 decimal places (e.g. "10.500").
     *
     * @see https://developers.tap.company/docs/webhook
     */
    private function verifySignature(Request $request): bool
    {
        $webhookSecret = config('services.tap.webhook_secret');

        if (empty($webhookSecret)) {
            // In production, reject unsigned webhooks — secret MUST be configured.
            if (app()->isProduction()) {
                Log::error('Tap webhook secret not configured in production — rejecting webhook');
                return false;
            }

            Log::warning('Tap webhook secret not configured — skipping verification (non-production)');
            return true;
        }

        $hashstring = $request->header('hashstring');

        if (! $hashstring) {
            return false;
        }

        $chargeId = $request->input('id', '');
        $amount   = number_format((float) $request->input('amount', 0), 3, '.', '');
        $currency = $request->input('currency', 'BHD');
        $status   = $request->input('status', '');

        $payload  = "x_id{$chargeId}x_amount{$amount}x_currency{$currency}x_status{$status}";
        $computed = hash_hmac('sha256', $payload, $webhookSecret);

        return hash_equals($computed, $hashstring);
    }
}
