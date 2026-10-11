<?php

namespace App\Flow\Triggers;

use App\Support\CrmEvents;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Packstub\Flow\Nodes\Trigger;

/** Starts a workflow when something happens to a contact (see App\Support\CrmEvents). */
class CrmEvent extends Trigger
{
    public function getName(): string
    {
        return 'CRM event';
    }

    public function getDescription(): string
    {
        return 'Tag added, customer replied, invoice paid, link clicked, review received and more.';
    }

    public function getIcon(): ?string
    {
        return 'heroicon-o-sparkles';
    }

    public function getFormSchema(): array
    {
        return [
            Select::make('event')
                ->options(CrmEvents::EVENTS)
                ->required()
                ->live(),
            TextInput::make('filter')
                ->label(fn (Get $get) => match ($get('event')) {
                    'tag_added', 'tag_removed' => 'Only for tag',
                    'appointment_status', 'opportunity_status' => 'Only for status',
                    'inbound_message' => 'Only for channel (email, sms, chat)',
                    'opportunity_stage' => 'Only for stage name',
                    'trigger_link_clicked' => 'Only for link name',
                    'review_received' => 'Only for ratings at or below',
                    default => 'Filter',
                })
                ->helperText('Leave empty to run for every event of this type.')
                ->visible(fn (Get $get) => in_array($get('event'), ['tag_added', 'tag_removed', 'appointment_status', 'opportunity_status', 'opportunity_stage', 'inbound_message', 'trigger_link_clicked', 'review_received'], true)),
        ];
    }

    public function matches(array $config, array $payload): bool
    {
        if (($config['event'] ?? null) !== ($payload['event'] ?? null)) {
            return false;
        }

        $filter = trim((string) ($config['filter'] ?? ''));

        if ($filter === '') {
            return true;
        }

        $data = (array) ($payload['data'] ?? []);

        return match ($payload['event']) {
            'tag_added', 'tag_removed' => strcasecmp($filter, (string) ($data['tag'] ?? '')) === 0,
            'appointment_status', 'opportunity_status' => strcasecmp($filter, (string) ($data['status'] ?? '')) === 0,
            'opportunity_stage' => strcasecmp($filter, (string) ($data['stage'] ?? '')) === 0,
            'inbound_message' => strcasecmp($filter, (string) ($data['channel'] ?? '')) === 0,
            'trigger_link_clicked' => strcasecmp($filter, (string) ($data['link'] ?? '')) === 0,
            'review_received' => (int) ($data['rating'] ?? 0) <= (int) $filter,
            default => true,
        };
    }

    public function getPlaceholders(): array
    {
        return [
            '{{ model.first_name }}' => 'The contact',
            '{{ data.tag }}' => 'Event details (tag, status, channel, body, rating, link...)',
        ];
    }
}
