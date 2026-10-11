<?php

namespace App\Filament\App\Resources\ApiKeys\Pages;

use App\Filament\App\Resources\ApiKeys\ApiKeyResource;
use App\Models\ApiKey;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ManageApiKeys extends ManageRecords
{
    protected static string $resource = ApiKeyResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString('REST API at <code>'.e(url('/api/v1')).'</code>. Send the key as <code>Authorization: Bearer &lt;key&gt;</code>. Endpoints are listed in docs/API.md.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('New API key')
                ->icon('heroicon-o-plus')
                ->schema([TextInput::make('name')->required()->placeholder('Zapier')->maxLength(100)])
                ->action(function (array $data): void {
                    [, $plain] = ApiKey::issue(Filament::getTenant(), $data['name']);

                    Notification::make()
                        ->success()
                        ->title('Copy your API key now')
                        ->body(new HtmlString('<code style="word-break:break-all">'.e($plain).'</code><br>It won\'t be shown again.'))
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
