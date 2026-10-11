<?php

namespace App\Http\Controllers\Api;

use App\Models\Appointment;
use App\Models\Calendar;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Pipelines, opportunities, calendars, appointments and invoices. */
class SalesController extends ApiController
{
    public function pipelines(Request $request)
    {
        return response()->json(['data' => Pipeline::with('stages:id,pipeline_id,name,sort')
            ->where('sub_account_id', $this->account($request)->id)->get(['id', 'name'])]);
    }

    public function opportunities(Request $request)
    {
        $page = Opportunity::with('stage:id,name', 'contact:id,first_name,last_name,email')
            ->where('sub_account_id', $this->account($request)->id)
            ->when($request->query('pipeline_id'), fn ($q, $id) => $q->where('pipeline_id', $id))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('contact_id'), fn ($q, $id) => $q->where('contact_id', $id))
            ->latest()->paginate($this->perPage($request));

        return response()->json($page);
    }

    public function storeOpportunity(Request $request)
    {
        $account = $this->account($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pipeline_id' => ['required', Rule::exists('pipelines', 'id')->where('sub_account_id', $account->id)],
            'pipeline_stage_id' => ['nullable', 'integer'],
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('sub_account_id', $account->id)],
            'value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(array_keys(Opportunity::STATUSES))],
            'source' => ['nullable', 'string', 'max:255'],
        ]);

        $this->assertStage($data['pipeline_stage_id'] ?? null, (int) $data['pipeline_id']);

        $opportunity = Opportunity::create($data + ['sub_account_id' => $account->id, 'status' => 'open', 'value' => 0]);

        return response()->json(['data' => $opportunity->fresh('stage')], 201);
    }

    public function updateOpportunity(Request $request, int $id)
    {
        $opportunity = Opportunity::where('sub_account_id', $this->account($request)->id)->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'pipeline_stage_id' => ['sometimes', 'integer'],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(array_keys(Opportunity::STATUSES))],
        ]);

        if (isset($data['pipeline_stage_id'])) {
            $this->assertStage($data['pipeline_stage_id'], null, $opportunity->sub_account_id);
            $data['position'] = bcadd((string) (Opportunity::where('pipeline_stage_id', $data['pipeline_stage_id'])->max('position') ?? 0), '65535', 10);
        }

        $opportunity->update($data);

        return response()->json(['data' => $opportunity->fresh('stage')]);
    }

    public function calendars(Request $request)
    {
        return response()->json(['data' => Calendar::where('sub_account_id', $this->account($request)->id)
            ->get(['id', 'name', 'slug', 'duration_minutes', 'timezone', 'is_active'])
            ->map(fn (Calendar $c) => $c->toArray() + ['booking_url' => $c->bookingUrl()])]);
    }

    public function appointments(Request $request)
    {
        $page = Appointment::with('contact:id,first_name,last_name,email,phone')
            ->where('sub_account_id', $this->account($request)->id)
            ->when($request->query('from'), fn ($q, $from) => $q->where('starts_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->where('starts_at', '<=', $to))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('starts_at')->paginate($this->perPage($request));

        return response()->json($page);
    }

    public function storeAppointment(Request $request)
    {
        $account = $this->account($request);
        $data = $request->validate([
            'calendar_id' => ['required', Rule::exists('calendars', 'id')->where('sub_account_id', $account->id)],
            'contact_id' => ['required', Rule::exists('contacts', 'id')->where('sub_account_id', $account->id)],
            'title' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string'],
        ]);

        $contact = Contact::find($data['contact_id']);
        $appointment = Appointment::create($data + [
            'sub_account_id' => $account->id,
            'title' => $data['title'] ?? Calendar::find($data['calendar_id'])->name.' with '.$contact->full_name,
            'source' => 'api',
        ]);

        return response()->json(['data' => $appointment], 201);
    }

    public function updateAppointment(Request $request, int $id)
    {
        $appointment = Appointment::where('sub_account_id', $this->account($request)->id)->findOrFail($id);
        $appointment->update($request->validate([
            'status' => ['sometimes', Rule::in(array_keys(Appointment::STATUSES))],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]));

        return response()->json(['data' => $appointment]);
    }

    public function invoices(Request $request)
    {
        $page = Invoice::with('contact:id,first_name,last_name,email')
            ->where('sub_account_id', $this->account($request)->id)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate($this->perPage($request));

        return response()->json($page->through(fn (Invoice $i) => $i->makeHidden('token')->toArray() + ['url' => $i->publicUrl()]));
    }

    private function assertStage(?int $stageId, ?int $pipelineId, ?int $subAccountId = null): void
    {
        if ($stageId === null) {
            return;
        }

        $stage = PipelineStage::with('pipeline')->find($stageId);
        $ok = $stage && ($pipelineId ? $stage->pipeline_id === $pipelineId : $stage->pipeline->sub_account_id === $subAccountId);

        abort_unless($ok, 422, 'pipeline_stage_id does not belong to that pipeline.');
    }
}
