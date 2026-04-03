<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Filament\Resources\ShippingZoneResource\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ShippingMethodsRelationManager extends RelationManager
{
    protected static string $relationship = 'methods';

    protected static ?string $title = 'Shipping Methods';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('carrier')
                    ->label('Carrier')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name_en')
                    ->label('Name (English)')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name_ar')
                    ->label('Name (Arabic)')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Type')
                    ->options([
                        'flat_rate' => 'Flat Rate',
                        'free_threshold' => 'Free Above Threshold',
                        'carrier_api' => 'Carrier API',
                    ])
                    ->required(),
                TextInput::make('rate_fils')
                    ->label('Rate (fils)')
                    ->numeric()
                    ->nullable(),
                TextInput::make('free_threshold_fils')
                    ->label('Free Threshold (fils)')
                    ->numeric()
                    ->nullable(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('carrier')
                    ->label('Carrier')
                    ->searchable(),
                TextColumn::make('name_en')
                    ->label('Name (EN)'),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'flat_rate' => 'blue',
                        'free_threshold' => 'green',
                        'carrier_api' => 'purple',
                        default => 'gray',
                    }),
                TextColumn::make('rate_fils')
                    ->label('Rate (BHD)')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1000, 3).' BHD' : '—'),
                TextColumn::make('free_threshold_fils')
                    ->label('Threshold (BHD)')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1000, 3).' BHD' : '—'),
                ToggleColumn::make('is_active')
                    ->label('Active'),
                TextColumn::make('sort_order')
                    ->label('Sort Order'),
            ])
            ->paginated(false)
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
