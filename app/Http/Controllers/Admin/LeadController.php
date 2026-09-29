<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignLeadRequest;
use App\Http\Requests\Admin\StoreLeadNoteRequest;
use App\Http\Requests\Admin\UpdateLeadStatusRequest;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    private const STATUSES = ['new', 'contacted', 'qualified', 'converted', 'lost'];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Lead::class);

        $leads = Lead::query()
            ->with('assignedTo:id,name')
            ->withCount('internalNotes')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('source'), fn ($query) => $query->where('source', $request->string('source')->value()))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->latest()
            ->get();

        $total = $leads->count();
        $converted = $leads->where('status', 'converted')->count();

        return Inertia::render('Admin/CRM/Leads/Index', [
            'leads' => $leads->map(fn (Lead $lead) => $this->leadData($lead)),
            'sources' => Lead::query()->distinct()->orderBy('source')->pluck('source'),
            'statuses' => self::STATUSES,
            'stats' => [
                'total' => $total,
                'converted' => $converted,
                'conversion_rate' => $total > 0 ? round(($converted / $total) * 100, 1) : 0.0,
            ],
            'filters' => [
                'status' => $request->string('status')->value() ?: null,
                'source' => $request->string('source')->value() ?: null,
                'from' => $request->string('from')->value() ?: null,
                'to' => $request->string('to')->value() ?: null,
            ],
        ]);
    }

    /**
     * Returns JSON rather than an Inertia page — the admin UI fetches this to populate the lead
     * detail drawer without a full page navigation (the drawer opens over the existing Kanban/list).
     */
    public function show(Lead $lead): JsonResponse
    {
        $this->authorize('view', $lead);

        $lead->load(['assignedTo:id,name', 'convertedBooking:id,code', 'internalNotes.author:id,name']);

        return response()->json([
            'lead' => [
                ...$this->leadData($lead),
                'notes_timeline' => $lead->internalNotes->map(fn (LeadNote $note) => [
                    'id' => $note->id,
                    'body' => $note->body,
                    'author' => $note->author?->name ?? 'System',
                    'created_at' => $note->created_at?->toIso8601String(),
                ]),
            ],
            'assignableUsers' => User::role(['admin', 'super-admin', 'receptionist', 'staff'])->get(['id', 'name']),
        ]);
    }

    public function updateStatus(UpdateLeadStatusRequest $request, Lead $lead): RedirectResponse
    {
        $lead->update(['status' => $request->validated('status')]);

        return back()->with('success', "Lead status set to \"{$lead->status}\".");
    }

    public function assign(AssignLeadRequest $request, Lead $lead): RedirectResponse
    {
        $lead->update(['assigned_to' => $request->validated('assigned_to')]);

        return back()->with('success', 'Lead assignment updated.');
    }

    public function storeNote(StoreLeadNoteRequest $request, Lead $lead): RedirectResponse
    {
        $lead->internalNotes()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return back()->with('success', 'Note added.');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $this->authorize('delete', $lead);

        $lead->delete();

        return back()->with('success', 'Lead deleted.');
    }

    /** @return array<string, mixed> */
    private function leadData(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'phone' => $lead->phone,
            'email' => $lead->email,
            'source' => $lead->source,
            'status' => $lead->status,
            'notes' => $lead->notes,
            'assigned_to' => $lead->assigned_to,
            'assigned_to_name' => $lead->assignedTo?->name,
            'converted_booking_id' => $lead->converted_booking_id,
            'internal_notes_count' => $lead->internal_notes_count ?? 0,
            'created_at' => $lead->created_at?->toIso8601String(),
        ];
    }
}
