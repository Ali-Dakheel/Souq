<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources;

use App\Modules\Catalog\Filament\Resources\AttributeResource\Pages\CreateAttribute;
use App\Modules\Catalog\Filament\Resources\AttributeResource\Pages\EditAttribute;
use App\Modules\Catalog\Filament\Resources\AttributeResource\Pages\ListAttributes;
use App\Modules\Catalog\Filament\Resources\AttributeResource\RelationManagers\AttributeValuesRelationManager;
use App\Modules\Catalog\Models\Attribute;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Attributes';

    protected static UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name.ar')
                ->label('Name (Arabic)')
                ->required()
                ->maxLength(255),
            TextInput::make('name.en')
                ->label('Name (English)')
                ->required()
                ->maxLength(255),
            Select::make('attribute_type')
                ->label('Type')
                ->options([
                    'select' => 'Select',
                    'text' => 'Text',
                    'color' => 'Color',
                ])
                ->required(),
            TextInput::make('sort_order')
                ->label('Sort Order')
                ->numeric()
                ->default(0),
            Toggle::make('is_filterable')
                ->label('Filterable')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->formatStateUsing(function ($state): string {
                        if (is_array($state)) {
                            return ($state['ar'] ?? '').' / '.($state['en'] ?? '');
                        }

                        return (string) $state;
                    })
                    ->searchable(),
                TextColumn::make('attribute_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getRelationManagers(): array
    {
        return [
            AttributeValuesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttributes::route('/'),
            'create' => CreateAttribute::route('/create'),
            'edit' => EditAttribute::route('/{record}/edit'),
        ];
    }
}
