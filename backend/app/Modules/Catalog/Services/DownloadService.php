<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\DownloadableLinkPurchase;
use InvalidArgumentException;
use RuntimeException;

class DownloadService
{
    private const HMAC_ALGO = 'sha256';

    private const TOKEN_TTL_SECONDS = 86400; // 24 hours

    /**
     * Generate a signed download token for a specific purchase + link.
     * Token encodes: link_id, purchase_id, user_id, expires_at unix timestamp.
     * Format: base64url(json_payload).HMAC_signature
     */
    public function generateToken(DownloadableLinkPurchase $purchase): string
    {
        $payload = [
            'link_id' => $purchase->downloadable_link_id,
            'purchase_id' => $purchase->id,
            'user_id' => $purchase->order->user_id,
            'expires_at' => now()->addSeconds(self::TOKEN_TTL_SECONDS)->unix(),
        ];

        $payloadBase64 = rtrim(
            strtr(base64_encode(json_encode($payload)), '+/', '-_'),
            '='
        );
        $sig = hash_hmac(self::HMAC_ALGO, $payloadBase64, (string) config('app.key'));

        return $payloadBase64.'.'.$sig;
    }

    /**
     * Validate token and return the purchase if valid.
     * Throws InvalidArgumentException on invalid/expired token.
     * Throws RuntimeException if downloads exhausted.
     */
    public function validateAndDecodeToken(string $token, int $userId): DownloadableLinkPurchase
    {
        // 1. Split token into payload + signature
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Invalid token format.');
        }

        [$payloadBase64, $sig] = $parts;

        // 2. Verify HMAC with hash_equals
        $expectedSig = hash_hmac(self::HMAC_ALGO, $payloadBase64, (string) config('app.key'));
        if (! hash_equals($expectedSig, $sig)) {
            throw new InvalidArgumentException('Invalid token signature.');
        }

        // 3. Decode payload
        $padding = str_repeat('=', (4 - strlen($payloadBase64) % 4) % 4);
        $json = base64_decode(strtr($payloadBase64.$padding, '-_', '+/'), strict: true);

        if ($json === false) {
            throw new InvalidArgumentException('Failed to decode token payload.');
        }

        $payload = json_decode($json, associative: true);
        if (! is_array($payload)) {
            throw new InvalidArgumentException('Invalid token payload.');
        }

        // 4. Check expires_at > now()
        $expiresAt = $payload['expires_at'] ?? null;
        if ($expiresAt === null || $expiresAt < now()->unix()) {
            throw new InvalidArgumentException('Token has expired.');
        }

        // 5. Check user_id matches authenticated user
        if (($payload['user_id'] ?? null) !== $userId) {
            throw new InvalidArgumentException('Token user mismatch.');
        }

        // 6. Load purchase, check downloads_allowed
        $purchase = DownloadableLinkPurchase::with(['downloadableLink', 'order'])
            ->findOrFail($payload['purchase_id'] ?? 0);

        // Verify ownership via order relationship
        if ($purchase->order->user_id !== $userId) {
            throw new InvalidArgumentException('Unauthorized purchase access.');
        }

        $link = $purchase->downloadableLink;
        if ($link->downloads_allowed > 0 && $purchase->download_count >= $link->downloads_allowed) {
            throw new RuntimeException('Download limit reached.');
        }

        return $purchase;
    }

    /**
     * Record a download: increment download_count, update last_downloaded_at.
     */
    public function recordDownload(DownloadableLinkPurchase $purchase): void
    {
        $purchase->increment('download_count');
        $purchase->update(['last_downloaded_at' => now()]);
    }
}
