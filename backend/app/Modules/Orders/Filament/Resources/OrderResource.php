<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources;

use App\Modules\Orders\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Modules\Orders\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Modules\Orders\Filament\Resources\OrderResource\RelationManagers\OrderItemsRelationManager;
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
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
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
                BadgeColumn::make('order_status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => ['initiated', 'processing'],
                        'success' => ['paid', 'fulfilled'],
                        'danger' => ['cancelled', 'failed'],
                        'gray' => 'refunded',
                    ]),
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
