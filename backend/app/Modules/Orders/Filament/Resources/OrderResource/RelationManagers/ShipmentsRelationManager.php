<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\OrderResource\RelationManagers;

use App\Modules\Orders\Models\Shipment;
use App\Modules\Orders\Services\ShipmentService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class ShipmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'shipments';

    protected static ?string $title = 'Shipments';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('shipment_number')
                    ->label('Shipment #')
                    ->searchable(),
                TextColumn::make('carrier')
                    ->label('Carrier')
                    ->placeholder('—'),
                TextColumn::make('tracking_number')
                    ->label('Tracking #')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'shipped' => 'info',
                        'delivered' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('shipped_at')
                    ->label('Shipped At')
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('delivered_at')
                    ->label('Delivered At')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->headerActions([
                Action::make('create_shipment')
                    ->label('Create Shipment')
                    ->icon('heroicon-o-truck')
                    ->form(function (): array {
                        $order = $this->getOwnerRecord();
                        $order->load(['items.shipmentItems']);

                        $itemOptions = $order->items
                            ->filter(fn ($item) => $item->quantity_to_ship > 0)
                            ->map(fn ($item) => [
                                'order_item_id' => $item->id,
                                'label' => "{$item->sku} (max: {$item->quantity_to_ship})",
                                'max_qty' => $item->quantity_to_ship,
                            ])
                            ->values()
                            ->toArray();

                        return [
                            TextInput::make('carrier')
                                ->label('Carrier')
                                ->maxLength(100),
                            TextInput::make('tracking_number')
                                ->label('Tracking Number')
                                ->maxLength(255),
                            Textarea::make('notes')
                                ->label('Notes'),
                            Repeater::make('items')
                                ->label('Items to Ship')
                                ->schema([
                                    Select::make('order_item_id')
                                        ->label('Order Item')
                                        ->options(
                                            collect($itemOptions)->pluck('label', 'order_item_id')->toArray()
                                        )
                                        ->required(),
                                    TextInput::make('quantity_shipped')
                                        ->label('Quantity')
                                        ->numeric()
                                        ->minValue(1)
                                        ->required(),
                                ])
                                ->minItems(1)
                                ->defaultItems(count($itemOptions)),
                        ];
                    })
                    ->action(function (array $data): void {
                        $order = $this->getOwnerRecord();

                        try {
                            app(ShipmentService::class)->createShipment(
                                order: $order,
                                items: $data['items'],
                                carrier: $data['carrier'] ?? null,
                                trackingNumber: $data['tracking_number'] ?? null,
                                notes: $data['notes'] ?? null,
                                createdBy: auth()->id(),
                            );

                            Notification::make()
                                ->title('Shipment created successfully.')
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title(implode(' ', array_merge(...array_values($e->errors()))))
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Action::make('mark_shipped')
                    ->label('Mark Shipped')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn (Shipment $record): bool => $record->status === 'pending')
                    ->form([
                        TextInput::make('tracking_number')
                            ->label('Tracking Number (optional)')
                            ->maxLength(255),
                    ])
                    ->action(function (Shipment $record, array $data): void {
                        app(ShipmentService::class)->markShipped(
                            $record,
                            $data['tracking_number'] ?: null,
                        );

                        Notification::make()
                            ->title('Shipment marked as shipped.')
                            ->success()
                            ->send();
                    }),

                Action::make('mark_delivered')
                    ->label('Mark Delivered')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Shipment $record): bool => $record->status === 'shipped')
                    ->requiresConfirmation()
                    ->action(function (Shipment $record): void {
                        app(ShipmentService::class)->markDelivered($record);

                        Notification::make()
                            ->title('Shipment marked as delivered.')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
