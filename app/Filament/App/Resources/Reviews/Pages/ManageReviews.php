<?php

namespace App\Filament\App\Resources\Reviews\Pages;

use App\Filament\App\Resources\Reviews\ReviewResource;
use App\Filament\App\Widgets\ReputationOverview;
use App\Models\Contact;
use App\Services\ReviewRequests;
use App\Support\Access;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageReviews extends ManageRecords
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderWidgets(): array
    {
        return [ReputationOverview::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('request')
                ->label('Request a review')
                ->icon('heroicon-o-paper-airplane')
                ->schema([
                    Select::make('contact_id')->label('Contact')
                        ->options(fn () => Contact::where('sub_account_id', Access::tenantId())->orderBy('first_name')->limit(500)->get()->mapWithKeys(fn ($c) => [$c->id => $c->full_name]))
                        ->searchable()->required(),
                    Select::make('channel')->options(['sms' => 'SMS', 'email' => 'Email'])->placeholder('Best available'),
                ])
                ->action(function (array $data): void {
                    $contact = Contact::where('sub_account_id', Access::tenantId())->findOrFail($data['contact_id']);
                    $request = ReviewRequests::send($contact, $data['channel'] ?? null);
                    Notification::make()->title($request ? 'Review request sent' : 'This contact can\'t be reached on that channel')->{$request ? 'success' : 'warning'}()->send();
                }),
            CreateAction::make()->label('Add review')->color('gray'),
        ];
    }
}
