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
 * @property string $address_type
 * @property string $recipient_name
 * @property string $phone
 * @property string $governorate
 * @property string|null $district
 * @property string $street_address
 * @property string|null $building_number
 * @property string|null $apartment_number
 * @property string|null $postal_code
 * @property string|null $delivery_instructions
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CustomerAddress extends Model
{
    protected $fillable = [
        'user_id',
        'address_type',
        'recipient_name',
        'phone',
        'governorate',
        'district',
        'street_address',
        'building_number',
        'apartment_number',
        'postal_code',
        'delivery_instructions',
        'is_default',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
