<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources;

use App\Modules\Orders\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Modules\Orders\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Modules\Orders\Filament\Resources\OrderResource\RelationManagers\InvoiceRelationManager;
use App\Modules\Orders\Filament\Resources\OrderResource\RelationManagers\OrderItemsRelationManager;
use App\Modules\Orders\Filament\Resources\OrderResource\RelationManagers\OrderShippingRelationManager;
use App\Modules\Orders\Filament\Resources\OrderResource\RelationManagers\ShipmentsRelationManager;
use App\Modules\Orders\Filament\Resources\OrderResource\RelationManagers\StatusHistoryRelationManager;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Orders';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('order_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending', 'pending_collection' => 'warning',
                        'initiated', 'processing' => 'info',
                        'paid', 'fulfilled', 'collected' => 'success',
                        'cancelled', 'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('total_fils')
                    ->label('Total (BHD)')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3).' BHD'),
                TextColumn::make('locale')
                    ->label('Lang')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make(),
                Action::make('mark_collected')
                    ->label('Mark Collected')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->isCod() && $record->order_status === 'pending_collection')
                    ->form([
                        Textarea::make('note')
                            ->label('Collection Note (optional)')
                            ->nullable(),
                    ])
                    ->action(function (Order $record, array $data): void {
                        try {
                            app(OrderService::class)->markCodCollected($record, $data['note'] ?? null);
                            Notification::make()
                                ->title('COD payment marked as collected.')
                                ->success()
                                ->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('fulfill')
                    ->label('Mark Fulfilled')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Order $record): bool => $record->order_status === 'paid')
                    ->form([
                        TextInput::make('tracking_number')
                            ->label('Tracking Number (optional)'),
                    ])
                    ->action(function (Order $record, array $data): void {
                        app(OrderService::class)->fulfillOrder(
                            $record,
                            $data['tracking_number'] ?: null,
                        );
                        Notification::make()
                            ->title('Order marked as fulfilled.')
                            ->success()
                            ->send();
                    }),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => in_array($record->order_status, ['pending', 'initiated', 'processing', 'paid'], true))
                    ->action(function (Order $record): void {
                        try {
                            app(OrderService::class)->cancelOrderAsAdmin($record);
                            Notification::make()
                                ->title('Order cancelled.')
                                ->success()
                                ->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('override_status')
                    ->label('Override Status')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->form([
                        Select::make('new_status')
                            ->options([
                                'pending' => 'Pending',
                                'initiated' => 'Initiated',
                                'processing' => 'Processing',
                                'paid' => 'Paid',
                                'fulfilled' => 'Fulfilled',
                                'cancelled' => 'Cancelled',
                                'failed' => 'Failed',
                                'refunded' => 'Refunded',
                                'shipped' => 'Shipped',
                                'delivered' => 'Delivered',
                                'pending_collection' => 'Pending Collection (COD)',
                                'collected' => 'Collected (COD)',
                            ])
                            ->required(),
                        Textarea::make('note')
                            ->label('Reason')
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data): void {
                        app(OrderService::class)->overrideOrderStatus(
                            $record,
                            $data['new_status'],
                            $data['note'],
                        );
                        Notification::make()
                            ->title('Order status updated.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getRelationManagers(): array
    {
        return [
            OrderItemsRelationManager::class,
            StatusHistoryRelationManager::class,
            InvoiceRelationManager::class,
            ShipmentsRelationManager::class,
            OrderShippingRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
