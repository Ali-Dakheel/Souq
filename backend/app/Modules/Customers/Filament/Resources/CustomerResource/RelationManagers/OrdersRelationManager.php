<?php

declare(strict_types=1);

namespace App\Modules\Customers\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'initiated' => 'info',
                        'paid' => 'success',
                        'failed' => 'danger',
                        'cancelled' => 'gray',
                        'fulfilled' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('total_fils')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => number_format($state / 1000, 3).' BHD')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
