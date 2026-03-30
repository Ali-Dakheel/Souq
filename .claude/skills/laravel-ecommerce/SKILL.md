---
name: laravel-ecommerce
description: >
  Laravel 13 patterns for this ecommerce platform. Service layer, events,
  Filament admin, Pest testing, BHD currency, inventory locking.
  Auto-referenced when working in backend/.
---

# Laravel Ecommerce Patterns

## Module structure — always follow exactly

```php
// Controller — thin, delegates everything
class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $service
    ) {}

    public function store(StoreProductRequest $request): ProductResource
    {
        $product = $this->service->create($request->validated());
        return new ProductResource($product);
    }
}

// Service — all business logic
class ProductService
{
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::create($data);
            event(new ProductCreated($product));
            return $product;
        });
    }
}

// Resource — all JSON transformation
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->getTranslation('name', app()->getLocale()),
            'price_fils'   => $this->price_fils,
            'price_display'=> number_format($this->price_fils / 1000, 3) . ' BHD',
            'slug'         => $this->slug,
        ];
    }
}
```

## BHD currency — everywhere in Laravel

```php
// Migrations — always integer
$table->integer('price_fils');
$table->integer('total_fils')->default(0);

// Services — compute in fils (pure integers)
$totalFils = collect($items)
    ->sum(fn($item) => $item->price_fils * $item->quantity);

// Tap Payments API — convert to decimal string
$amountBhd = number_format($order->total_fils / 1000, 3, '.', '');
// 10500 fils → "10.500"
```

## Inventory locking — always this exact pattern

```php
public function decrementStock(int $productId, int $quantity): void
{
    DB::transaction(function () use ($productId, $quantity) {
        $inventory = Inventory::lockForUpdate()->findOrFail($productId);

        if ($inventory->available_stock < $quantity) {
            throw new InsufficientStockException($productId, $quantity);
        }

        $inventory->decrement('available_stock', $quantity);
        $inventory->increment('reserved_stock', $quantity);

        InventoryMovement::create([
            'product_id' => $productId,
            'type'       => 'reservation',
            'quantity'   => -$quantity,
        ]);
    });
}
```

## Idempotent job — always check state first

```php
class ProcessPaymentCapture implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $order = $this->order->fresh();

        // ALWAYS check state — this job may run twice
        if ($order->status !== OrderStatus::INITIATED) {
            Log::info('Already processed', ['order_id' => $order->id]);
            return;
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => OrderStatus::PAID]);
            event(new PaymentCaptured($order));
        });
    }
}
```

## Filament admin — bilingual product form

```php
class ProductResource extends Resource
{
    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make()->tabs([
                Tab::make('English')->schema([
                    TextInput::make('name.en')->required(),
                    RichEditor::make('description.en'),
                ]),
                Tab::make('Arabic')->schema([
                    TextInput::make('name.ar')
                        ->required()
                        ->extraAttributes(['dir' => 'rtl']),
                    RichEditor::make('description.ar')
                        ->extraAttributes(['dir' => 'rtl']),
                ]),
            ]),
            TextInput::make('price_fils')
                ->label('Price (fils) — 1 BHD = 1000 fils')
                ->numeric()
                ->required(),
        ]);
    }
}
```

## Pest test patterns

```php
it('creates a product with bilingual content', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->postJson('/api/v1/products', [
        'name'       => ['en' => 'Test Product', 'ar' => 'منتج تجريبي'],
        'price_fils' => 5000, // 5.000 BHD
        'sku'        => 'TEST-001',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.price_fils', 5000);
    $this->assertDatabaseHas('products', [
        'sku'        => 'TEST-001',
        'price_fils' => 5000,
    ]);
});

it('prevents overselling under concurrent checkouts', function () {
    $product = Product::factory()->create();
    Inventory::factory()->for($product)->create(['available_stock' => 1]);

    $service = app(InventoryService::class);
    $service->decrementStock($product->id, 1); // first succeeds

    expect(fn () => $service->decrementStock($product->id, 1))
        ->toThrow(InsufficientStockException::class);
});
```

## VAT calculation

```php
class TaxService
{
    public const VAT_RATE = 0.10;

    public function calculate(int $subtotalFils): array
    {
        $vatFils   = (int) round($subtotalFils * self::VAT_RATE);
        $totalFils = $subtotalFils + $vatFils;

        return [
            'subtotal_fils' => $subtotalFils,
            'vat_fils'      => $vatFils,
            'vat_rate'      => self::VAT_RATE,
            'total_fils'    => $totalFils,
        ];
    }
}
```
