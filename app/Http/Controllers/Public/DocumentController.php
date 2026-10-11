<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Document;
use App\Support\CrmEvents;
use Illuminate\Http\Request;

/** Client-facing proposals and contracts with a typed e-signature. */
class DocumentController extends Controller
{
    public function show(Request $request, string $token)
    {
        $document = Document::with('subAccount', 'contact')->where('token', $token)->firstOrFail();
        $isTeam = (bool) $request->user()?->canAccessTenant($document->subAccount);
        abort_if($document->status === 'draft' && ! $isTeam, 404);

        if ($document->status === 'sent' && ! $isTeam) {
            $document->update(['status' => 'viewed', 'viewed_at' => now()]);
            Activity::log($document->contact, 'document', "Viewed \"{$document->title}\"", $document);
        }

        return view('public.document', ['document' => $document]);
    }

    public function sign(Request $request, string $token)
    {
        $document = Document::where('token', $token)->firstOrFail();
        abort_unless($document->canBeSigned(), 422, 'This document can no longer be signed.');

        $data = $request->validate([
            'signer_name' => ['required', 'string', 'max:120'],
            'agree' => ['accepted'],
            'decision' => ['required', 'in:sign,decline'],
        ]);

        if ($data['decision'] === 'decline') {
            $document->update(['status' => 'declined']);
            Activity::log($document->contact, 'document', "Declined \"{$document->title}\"", $document);

            return redirect()->route('documents.public', $token);
        }

        $document->update([
            'status' => 'signed',
            'signer_name' => $data['signer_name'],
            'signer_ip' => $request->ip(),
            'signed_at' => now(),
        ]);

        Activity::log($document->contact, 'document', "Signed \"{$document->title}\" as {$data['signer_name']}", $document);
        CrmEvents::fire('document_signed', $document->contact, $document, ['title' => $document->title, 'amount' => $document->amount]);

        if ($document->opportunity && $document->opportunity->status === 'open') {
            $document->opportunity->update(['status' => 'won']);
        }

        return redirect()->route('documents.public', $token);
    }
}
