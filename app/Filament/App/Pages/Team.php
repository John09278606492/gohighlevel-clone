<?php

namespace App\Filament\App\Pages;

use App\Models\User;
use App\Support\Access;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/** Sub-account admins manage who can use this sub-account and whether they are admins or users. */
class Team extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.app.pages.team';

    public static function canAccess(): bool
    {
        return Access::isTenantAdmin();
    }

    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();

        return $table
            ->query(User::query()->whereHas('subAccounts', fn ($q) => $q->whereKey($tenant->getKey()))->with(['subAccounts' => fn ($q) => $q->whereKey($tenant->getKey())]))
            ->columns([
                TextColumn::make('name')->weight('medium')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->placeholder('-'),
                TextColumn::make('role')->badge()
                    ->state(fn (User $u) => $u->subAccounts->first()?->pivot->role ?? 'user')
                    ->formatStateUsing(fn ($state) => $state === 'admin' ? 'Admin' : 'User')
                    ->color(fn ($state) => $state === 'admin' ? 'primary' : 'gray'),
            ])
            ->headerActions([
                Action::make('add')
                    ->label('Add team member')
                    ->icon('heroicon-o-user-plus')
                    ->schema([
                        TextInput::make('email')->email()->required(),
                        TextInput::make('name')->helperText('Only needed when they don\'t have an account yet.'),
                        TextInput::make('password')->password()->revealable()->minLength(8)->helperText('Only for new accounts. Leave empty to generate one.'),
                        Select::make('role')->options(['user' => 'User', 'admin' => 'Admin'])->default('user')->required(),
                    ])
                    ->action(function (array $data) use ($tenant): void {
                        $user = User::firstWhere('email', strtolower($data['email']));
                        $password = null;

                        if (! $user) {
                            $password = $data['password'] ?: Str::password(12);
                            $user = User::create([
                                'name' => $data['name'] ?: Str::before($data['email'], '@'),
                                'email' => strtolower($data['email']),
                                'password' => $password,
                            ]);
                        }

                        $tenant->users()->syncWithoutDetaching([$user->id => ['role' => $data['role']]]);

                        Notification::make()->success()->title("{$user->name} added")
                            ->body($password && ! $data['password'] ? "Temporary password: {$password}" : null)
                            ->persistent((bool) $password)->send();
                    }),
            ])
            ->recordActions([
                Action::make('role')->label('Change role')->icon('heroicon-o-shield-check')
                    ->schema(fn (User $record) => [Select::make('role')->options(['user' => 'User', 'admin' => 'Admin'])->default($record->subAccounts->first()?->pivot->role)->required()])
                    ->action(fn (User $record, array $data) => $tenant->users()->updateExistingPivot($record->id, ['role' => $data['role']])),
                Action::make('remove')->label('Remove')->icon('heroicon-o-user-minus')->color('danger')->requiresConfirmation()
                    ->visible(fn (User $record) => $record->id !== auth()->id())
                    ->action(fn (User $record) => $tenant->users()->detach($record->id)),
            ]);
    }
}
