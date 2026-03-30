<?php

declare(strict_types=1);

namespace App\Modules\Cart\Console;

use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Services\CartService;
use Illuminate\Console\Command;

class PruneExpiredCartsCommand extends Command
{
    protected $signature = 'cart:prune-expired {--dry-run : List expired carts without deleting}';

    protected $description = 'Delete expired guest carts and record cart abandonment entries.';

    public function __construct(
        private readonly CartService $cartService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $expired = Cart::whereNotNull('session_id')
            ->whereNull('user_id')
            ->where('expires_at', '<=', now())
            ->whereHas('items') // only carts with items are worth recording as abandoned
            ->with('items')
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired guest carts found.');

            return Command::SUCCESS;
        }

        $this->info("Found {$expired->count()} expired guest cart(s).");

        if ($isDryRun) {
            $this->table(
                ['Cart ID', 'Session ID', 'Items', 'Expired At'],
                $expired->map(fn ($c) => [
                    $c->id,
                    $c->session_id,
                    $c->items->sum('quantity'),
                    $c->expires_at?->toDateTimeString(),
                ])->toArray(),
            );

            return Command::SUCCESS;
        }

        $pruned = 0;
        foreach ($expired as $cart) {
            // Idempotent: markAbandoned uses firstOrCreate internally
            $this->cartService->markAbandoned($cart);
            $cart->items()->delete();
            $cart->delete();
            $pruned++;
        }

        $this->info("Pruned {$pruned} expired guest cart(s).");

        return Command::SUCCESS;
    }
}
