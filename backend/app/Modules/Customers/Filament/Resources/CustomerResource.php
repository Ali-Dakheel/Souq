<?php

declare(strict_types=1);

namespace App\Modules\Customers\Filament\Resources;

use App\Models\User;
use App\Modules\Customers\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Modules\Customers\Filament\Resources\CustomerResource\Pages\ViewCustomer;
use App\Modules\Customers\Filament\Resources\CustomerResource\RelationManagers\OrdersRelationManager;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Customers';

    protected static UnitEnum|string|null $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 7;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('profile.phone')
                    ->label('Phone')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }

    public static function getRelationManagers(): array
    {
        return [
            OrdersRelationManager::class,
        ];
    }
}
