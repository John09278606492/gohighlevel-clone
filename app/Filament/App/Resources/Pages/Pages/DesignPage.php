<?php

namespace App\Filament\App\Resources\Pages\Pages;

use App\Filament\App\Resources\Pages\PageResource;
use CarlJanzell\FilamentPageBuilder\Filament\Pages\DesignPage as BaseDesignPage;

/** Full-screen drag-and-drop editor for a funnel step or website page (Carl Janzell Page Builder). */
class DesignPage extends BaseDesignPage
{
    protected static string $resource = PageResource::class;
}
