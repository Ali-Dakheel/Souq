<?php

declare(strict_types=1);

namespace App\Modules\Cart\Filament\Resources\CouponResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsageHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'usages';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('used_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('used_at', 'desc');
    }
}
