<?php

declare(strict_types=1);

namespace App\Modules\Cart\Filament\Resources;

use App\Modules\Cart\Filament\Resources\CouponResource\Pages\CreateCoupon;
use App\Modules\Cart\Filament\Resources\CouponResource\Pages\EditCoupon;
use App\Modules\Cart\Filament\Resources\CouponResource\Pages\ListCoupons;
use App\Modules\Cart\Filament\Resources\CouponResource\RelationManagers\UsageHistoryRelationManager;
use App\Modules\Cart\Models\Coupon;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Coupons';

    protected static UnitEnum|string|null $navigationGroup = 'Cart';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('code')
                ->label('Code')
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true),
            TextInput::make('name.ar')
                ->label('Name (Arabic)')
                ->maxLength(255),
            TextInput::make('name.en')
                ->label('Name (English)')
                ->maxLength(255),
            Select::make('discount_type')
                ->label('Discount Type')
                ->options([
                    'percentage' => 'Percentage (%)',
                    'fixed_fils' => 'Fixed Amount (fils)',
                ])
                ->required(),
            TextInput::make('discount_value')
                ->label('Discount Value')
                ->numeric()
                ->required(),
            TextInput::make('minimum_order_amount_fils')
                ->label('Minimum Order Amount (fils)')
                ->numeric()
                ->default(0),
            TextInput::make('maximum_discount_fils')
                ->label('Maximum Discount (fils)')
                ->numeric()
                ->nullable(),
            TextInput::make('max_uses_global')
                ->label('Global Usage Limit')
                ->numeric()
                ->nullable(),
            TextInput::make('max_uses_per_user')
                ->label('Per User Limit')
                ->numeric()
                ->default(1),
            Select::make('applicable_to')
                ->label('Applicable To')
                ->options([
                    'all_products' => 'All Products',
                    'specific_categories' => 'Specific Categories',
                    'specific_products' => 'Specific Products',
                ])
                ->required()
                ->default('all_products'),
            DateTimePicker::make('starts_at')
                ->label('Starts At')
                ->nullable(),
            DateTimePicker::make('expires_at')
                ->label('Expires At')
                ->nullable(),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('discount_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('discount_value')
                    ->label('Discount')
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->discount_type === 'percentage') {
                            return $state.'%';
                        }

                        return number_format($state / 1000, 3).' BHD';
                    })
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Active'),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('max_uses_global')
                    ->label('Global Limit')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/create'),
            'edit' => EditCoupon::route('/{record}/edit'),
        ];
    }

    /** @return array<int, class-string> */
    public static function getRelationManagers(): array
    {
        return [
            UsageHistoryRelationManager::class,
        ];
    }
}
