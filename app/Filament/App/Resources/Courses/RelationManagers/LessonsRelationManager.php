<?php

namespace App\Filament\App\Resources\Courses\RelationManagers;

use App\Models\Lesson;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LessonsRelationManager extends RelationManager
{
    protected static string $relationship = 'lessons';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->required()->maxLength(200),
            TextInput::make('module')->placeholder('Module 1: Getting started')->datalist(fn () => $this->getOwnerRecord()->lessons()->whereNotNull('module')->distinct()->pluck('module')->all()),
            TextInput::make('video_url')->url()->helperText('YouTube, Vimeo or Loom links play inside the lesson.')->columnSpanFull(),
            RichEditor::make('content')->columnSpanFull(),
            Toggle::make('is_preview')->label('Free preview'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('module')->placeholder('-'),
                TextColumn::make('title')->weight('medium'),
                IconColumn::make('video_url')->label('Video')->boolean(fn (Lesson $r) => filled($r->video_url)),
            ])
            ->headerActions([CreateAction::make()->mutateDataUsing(fn (array $data) => $data + ['sort' => (int) $this->getOwnerRecord()->lessons()->max('sort') + 1])])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
