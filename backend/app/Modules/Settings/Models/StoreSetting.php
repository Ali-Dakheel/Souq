<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    protected $table = 'store_settings';

    protected $fillable = [
        'key',
        'value',
        'group',
    ];
}
