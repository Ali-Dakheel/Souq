<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistory';

    protected static ?string $title = 'Status History';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('old_status')->label('From'),
                TextColumn::make('new_status')->label('To')->badge(),
                TextColumn::make('reason'),
                TextColumn::make('created_at')->dateTime()->label('At'),
            ])
            ->defaultSort('created_at', 'asc')
            ->paginated(false);
    }
}
