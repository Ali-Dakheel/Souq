<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Modules\Settings\Services\StoreSettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StoreSettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Store Settings';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament-panels::pages.page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $service = app(StoreSettingsService::class);

        $keys = [
            'cr_number',
            'vat_number',
            'company_name_en',
            'company_name_ar',
            'company_address_en',
            'company_address_ar',
            'logo_path',
            'favicon_path',
            'support_email',
            'support_phone',
        ];

        $formData = [];
        foreach ($keys as $key) {
            $formData[$key] = $service->get($key, '');
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Legal Information')
                    ->schema([
                        TextInput::make('cr_number')
                            ->label('CR Number')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('vat_number')
                            ->label('VAT Number')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('company_name_en')
                            ->label('Company Name (English)')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('company_name_ar')
                            ->label('Company Name (Arabic)')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('company_address_en')
                            ->label('Company Address (English)')
                            ->columnSpanFull(),
                        Textarea::make('company_address_ar')
                            ->label('Company Address (Arabic)')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Branding')
                    ->schema([
                        TextInput::make('logo_path')
                            ->label('Logo Path')
                            ->maxLength(500),
                        TextInput::make('favicon_path')
                            ->label('Favicon Path')
                            ->maxLength(500),
                    ])
                    ->columns(2),

                Section::make('Commerce')
                    ->schema([
                        TextInput::make('support_email')
                            ->label('Support Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('support_phone')
                            ->label('Support Phone')
                            ->maxLength(50),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make($this->getFormActions())
                            ->key('form-actions'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        app(StoreSettingsService::class)->bulkUpdate($data);

        Notification::make()
            ->title('Settings saved successfully.')
            ->success()
            ->send();
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }
}
