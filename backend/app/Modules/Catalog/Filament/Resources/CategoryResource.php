<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources;

use App\Modules\Catalog\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Modules\Catalog\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Modules\Catalog\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Modules\Catalog\Filament\Resources\CategoryResource\RelationManagers\ChildCategoriesRelationManager;
use App\Modules\Catalog\Models\Category;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Categories';

    protected static UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name.ar')->label('Name (Arabic)')->required(),
            TextInput::make('name.en')->label('Name (English)')->required()
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, callable $set): void {
                    $set('slug', Str::slug($state));
                }),
            TextInput::make('slug')->required()->unique(ignoreRecord: true),
            Select::make('parent_id')
                ->label('Parent Category')
                ->options(Category::whereNull('parent_id')->pluck('name', 'id')->mapWithKeys(
                    fn ($name, $id) => [$id => is_array($name) ? ($name['en'] ?? $name['ar'] ?? '') : $name]
                ))
                ->nullable()
                ->searchable(),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state['ar'] ?? '').' / '.($state['en'] ?? '') : (string) $state)
                    ->searchable(),
                TextColumn::make('slug'),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state['en'] ?? $state['ar'] ?? '') : (string) ($state ?? '—')),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([EditAction::make()]);
    }

    public static function getRelationManagers(): array
    {
        return [ChildCategoriesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
