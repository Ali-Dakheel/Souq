<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources;

use App\Modules\Payments\Filament\Resources\TapTransactionResource\Pages\ListTapTransactions;
use App\Modules\Payments\Filament\Resources\TapTransactionResource\Pages\ViewTapTransaction;
use App\Modules\Payments\Models\TapTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class TapTransactionResource extends Resource
{
    protected static ?string $model = TapTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Transactions';

    protected static UnitEnum|string|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tap_charge_id')
                    ->label('Charge ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount_fils')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => number_format($state / 1000, 3).' BHD')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'captured' => 'success',
                        'failed' => 'danger',
                        'initiated' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Method')
                    ->sortable(),
                TextColumn::make('attempt_number')
                    ->label('Attempt')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'captured' => 'Captured',
                        'failed' => 'Failed',
                        'initiated' => 'Initiated',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTapTransactions::route('/'),
            'view' => ViewTapTransaction::route('/{record}'),
        ];
    }
}
