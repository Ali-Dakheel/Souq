<?php

declare(strict_types=1);

namespace App\Modules\Payments\Services;

use App\Modules\Payments\Exceptions\TapApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for Tap Payments API v2.
 * No business logic — only raw API calls.
 *
 * @see https://developers.tap.company/reference/api-endpoint
 */
class TapApiService
{
    private string $baseUrl;
    private string $secretKey;

    public function __construct()
    {
        $this->baseUrl   = (string) config('services.tap.base_url', 'https://api.tap.company/v2');
        $this->secretKey = (string) config('services.tap.secret_key', '');
    }

    /**
     * POST /v2/charges/
     *
     * @throws TapApiException
     */
    public function createCharge(array $payload): array
    {
        return $this->post('/charges/', $payload);
    }

    /**
     * GET /v2/charges/{charge_id}
     *
     * @throws TapApiException
     */
    public function retrieveCharge(string $chargeId): array
    {
        return $this->get("/charges/{$chargeId}");
    }

    /**
     * POST /v2/refunds/
     *
     * @throws TapApiException
     */
    public function createRefund(array $payload): array
    {
        return $this->post('/refunds/', $payload);
    }

    /**
     * POST /v2/customers/
     *
     * @throws TapApiException
     */
    public function createCustomer(array $payload): array
    {
        return $this->post('/customers/', $payload);
    }

    /**
     * @throws TapApiException
     */
    private function post(string $path, array $payload): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(15)
                ->retry(1, 500)
                ->post($this->baseUrl . $path, $payload);
        } catch (ConnectionException $e) {
            Log::error('Tap API connection error', ['path' => $path, 'error' => $e->getMessage()]);
            throw new TapApiException('Payment service is temporarily unavailable.', 503, $e);
        }

        if ($response->failed()) {
            $body = $response->json();
            $errorMsg = $body['errors'][0]['description'] ?? 'Unknown Tap API error';
            $errorCode = $body['errors'][0]['code'] ?? 'unknown';

            Log::warning('Tap API error', [
                'path'       => $path,
                'status'     => $response->status(),
                'error_code' => $errorCode,
                'error_msg'  => $errorMsg,
            ]);

            throw new TapApiException($errorMsg, $response->status());
        }

        return $response->json();
    }

    /**
     * @throws TapApiException
     */
    private function get(string $path): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(15)
                ->retry(1, 500)
                ->get($this->baseUrl . $path);
        } catch (ConnectionException $e) {
            Log::error('Tap API connection error', ['path' => $path, 'error' => $e->getMessage()]);
            throw new TapApiException('Payment service is temporarily unavailable.', 503, $e);
        }

        if ($response->failed()) {
            $body = $response->json();
            $errorMsg = $body['errors'][0]['description'] ?? 'Unknown Tap API error';

            throw new TapApiException($errorMsg, $response->status());
        }

        return $response->json();
    }
}
