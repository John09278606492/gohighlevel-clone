<?php

namespace App\Filament\App\Resources\Conversations;

use App\Filament\App\Resources\Conversations\Pages\ListConversations;
use App\Filament\App\Resources\Conversations\Pages\ViewConversation;
use App\Filament\App\Resources\Conversations\Tables\ConversationsTable;
use App\Models\Conversation;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** GoHighLevel's unified inbox: one conversation per contact, email and SMS in one thread. */
class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Conversations';

    public static function getNavigationBadge(): ?string
    {
        $unread = Conversation::where('sub_account_id', Filament::getTenant()?->getKey())->where('unread_count', '>', 0)->count();

        return $unread ? (string) $unread : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return ConversationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConversations::route('/'),
            'view' => ViewConversation::route('/{record}'),
        ];
    }
}
