<?php

namespace App\Filament\App\Pages\Tenancy;

use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/** Sub-account settings: business profile, messaging, payments, reputation, chat widget, social and integrations. */
class EditSubAccountProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Settings';
    }

    public static function canView(Model $tenant): bool
    {
        return (bool) auth()->user()?->isAdminOf($tenant);
    }

    public function form(Schema $schema): Schema
    {
        $tenant = Filament::getTenant();
        $code = fn (string $text) => new HtmlString('<code style="word-break:break-all">'.e($text).'</code>');

        return $schema->components([
            Tabs::make('settings')->persistTabInQueryString()->tabs([
                Tab::make('Business')->icon('heroicon-o-building-storefront')->columns(2)->schema([
                    TextInput::make('name')->label('Business name')->required(),
                    TextInput::make('email')->email()->helperText('Used as reply-to on emails.'),
                    TextInput::make('phone')->tel(),
                    TextInput::make('website')->url(),
                    TextInput::make('address')->columnSpanFull(),
                    Select::make('timezone')->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))->searchable(),
                    Select::make('currency')->options(['USD' => 'USD', 'EUR' => 'EUR', 'GBP' => 'GBP', 'CAD' => 'CAD', 'AUD' => 'AUD', 'PHP' => 'PHP', 'INR' => 'INR', 'NZD' => 'NZD', 'SGD' => 'SGD', 'ZAR' => 'ZAR'])->required(),
                    FileUpload::make('logo')->image()->disk('public')->directory('logos'),
                    ColorPicker::make('brand_color'),
                ]),

                Tab::make('Messaging')->icon('heroicon-o-chat-bubble-left-right')->schema([
                    Section::make('Email')->columns(2)->schema([
                        TextInput::make('email_from_name')->placeholder($tenant?->name),
                        TextInput::make('email_from_address')->email()->helperText('Must be a sender your mail provider (MAIL_* in .env) allows.'),
                        TextEntry::make('inbound_email')->label('Inbound email webhook')->columnSpanFull()
                            ->state($tenant ? $code(route('webhooks.email', [$tenant, $tenant->inbound_secret ?? 'save-first'])) : null)
                            ->helperText('Point Mailgun / Postmark / SendGrid inbound parse here so customer replies land in Conversations.'),
                    ]),
                    Section::make('SMS (Twilio)')->columns(2)->schema([
                        TextInput::make('twilio_sid')->label('Account SID'),
                        TextInput::make('twilio_token')->label('Auth token')->password()->revealable()
                            ->dehydrated(fn ($state) => filled($state))
                            ->placeholder(fn () => $tenant?->twilio_token ? 'Saved (leave empty to keep)' : null),
                        TextInput::make('twilio_from')->label('Twilio number')->tel()->placeholder('+15551234567'),
                        TextEntry::make('twilio_webhook')->label('Messaging webhook (set on the Twilio number)')
                            ->state($tenant ? $code(route('webhooks.twilio.sms', $tenant)) : null),
                    ]),
                ]),

                Tab::make('Payments')->icon('heroicon-o-credit-card')->columns(2)->schema([
                    TextInput::make('stripe_secret_key')->label('Stripe secret key')->password()->revealable()
                        ->dehydrated(fn ($state) => filled($state))
                        ->placeholder(fn () => $tenant?->stripe_secret_key ? 'Saved (leave empty to keep)' : 'sk_live_… or sk_test_…')
                        ->helperText('Adds a "Pay now" button (Stripe Checkout) to invoices.'),
                    TextEntry::make('stripe_webhook')->label('Stripe webhook (checkout.session.completed)')
                        ->state($tenant ? $code(route('webhooks.stripe', $tenant)) : null),
                    TextInput::make('default_tax_rate')->numeric()->suffix('%')->minValue(0)->maxValue(100),
                    Textarea::make('invoice_footer')->rows(3)->placeholder('Bank details, terms, thank-you note…')->columnSpanFull(),
                ]),

                Tab::make('Reputation')->icon('heroicon-o-star')->columns(2)->schema([
                    TextInput::make('google_review_url')->url()->label('Google review link'),
                    TextInput::make('facebook_review_url')->url()->label('Facebook review link'),
                    Select::make('review_threshold')->label('Send to Google/Facebook at')
                        ->options([3 => '3 stars and up', 4 => '4 stars and up', 5 => '5 stars only'])
                        ->helperText('Lower ratings become private feedback instead.'),
                    TextEntry::make('review_page')->label('Public review page (for QR codes)')
                        ->state($tenant ? $code(route('reviews.generic', $tenant)) : null),
                    Textarea::make('review_request_message')->rows(3)->columnSpanFull()
                        ->placeholder(\App\Services\ReviewRequests::DEFAULT_MESSAGE)
                        ->helperText('Include {{ review_link }}. Merge tags like {{ contact.first_name }} work too.'),
                ]),

                Tab::make('Chat widget')->icon('heroicon-o-chat-bubble-oval-left')->columns(2)->schema([
                    Toggle::make('chat_widget_enabled')->label('Enable chat widget')->columnSpanFull(),
                    ColorPicker::make('chat_widget_color'),
                    TextInput::make('chat_widget_greeting')->maxLength(255)->placeholder('Hi! How can we help?'),
                    TextEntry::make('snippet')->label('Paste before </body> on any website')->columnSpanFull()
                        ->state($tenant ? $code($tenant->chatWidgetSnippet()) : null)
                        ->helperText('Visitors\' messages arrive in Conversations. Reply on the "Chat" channel and they see it live.'),
                ]),

                Tab::make('Social')->icon('heroicon-o-share')->schema([
                    TextInput::make('social_webhook_url')->url()->label('Publishing webhook')
                        ->helperText('Scheduled posts are sent here as JSON when they are due (e.g. a Zapier, Make or n8n webhook that posts to your networks). Leave empty to use the planner as a content calendar.'),
                ]),
            ])->columnSpanFull(),
        ]);
    }
}
