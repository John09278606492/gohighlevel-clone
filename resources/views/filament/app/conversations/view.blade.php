<x-filament-panels::page>
    <div class="ghl-thread" wire:poll.15s>
        @forelse ($this->getMessages() as $message)
            @php
                $mine = $message->direction === 'outbound';
                $isNote = $message->channel === 'note';
            @endphp
            <div @class(['ghl-msg', 'ghl-msg--out' => $mine && ! $isNote, 'ghl-msg--in' => ! $mine, 'ghl-msg--note' => $isNote])>
                <div class="ghl-msg__meta">
                    <span class="ghl-msg__channel">{{ $isNote ? 'Note' : strtoupper($message->channel) }}</span>
                    @if ($message->user) · {{ $message->user->name }} @endif
                    · {{ $message->created_at->diffForHumans() }}
                    @if ($message->status === 'failed')
                        · <span class="ghl-msg__failed" title="{{ $message->error }}">failed</span>
                    @elseif ($message->status === 'queued')
                        · queued
                    @endif
                </div>
                <div class="ghl-msg__bubble">
                    @if ($message->subject)
                        <div class="ghl-msg__subject">{{ $message->subject }}</div>
                    @endif
                    {!! nl2br(e($message->body)) !!}
                </div>
            </div>
        @empty
            <p class="ghl-thread__empty">No messages yet. Send the first one below.</p>
        @endforelse
    </div>

    <x-filament::section>
        <form wire:submit="send" class="ghl-composer">
            {{ $this->form }}
            <div class="ghl-composer__actions">
                <x-filament::button type="submit" icon="heroicon-o-paper-airplane">Send</x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
