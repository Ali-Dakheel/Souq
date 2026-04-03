<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources;

use App\Modules\Orders\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Modules\Orders\Filament\Resources\InvoiceResource\Pages\ViewInvoice;
use App\Modules\Orders\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Invoices';

    protected static UnitEnum|string|null $navigationGroup = 'Orders';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_fils')
                    ->label('Total (BHD)')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3).' BHD')
                    ->sortable(),
                TextColumn::make('issued_at')
                    ->label('Issued At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('issued_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }
}
