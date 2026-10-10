<?php

namespace App\Filament\App\Resources\Conversations\Tables;

use App\Models\Conversation;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ConversationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['contact', 'latestMessage']))
            ->defaultSort('last_message_at', 'desc')
            ->recordUrl(fn (Conversation $record) => \App\Filament\App\Resources\Conversations\ConversationResource::getUrl('view', ['record' => $record]))
            ->columns([
                IconColumn::make('is_starred')
                    ->label('')
                    ->icon(fn (bool $state) => $state ? 'heroicon-s-star' : 'heroicon-o-star')
                    ->color(fn (bool $state) => $state ? 'warning' : 'gray')
                    ->action(fn (Conversation $record) => $record->update(['is_starred' => ! $record->is_starred]))
                    ->width('1%'),
                TextColumn::make('contact.full_name')
                    ->label('Contact')
                    ->weight(fn (Conversation $record) => $record->unread_count ? 'bold' : 'normal')
                    ->searchable(['first_name', 'last_name', 'email', 'phone'])
                    ->description(fn (Conversation $record) => Str::limit((string) $record->latestMessage?->body, 80)),
                TextColumn::make('latestMessage.channel')
                    ->label('Channel')
                    ->badge()
                    ->color(fn (?string $state) => $state === 'sms' ? 'success' : 'info'),
                TextColumn::make('unread_count')
                    ->label('Unread')
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn (int $state) => $state ?: '')
                    ->alignCenter(),
                TextColumn::make('last_message_at')
                    ->label('Last message')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('unread')->query(fn (Builder $q) => $q->where('unread_count', '>', 0)),
                Filter::make('starred')->query(fn (Builder $q) => $q->where('is_starred', true)),
            ])
            ->recordActions([
                ViewAction::make()->label('Open'),
                Action::make('markRead')
                    ->label('Mark read')
                    ->icon('heroicon-o-check')
                    ->visible(fn (Conversation $record) => $record->unread_count > 0)
                    ->action(fn (Conversation $record) => $record->update(['unread_count' => 0])),
            ]);
    }
}
