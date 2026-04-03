<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Mail\CodCollectedMail;
use App\Modules\Payments\Events\CODCollected;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendCodCollectedEmail implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(CODCollected $event): void
    {
        Mail::queue(new CodCollectedMail($event->order));
    }
}
