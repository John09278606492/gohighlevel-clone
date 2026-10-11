<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\SubAccount;
use App\Services\InboundMessages;
use Illuminate\Http\Request;

/**
 * Website chat widget. One <script> tag on any site shows a chat bubble; the
 * visitor leaves their details and a message, which lands in Conversations. Replies
 * sent on the "Chat" channel show up in the widget (it polls), so it works as live chat.
 */
class ChatWidgetController extends Controller
{
    public function script(SubAccount $subAccount)
    {
        abort_unless($subAccount->chat_widget_enabled && $subAccount->is_active, 404);

        return response()
            ->view('public.chat-widget-js', ['account' => $subAccount], 200)
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function send(Request $request, SubAccount $subAccount)
    {
        abort_unless($subAccount->chat_widget_enabled && $subAccount->is_active, 404);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255', 'required_without_all:phone,visitor'],
            'phone' => ['nullable', 'string', 'max:40', 'required_without_all:email,visitor'],
            'message' => ['required', 'string', 'max:2000'],
            'visitor' => ['nullable', 'string', 'size:40'],
            'page' => ['nullable', 'string', 'max:500'],
        ]);

        $contact = $this->visitor($subAccount, $data['visitor'] ?? null);
        $attributes = $contact
            ? array_filter(['email' => $contact->email, 'phone' => $contact->phone])
            : array_filter(['email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null]);

        if (! $contact && filled($data['name'] ?? null)) {
            [$first, $last] = array_pad(explode(' ', trim($data['name']), 2), 2, null);
            $attributes += ['first_name' => $first, 'last_name' => $last];
        }

        $message = InboundMessages::receive($subAccount, $attributes, 'chat', $data['message'], source: 'Web chat'.($data['page'] ?? null ? ': '.$data['page'] : ''));
        $contact = $message->conversation->contact;

        return response()->json([
            'visitor' => $contact->trackingToken(),
            'messages' => $this->thread($contact),
        ]);
    }

    public function poll(Request $request, SubAccount $subAccount)
    {
        $contact = $this->visitor($subAccount, (string) $request->query('visitor'));

        return response()->json(['messages' => $contact ? $this->thread($contact) : []]);
    }

    private function visitor(SubAccount $account, ?string $token): ?Contact
    {
        $contact = Contact::findByTrackingToken($token);

        return $contact && $contact->sub_account_id === $account->id ? $contact : null;
    }

    private function thread(Contact $contact): array
    {
        return $contact->conversationOrCreate()->messages()
            ->where('channel', 'chat')
            ->latest('id')->limit(50)->get()->reverse()->values()
            ->map(fn ($m) => ['id' => $m->id, 'from' => $m->direction === 'inbound' ? 'visitor' : 'agent', 'body' => $m->body, 'at' => $m->created_at->toIso8601String()])
            ->all();
    }
}
