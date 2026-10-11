<?php

namespace App\Filament\App\Resources\Courses;

use App\Filament\App\Resources\Courses\Pages\CreateCourse;
use App\Filament\App\Resources\Courses\Pages\EditCourse;
use App\Filament\App\Resources\Courses\Pages\ListCourses;
use App\Filament\App\Resources\Courses\RelationManagers\EnrollmentsRelationManager;
use App\Filament\App\Resources\Courses\RelationManagers\LessonsRelationManager;
use App\Models\Course;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Memberships: courses with lessons, unlocked per contact (manually, by workflow, or by buying a product). */
class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Sites';

    protected static ?string $navigationLabel = 'Memberships';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->required()->maxLength(200),
            Toggle::make('is_published')->label('Published')->helperText('Members can only open published courses.')->inline(false),
            Textarea::make('description')->rows(3)->columnSpanFull(),
            FileUpload::make('cover_image')->image()->disk('public')->directory('courses'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_image')->disk('public')->label('')->square(),
                TextColumn::make('title')->weight('medium')->searchable(),
                TextColumn::make('lessons_count')->counts('lessons')->label('Lessons'),
                TextColumn::make('enrollments_count')->counts('enrollments')->label('Members'),
                ToggleColumn::make('is_published')->label('Published'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getRelations(): array
    {
        return [LessonsRelationManager::class, EnrollmentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourses::route('/'),
            'create' => CreateCourse::route('/create'),
            'edit' => EditCourse::route('/{record}/edit'),
        ];
    }
}
