<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources;

use App\Modules\Catalog\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Modules\Catalog\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Modules\Catalog\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Modules\Catalog\Filament\Resources\ProductResource\RelationManagers\ReviewsRelationManager;
use App\Modules\Catalog\Filament\Resources\ProductResource\RelationManagers\TagsRelationManager;
use App\Modules\Catalog\Filament\Resources\ProductResource\RelationManagers\VariantsRelationManager;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Products';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name.ar')
                    ->label('Name (Arabic)')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name.en')
                    ->label('Name (English)')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description.ar')
                    ->label('Description (Arabic)')
                    ->columnSpanFull(),
                Textarea::make('description.en')
                    ->label('Description (English)')
                    ->columnSpanFull(),
                TextInput::make('base_price_fils')
                    ->label('Base Price (fils)')
                    ->required()
                    ->numeric()
                    ->minValue(0),
                Select::make('category_id')
                    ->label('Category')
                    ->options(
                        Category::query()
                            ->get()
                            ->mapWithKeys(
                                fn (Category $category) => [
                                    $category->id => self::formatCategoryName($category),
                                ]
                            )
                            ->toArray()
                    )
                    ->searchable()
                    ->required(),
                Toggle::make('is_available')
                    ->label('Active')
                    ->default(true),
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
                TextColumn::make('base_price_fils')
                    ->label('Price (BHD)')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3).' BHD'),
                ToggleColumn::make('is_available')->label('Active'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getRelationManagers(): array
    {
        return [
            VariantsRelationManager::class,
            TagsRelationManager::class,
            ReviewsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    /**
     * Format category name with both Arabic and English.
     */
    private static function formatCategoryName(Category $category): string
    {
        if (is_array($category->name)) {
            return ($category->name['ar'] ?? '').' / '.($category->name['en'] ?? '');
        }

        return (string) $category->name;
    }
}
