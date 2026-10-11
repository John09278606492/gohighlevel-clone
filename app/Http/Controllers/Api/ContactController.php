<?php

namespace App\Http\Controllers\Api;

use App\Models\Contact;
use App\Services\MessageSender;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends ApiController
{
    private const FIELDS = ['first_name', 'last_name', 'email', 'phone', 'company', 'type', 'source', 'address', 'city', 'country', 'date_of_birth', 'dnd', 'custom_fields'];

    public function index(Request $request)
    {
        $contacts = Contact::with('tags')
            ->where('sub_account_id', $this->account($request)->id)
            ->when($request->query('query'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->when($request->query('email'), fn ($q, $email) => $q->where('email', $email))
            ->when($request->query('tag'), fn ($q, $tag) => $q->withAnyTags([$tag]))
            ->when($request->query('updated_since'), fn ($q, $since) => $q->where('updated_at', '>=', $since))
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json($contacts->through(fn (Contact $c) => $this->present($c)));
    }

    public function show(Request $request, int $id)
    {
        return response()->json(['data' => $this->present($this->find($request, $id))]);
    }

    /** Creates a contact, or updates the one with the same email or phone (like GoHighLevel's upsert). */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'email' => ['nullable', 'email', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:50', 'required_without:email'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
        ]);

        $contact = Contact::upsertFor($this->account($request), collect($data)->only(self::FIELDS)->all() + ['source' => $data['source'] ?? 'API']);

        if (! empty($data['tags'])) {
            $contact->attachTags($data['tags']);
        }

        return response()->json(['data' => $this->present($contact->fresh('tags'))], $contact->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, int $id)
    {
        $contact = $this->find($request, $id);
        $data = $request->validate($this->rules());

        if (isset($data['custom_fields'])) {
            $data['custom_fields'] = array_merge((array) $contact->custom_fields, $data['custom_fields']);
        }

        $contact->update(collect($data)->only(self::FIELDS)->all());

        return response()->json(['data' => $this->present($contact->fresh('tags'))]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->find($request, $id)->delete();

        return response()->noContent();
    }

    public function addTags(Request $request, int $id)
    {
        $contact = $this->find($request, $id);
        $contact->attachTags($request->validate(['tags' => ['required', 'array'], 'tags.*' => ['string', 'max:100']])['tags']);

        return response()->json(['data' => $this->present($contact->fresh('tags'))]);
    }

    public function removeTags(Request $request, int $id)
    {
        $contact = $this->find($request, $id);
        $contact->detachTags($request->validate(['tags' => ['required', 'array'], 'tags.*' => ['string']])['tags']);

        return response()->json(['data' => $this->present($contact->fresh('tags'))]);
    }

    public function addNote(Request $request, int $id)
    {
        $note = $this->find($request, $id)->notes()->create($request->validate(['body' => ['required', 'string', 'max:20000']]));

        return response()->json(['data' => $note->only(['id', 'body', 'created_at'])], 201);
    }

    public function sendMessage(Request $request, int $id)
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['email', 'sms'])],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $message = MessageSender::send($this->find($request, $id), $data['channel'], $data['body'], $data['subject'] ?? null, ['respect_dnd' => true]);

        return response()->json(['data' => $message->only(['id', 'channel', 'status', 'error', 'created_at'])], $message->status === 'failed' ? 422 : 201);
    }

    private function find(Request $request, int $id): Contact
    {
        return Contact::with('tags')->where('sub_account_id', $this->account($request)->id)->findOrFail($id);
    }

    private function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['lead', 'customer'])],
            'source' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'dnd' => ['nullable', 'boolean'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }

    public static function present(Contact $c): array
    {
        return [
            'id' => $c->id,
            'first_name' => $c->first_name,
            'last_name' => $c->last_name,
            'full_name' => $c->full_name,
            'email' => $c->email,
            'phone' => $c->phone,
            'company' => $c->company,
            'type' => $c->type,
            'source' => $c->source,
            'address' => $c->address,
            'city' => $c->city,
            'country' => $c->country,
            'date_of_birth' => $c->date_of_birth?->toDateString(),
            'dnd' => $c->dnd,
            'tags' => $c->tagNames(),
            'custom_fields' => (object) ($c->custom_fields ?? []),
            'last_activity_at' => $c->last_activity_at?->toIso8601String(),
            'created_at' => $c->created_at?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
        ];
    }
}
