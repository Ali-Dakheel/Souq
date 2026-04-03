<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Filament\Resources;

use App\Modules\Shipping\Filament\Resources\ShippingZoneResource\Pages\CreateShippingZone;
use App\Modules\Shipping\Filament\Resources\ShippingZoneResource\Pages\EditShippingZone;
use App\Modules\Shipping\Filament\Resources\ShippingZoneResource\Pages\ListShippingZones;
use App\Modules\Shipping\Filament\Resources\ShippingZoneResource\RelationManagers\ShippingMethodsRelationManager;
use App\Modules\Shipping\Models\ShippingZone;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class ShippingZoneResource extends Resource
{
    protected static ?string $model = ShippingZone::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Shipping Zones';

    protected static UnitEnum|string|null $navigationGroup = 'Shipping';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name_en')
                    ->label('Name (English)')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name_ar')
                    ->label('Name (Arabic)')
                    ->required()
                    ->maxLength(255),
                TextInput::make('countries')
                    ->label('Countries')
                    ->helperText('Comma-separated ISO-2 codes, e.g. BH,SA,AE')
                    ->required()
                    ->formatStateUsing(fn ($state) => implode(', ', $state ?? []))
                    ->dehydrateStateUsing(fn ($state) => array_filter(array_map('trim', explode(',', $state ?? '')))),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_en')
                    ->label('Name (EN)')
                    ->searchable(),
                TextColumn::make('countries')
                    ->label('Countries')
                    ->formatStateUsing(fn ($state) => implode(', ', $state ?? []))
                    ->searchable(),
                ToggleColumn::make('is_active')
                    ->label('Active'),
                TextColumn::make('sort_order')
                    ->label('Sort Order')
                    ->sortable(),
            ])
            ->defaultSort('sort_order', 'asc')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelationManagers(): array
    {
        return [
            ShippingMethodsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingZones::route('/'),
            'create' => CreateShippingZone::route('/create'),
            'edit' => EditShippingZone::route('/{record}/edit'),
        ];
    }
}
