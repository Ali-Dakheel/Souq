<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources;

use App\Modules\Payments\Filament\Resources\RefundResource\Pages\ListRefunds;
use App\Modules\Payments\Models\Refund;
use App\Modules\Payments\Services\RefundService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationLabel = 'Refunds';

    protected static UnitEnum|string|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 6;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('requestedBy.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('refund_amount_fils')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => number_format($state / 1000, 3).' BHD')
                    ->sortable(),
                TextColumn::make('refund_reason')
                    ->label('Reason')
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'processing' => 'info',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->visible(fn (Refund $record) => $record->isPending())
                    ->action(function (Refund $record) {
                        try {
                            app(RefundService::class)->approveRefund($record, auth()->user());

                            Notification::make()
                                ->success()
                                ->title('Refund Approved')
                                ->body('Refund has been approved and processed.')
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->send();

                            throw $e;
                        }
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-m-x-mark')
                    ->color('danger')
                    ->visible(fn (Refund $record) => $record->isPending())
                    ->form([
                        Textarea::make('admin_notes')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Refund $record, array $data) {
                        try {
                            app(RefundService::class)->rejectRefund($record, auth()->user(), $data['admin_notes']);

                            Notification::make()
                                ->success()
                                ->title('Refund Rejected')
                                ->body('Refund request has been rejected.')
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->send();

                            throw $e;
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRefunds::route('/'),
        ];
    }
}
