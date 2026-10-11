<?php

namespace App\Filament\App\Resources\Courses\Pages;

use App\Filament\App\Resources\Courses\CourseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourses extends ListRecords
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New course')];
    }
}
