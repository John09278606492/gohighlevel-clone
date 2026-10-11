<?php

namespace App\Filament\Agency\Pages;

use App\Models\AgencySetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** White-label: the agency's own name, logo and color across both panels. */
class Branding extends Page
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Branding';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.agency.pages.branding';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'brand_name' => AgencySetting::get('brand_name'),
            'brand_color' => AgencySetting::get('brand_color'),
            'brand_logo' => AgencySetting::get('brand_logo'),
            'brand_favicon' => AgencySetting::get('brand_favicon'),
            'support_email' => AgencySetting::get('support_email'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('White label')->description('Shown to your team and your clients in the app, instead of the default name and colors.')->columns(2)->schema([
                TextInput::make('brand_name')->placeholder(config('app.name'))->maxLength(60),
                ColorPicker::make('brand_color')->label('Primary color'),
                FileUpload::make('brand_logo')->label('Logo')->image()->disk('public')->directory('branding'),
                FileUpload::make('brand_favicon')->label('Favicon')->image()->disk('public')->directory('branding'),
                TextInput::make('support_email')->email(),
            ]),
        ]);
    }

    public function save(): void
    {
        AgencySetting::put($this->form->getState());
        Notification::make()->success()->title('Branding saved')->send();
        $this->redirect(static::getUrl());
    }
}
