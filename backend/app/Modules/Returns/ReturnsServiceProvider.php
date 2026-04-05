<?php

declare(strict_types=1);

namespace App\Modules\Returns;

use Illuminate\Support\ServiceProvider;

class ReturnsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
