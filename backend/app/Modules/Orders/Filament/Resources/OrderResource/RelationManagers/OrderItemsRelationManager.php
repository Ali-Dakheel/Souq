<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Items';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product_name')
                    ->label('Product')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            return $state['en'] ?? $state['ar'] ?? 'N/A';
                        }

                        return $state ?? 'N/A';
                    }),
                TextColumn::make('sku')
                    ->label('SKU'),
                TextColumn::make('quantity'),
                TextColumn::make('price_fils_per_unit')
                    ->label('Unit Price (BHD)')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3).' BHD'),
                TextColumn::make('total_fils')
                    ->label('Line Total (BHD)')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3).' BHD'),
            ])
            ->paginated(false);
    }
}
