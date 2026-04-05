<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Filament\Resources;

use App\Modules\Promotions\Filament\Resources\PromotionRuleResource\Pages\CreatePromotionRule;
use App\Modules\Promotions\Filament\Resources\PromotionRuleResource\Pages\EditPromotionRule;
use App\Modules\Promotions\Filament\Resources\PromotionRuleResource\Pages\ListPromotionRules;
use App\Modules\Promotions\Models\PromotionRule;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PromotionRuleResource extends Resource
{
    protected static ?string $model = PromotionRule::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Promotion Rules';

    protected static UnitEnum|string|null $navigationGroup = 'Promotions';

    protected static ?int $navigationSort = 1;

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
            Textarea::make('description')
                ->label('Description')
                ->nullable()
                ->columnSpanFull(),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
            TextInput::make('priority')
                ->label('Priority')
                ->numeric()
                ->default(100),
            Toggle::make('is_exclusive')
                ->label('Exclusive')
                ->default(false)
                ->helperText('Only this promotion applies if multiple are eligible'),
            DateTimePicker::make('starts_at')
                ->label('Starts At')
                ->nullable(),
            DateTimePicker::make('expires_at')
                ->label('Expires At')
                ->nullable(),
            TextInput::make('max_uses_global')
                ->label('Global Usage Limit')
                ->numeric()
                ->nullable(),
            TextInput::make('max_uses_per_user')
                ->label('Per User Limit')
                ->numeric()
                ->nullable(),

            // Conditions repeater
            Repeater::make('conditions')
                ->label('Conditions')
                ->relationship('conditions')
                ->schema([
                    Select::make('type')
                        ->label('Type')
                        ->options([
                            'cart_total' => 'Cart Total',
                            'item_qty' => 'Item Quantity',
                            'customer_group' => 'Customer Group',
                            'product_in_cart' => 'Product in Cart',
                            'category_in_cart' => 'Category in Cart',
                        ])
                        ->required(),
                    Select::make('operator')
                        ->label('Operator')
                        ->options([
                            'gte' => 'Greater than or equal (≥)',
                            'lte' => 'Less than or equal (≤)',
                            'eq' => 'Equal (=)',
                            'in' => 'In list',
                            'not_in' => 'Not in list',
                        ])
                        ->required(),
                    TextInput::make('value')
                        ->label('Value (JSON format)')
                        ->required()
                        ->helperText('Enter a value or JSON array (e.g., "5000" or "[1,2,3]")')
                        ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : $state)
                        ->dehydrateStateUsing(fn ($state) => json_decode($state, true) ?? $state),
                ])
                ->minItems(0)
                ->columnSpanFull(),

            // Actions repeater
            Repeater::make('actions')
                ->label('Actions')
                ->relationship('actions')
                ->schema([
                    Select::make('type')
                        ->label('Type')
                        ->options([
                            'percent_off_cart' => 'Percent Off Cart',
                            'fixed_off_cart' => 'Fixed Amount Off Cart',
                            'free_shipping' => 'Free Shipping',
                            'percent_off_items' => 'Percent Off Items',
                            'bogo' => 'Buy One Get One',
                        ])
                        ->required(),
                    TextInput::make('value')
                        ->label('Value (JSON format)')
                        ->required()
                        ->helperText('Enter JSON (e.g., {"percent": 10} or {"amount_fils": 500})')
                        ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : $state)
                        ->dehydrateStateUsing(fn ($state) => json_decode($state, true) ?? $state),
                ])
                ->minItems(1)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_en')
                    ->label('Name (EN)')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_ar')
                    ->label('Name (AR)')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('is_active')
                    ->label('Active')
                    ->badge()
                    ->color(fn (bool $state) => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state) => $state ? 'Yes' : 'No')
                    ->sortable(),
                TextColumn::make('priority')
                    ->label('Priority')
                    ->sortable(),
                TextColumn::make('is_exclusive')
                    ->label('Exclusive')
                    ->badge()
                    ->color(fn (bool $state) => $state ? 'warning' : 'gray')
                    ->formatStateUsing(fn (bool $state) => $state ? 'Exclusive' : '—'),
                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('conditions')
                    ->label('Conditions')
                    ->formatStateUsing(fn (PromotionRule $record) => $record->conditions()->count()),
                TextColumn::make('actions')
                    ->label('Actions')
                    ->formatStateUsing(fn (PromotionRule $record) => $record->actions()->count()),
            ])
            ->defaultSort('priority', 'asc')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromotionRules::route('/'),
            'create' => CreatePromotionRule::route('/create'),
            'edit' => EditPromotionRule::route('/{record}/edit'),
        ];
    }
}
