<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Resources\Opportunities\OpportunityResource;
use App\Models\Opportunity;
use App\Models\Pipeline;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\BoardPage;
use Relaticle\Flowforge\Column;
use Relaticle\Flowforge\Components\CardFlex;
use UnitEnum;

/** The drag-and-drop opportunities board, one column per pipeline stage (Flowforge). */
class PipelineBoard extends BoardPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Pipelines';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'pipeline';

    #[Url]
    public ?int $pipeline = null;

    public function mount(): void
    {
        $this->pipeline ??= $this->pipelines()->value('id');
    }

    protected function pipelines(): Builder
    {
        return Pipeline::query()->where('sub_account_id', Filament::getTenant()?->getKey());
    }

    public function currentPipeline(): ?Pipeline
    {
        return $this->pipelines()->with('stages')->find($this->pipeline);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->currentPipeline()?->name ?? 'Pipelines';
    }

    public function getSubheading(): ?string
    {
        $open = Opportunity::where('pipeline_id', $this->pipeline)->where('status', 'open');

        return sprintf('%d open opportunities · $%s', (clone $open)->count(), number_format((float) (clone $open)->sum('value'), 2));
    }

    public function board(Board $board): Board
    {
        $stages = $this->currentPipeline()?->stages ?? collect();

        return $board
            ->query(Opportunity::query()->with('contact')->where('pipeline_id', $this->pipeline)->where('sub_account_id', Filament::getTenant()?->getKey()))
            ->recordTitleAttribute('name')
            ->columnIdentifier('pipeline_stage_id')
            ->positionIdentifier('position')
            ->columns($stages->map(fn ($stage) => Column::make((string) $stage->id)->label($stage->name)->color($stage->color))->all())
            ->searchable(['name'])
            ->filters([
                SelectFilter::make('status')->options(Opportunity::STATUSES)->default('open'),
                SelectFilter::make('owner_id')->label('Assigned to')->options(fn () => Filament::getTenant()?->users()->pluck('name', 'users.id') ?? []),
            ])
            ->cardSchema(fn (Schema $schema) => $schema->components([
                TextEntry::make('contact.full_name')->hiddenLabel()->icon('heroicon-m-user')->color('gray'),
                CardFlex::make([
                    TextEntry::make('value')->hiddenLabel()->money('USD')->badge()->color('success'),
                    TextEntry::make('status')->hiddenLabel()->badge()
                        ->formatStateUsing(fn (string $state) => Opportunity::STATUSES[$state] ?? $state)
                        ->color(fn (string $state) => match ($state) {
                            'won' => 'success', 'lost' => 'danger', 'abandoned' => 'gray', default => 'info',
                        }),
                ])->justify('between'),
            ]))
            ->columnActions([
                CreateAction::make()
                    ->label('Add opportunity')
                    ->model(Opportunity::class)
                    ->schema(fn (Schema $schema) => OpportunityResource::form($schema))
                    ->fillForm(fn (array $arguments) => [
                        'pipeline_id' => $this->pipeline,
                        'pipeline_stage_id' => $arguments['column'] ?? null,
                        'status' => 'open',
                        'value' => 0,
                    ])
                    ->mutateDataUsing(function (array $data, array $arguments): array {
                        $data['sub_account_id'] = Filament::getTenant()->getKey();
                        if (isset($arguments['column'])) {
                            $data['pipeline_stage_id'] = $arguments['column'];
                            $data['position'] = $this->getBoardPositionInColumn($arguments['column']);
                        }

                        return $data;
                    }),
            ])
            ->cardActions([
                EditAction::make()->model(Opportunity::class)->schema(fn (Schema $schema) => OpportunityResource::form($schema)),
                ActionGroup::make([
                    Action::make('won')->label('Mark won')->icon('heroicon-o-trophy')->color('success')
                        ->action(fn (Opportunity $record) => $record->update(['status' => 'won'])),
                    Action::make('lost')->label('Mark lost')->icon('heroicon-o-x-circle')->color('danger')
                        ->action(fn (Opportunity $record) => $record->update(['status' => 'lost'])),
                    DeleteAction::make()->model(Opportunity::class),
                ]),
            ])
            ->cardAction('edit');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('switchPipeline')
                ->label('Switch pipeline')
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->visible(fn () => $this->pipelines()->count() > 1)
                ->schema([
                    \Filament\Forms\Components\Select::make('pipeline')
                        ->options(fn () => $this->pipelines()->pluck('name', 'id'))
                        ->default($this->pipeline)
                        ->required(),
                ])
                ->action(fn (array $data) => $this->redirect(static::getUrl(['pipeline' => $data['pipeline']]))),
            Action::make('managePipelines')
                ->label('Edit stages')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn () => \App\Filament\App\Resources\Pipelines\PipelineResource::getUrl('index')),
        ];
    }
}
