<?php

namespace App\Filament\App\Resources\SocialPosts\Pages;

use App\Filament\App\Resources\SocialPosts\SocialPostResource;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ManageSocialPosts extends ManageRecords
{
    protected static string $resource = SocialPostResource::class;

    public function getSubheading(): ?string
    {
        return Filament::getTenant()?->social_webhook_url
            ? 'Scheduled posts are sent to your publishing webhook when they are due.'
            : 'Add a publishing webhook in Settings → Social to post to your networks automatically.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New post')->mutateDataUsing(fn (array $data) => $data + ['user_id' => auth()->id()])];
    }

    public function getTabs(): array
    {
        return [
            'upcoming' => Tab::make('Upcoming')->modifyQueryUsing(fn ($query) => $query->whereIn('status', ['draft', 'scheduled'])),
            'published' => Tab::make('Published')->modifyQueryUsing(fn ($query) => $query->where('status', 'published')),
            'failed' => Tab::make('Failed')->modifyQueryUsing(fn ($query) => $query->where('status', 'failed')),
            'all' => Tab::make('All'),
        ];
    }
}
