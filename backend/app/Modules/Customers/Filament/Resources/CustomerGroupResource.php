<?php

declare(strict_types=1);

namespace App\Modules\Customers\Filament\Resources;

use App\Modules\Customers\Filament\Resources\CustomerGroupResource\Pages\CreateCustomerGroup;
use App\Modules\Customers\Filament\Resources\CustomerGroupResource\Pages\EditCustomerGroup;
use App\Modules\Customers\Filament\Resources\CustomerGroupResource\Pages\ListCustomerGroups;
use App\Modules\Customers\Models\CustomerGroup;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CustomerGroupResource extends Resource
{
    protected static ?string $model = CustomerGroup::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Customer Groups';

    protected static UnitEnum|string|null $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name_en')
                ->label('Name (English)')
                ->required()
                ->maxLength(255),
            TextInput::make('name_ar')
                ->label('Name (Arabic)')
                ->required()
                ->maxLength(255),
            TextInput::make('slug')
                ->label('Slug')
                ->nullable()
                ->maxLength(255)
                ->helperText('Auto-generated if left blank'),
            Textarea::make('description')
                ->label('Description')
                ->nullable(),
            Toggle::make('is_default')
                ->label('Default Group'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_en')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_ar')
                    ->label('Arabic Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('is_default')
                    ->label('Default')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
                TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('users');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomerGroups::route('/'),
            'create' => CreateCustomerGroup::route('/create'),
            'edit' => EditCustomerGroup::route('/{record}/edit'),
        ];
    }
}
