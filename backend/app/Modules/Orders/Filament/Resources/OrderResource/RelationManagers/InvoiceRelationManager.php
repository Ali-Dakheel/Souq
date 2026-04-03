<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoiceRelationManager extends RelationManager
{
    protected static string $relationship = 'invoice';

    protected static ?string $title = 'Invoice';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable(),
                TextColumn::make('subtotal_fils')
                    ->label('Subtotal (BHD)')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3).' BHD'),
                TextColumn::make('vat_fils')
                    ->label('VAT (BHD)')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3).' BHD'),
                TextColumn::make('discount_fils')
                    ->label('Discount (BHD)')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3).' BHD'),
                TextColumn::make('total_fils')
                    ->label('Total (BHD)')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3).' BHD'),
                TextColumn::make('issued_at')
                    ->label('Issued At')
                    ->dateTime(),
            ])
            ->paginated(false);
    }
}
