<?php

namespace App\Filament\App\Resources\Contacts\Pages;

use App\Filament\App\Resources\Contacts\ContactResource;
use App\Filament\App\Resources\SmartLists\SmartListResource;
use App\Models\SmartList;
use App\Services\ContactCsv;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Facades\Storage;

class ListContacts extends ListRecords
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ActionGroup::make([
                Action::make('import')
                    ->label('Import CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->modalDescription('Columns are matched by header: first_name, last_name (or name), email, phone, company, source, type, tags, plus any custom field key. Existing contacts are matched by email or phone and updated.')
                    ->schema([
                        FileUpload::make('file')
                            ->label('CSV file')
                            ->disk('local')
                            ->directory('imports')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/csv'])
                            ->maxSize(10240)
                            ->required(),
                        TagsInput::make('tags')->label('Tag every imported contact with'),
                    ])
                    ->action(function (array $data): void {
                        $path = Storage::disk('local')->path($data['file']);
                        $result = ContactCsv::import(Filament::getTenant(), $path, $data['tags'] ?? []);
                        Storage::disk('local')->delete($data['file']);

                        Notification::make()->success()->title('Import finished')
                            ->body("{$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped (no email or phone).")
                            ->send();
                    }),
                Action::make('exportAll')
                    ->label('Export all to CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => ContactCsv::export(
                        $this->getFilteredTableQuery()->with('tags')->get(),
                        Filament::getTenant(),
                    )),
                Action::make('saveList')
                    ->label('New smart list')
                    ->icon('heroicon-o-funnel')
                    ->url(SmartListResource::getUrl('index')),
            ])->label('More')->icon('heroicon-m-ellipsis-vertical')->button()->color('gray'),
        ];
    }

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('All')];

        $lists = SmartList::where('sub_account_id', Filament::getTenant()?->getKey())
            ->where(fn ($q) => $q->where('is_shared', true)->orWhere('user_id', auth()->id()))
            ->orderBy('sort')
            ->limit(12)
            ->get();

        foreach ($lists as $list) {
            $tabs['list-'.$list->id] = Tab::make($list->name)
                ->icon('heroicon-m-funnel')
                ->modifyQueryUsing(fn ($query) => SmartList::apply($query, $list->criteria ?? []));
        }

        return $tabs;
    }
}
