<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Services;

use App\Models\User;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\LoyaltyConfig;
use App\Modules\Loyalty\Models\LoyaltyTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoyaltyService
{
    public function getOrCreateAccount(User $user): LoyaltyAccount
    {
        return LoyaltyAccount::firstOrCreate(
            ['user_id' => $user->id],
            ['points_balance' => 0, 'lifetime_points_earned' => 0]
        );
    }

    public function earnPoints(
        User $user,
        int $amountFils,
        string $referenceType,
        int $referenceId,
    ): LoyaltyTransaction {
        $pointsPerFil = (float) $this->config('points_per_fil');
        $points = (int) round($amountFils * $pointsPerFil);

        return DB::transaction(function () use ($user, $points, $referenceType, $referenceId): LoyaltyTransaction {
            $account = LoyaltyAccount::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                $account = LoyaltyAccount::create([
                    'user_id' => $user->id,
                    'points_balance' => 0,
                    'lifetime_points_earned' => 0,
                ]);
            }

            $account->increment('points_balance', $points);
            $account->increment('lifetime_points_earned', $points);

            return LoyaltyTransaction::create([
                'loyalty_account_id' => $account->id,
                'type' => 'earn',
                'points' => $points,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description_en' => 'Points earned',
                'description_ar' => 'نقاط مكتسبة',
            ]);
        });
    }

    public function redeemPoints(
        User $user,
        int $points,
        int $orderId,
        int $orderTotalFils,
    ): int {
        $filsPerPoint = (int) $this->config('fils_per_point');
        $maxRedeemPercent = (float) $this->config('max_redeem_percent');

        $filsValue = $points * $filsPerPoint;
        $maxFilsAllowed = (int) ($orderTotalFils * $maxRedeemPercent);

        if ($filsValue > $maxFilsAllowed) {
            throw ValidationException::withMessages([
                'points' => ['Redemption exceeds the maximum allowed discount for this order.'],
            ]);
        }

        DB::transaction(function () use ($user, $points, $orderId): void {
            $account = LoyaltyAccount::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($account === null || $account->points_balance < $points) {
                throw ValidationException::withMessages([
                    'points' => ['Insufficient points balance.'],
                ]);
            }

            $account->decrement('points_balance', $points);

            LoyaltyTransaction::create([
                'loyalty_account_id' => $account->id,
                'type' => 'redeem',
                'points' => -$points,
                'reference_type' => 'order',
                'reference_id' => $orderId,
                'description_en' => 'Points redeemed',
                'description_ar' => 'نقاط مستردة',
            ]);
        });

        return $filsValue;
    }

    public function getBalance(User $user): int
    {
        $account = LoyaltyAccount::where('user_id', $user->id)->first();

        return $account instanceof LoyaltyAccount ? $account->points_balance : 0;
    }

    /** @return Collection<int, LoyaltyTransaction> */
    public function getHistory(User $user): Collection
    {
        $account = $this->getOrCreateAccount($user);

        return LoyaltyTransaction::where('loyalty_account_id', $account->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function creditStoreCredit(
        User $user,
        int $amountFils,
        string $referenceType,
        int $referenceId,
    ): LoyaltyTransaction {
        $filsPerPoint = (int) $this->config('fils_per_point');
        $points = (int) round($amountFils / $filsPerPoint);

        return DB::transaction(function () use ($user, $points, $referenceType, $referenceId): LoyaltyTransaction {
            $account = LoyaltyAccount::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                $account = LoyaltyAccount::create([
                    'user_id' => $user->id,
                    'points_balance' => 0,
                    'lifetime_points_earned' => 0,
                ]);
            }

            $account->increment('points_balance', $points);
            $account->increment('lifetime_points_earned', $points);

            return LoyaltyTransaction::create([
                'loyalty_account_id' => $account->id,
                'type' => 'store_credit',
                'points' => $points,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description_en' => 'Store credit issued',
                'description_ar' => 'رصيد متجر صادر',
            ]);
        });
    }

    public function manualAdjust(
        User $user,
        int $points,
        string $descriptionEn,
        string $descriptionAr,
        User $admin,
    ): LoyaltyTransaction {
        return DB::transaction(function () use ($user, $points, $descriptionEn, $descriptionAr, $admin): LoyaltyTransaction {
            $account = LoyaltyAccount::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                $account = LoyaltyAccount::create([
                    'user_id' => $user->id,
                    'points_balance' => 0,
                    'lifetime_points_earned' => 0,
                ]);
            }

            if ($points >= 0) {
                $account->increment('points_balance', $points);
                $account->increment('lifetime_points_earned', $points);
            } else {
                $account->decrement('points_balance', abs($points));
            }

            return LoyaltyTransaction::create([
                'loyalty_account_id' => $account->id,
                'type' => 'adjust',
                'points' => $points,
                'reference_type' => 'admin',
                'reference_id' => $admin->id,
                'description_en' => $descriptionEn,
                'description_ar' => $descriptionAr,
            ]);
        });
    }

    private function config(string $key): string
    {
        $row = LoyaltyConfig::find($key);

        return $row instanceof LoyaltyConfig ? $row->value : '0';
    }
}
