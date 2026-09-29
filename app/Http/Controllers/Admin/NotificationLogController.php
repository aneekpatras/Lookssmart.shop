<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationLogController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $this->authorize('viewAny', NotificationLog::class);

        $logs = NotificationLog::query()
            ->when($request->filled('channel'), fn ($query) => $query->where('channel', $request->string('channel')->value()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest('sent_at')
            ->paginate(25)
            ->withQueryString();

        $data = $logs->through(fn (NotificationLog $log) => [
            'id' => $log->id,
            'channel' => $log->channel,
            'recipient' => $log->recipient,
            'status' => $log->status,
            'provider_ref' => $log->provider_ref,
            'error' => $log->error,
            'sent_at' => $log->sent_at?->toIso8601String(),
        ]);

        if ($request->expectsJson()) {
            return response()->json($data);
        }

        return Inertia::render('Admin/NotificationLogs', [
            'logs' => $data,
            'filters' => [
                'channel' => $request->string('channel')->value() ?: null,
                'status' => $request->string('status')->value() ?: null,
            ],
        ]);
    }
}
