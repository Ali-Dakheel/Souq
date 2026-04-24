<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $phone
 * @property string|null $nationality
 * @property Carbon|null $date_of_birth
 * @property string|null $gender
 * @property string|null $preferred_locale
 * @property bool $marketing_consent
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CustomerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'nationality',
        'date_of_birth',
        'gender',
        'preferred_locale',
        'marketing_consent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'marketing_consent' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
