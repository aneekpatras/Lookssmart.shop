<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkMessageActionRequest;
use App\Http\Requests\Admin\ReplyToMessageRequest;
use App\Models\Message;
use App\Models\MessageReply;
use App\Notifications\MessageReply as MessageReplyNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Message::class);

        $query = Message::query();

        if ($request->string('status')->value() === 'archived') {
            $query->archived();
        } else {
            $query->notArchived();

            if ($request->filled('status')) {
                $query->where('status', $request->string('status')->value());
            }
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->value();
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('overdue_only')) {
            $query->overdue();
        }

        $messages = $query->withCount('replies')->latest()->get();

        return Inertia::render('Admin/CRM/Messages/Index', [
            'messages' => $messages->map(fn (Message $message) => $this->messageData($message)),
            'stats' => [
                'unread' => Message::notArchived()->where('status', 'unread')->count(),
                'overdue' => Message::notArchived()->overdue()->count(),
                'spam' => Message::notArchived()->where('status', 'spam')->count(),
                'archived' => Message::archived()->count(),
            ],
            'filters' => [
                'status' => $request->string('status')->value() ?: null,
                'search' => $request->string('search')->value() ?: null,
                'overdue_only' => $request->boolean('overdue_only'),
            ],
        ]);
    }

    /**
     * Returns JSON, not an Inertia page — the admin UI fetches this into a detail/reply drawer
     * without a full page navigation. Also auto-transitions `unread` -> `read` on open, standard
     * inbox behavior; `replied`/`spam` messages are left as-is.
     */
    public function show(Message $message): JsonResponse
    {
        $this->authorize('view', $message);

        if ($message->status === 'unread') {
            $message->update(['status' => 'read']);
        }

        $message->load('replies.author:id,name');

        return response()->json([
            'message' => [
                ...$this->messageData($message),
                'replies' => $message->replies->map(fn (MessageReply $reply) => [
                    'id' => $reply->id,
                    'body' => $reply->body,
                    'author' => $reply->author?->name ?? 'System',
                    'created_at' => $reply->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function reply(ReplyToMessageRequest $request, Message $message): RedirectResponse
    {
        $body = $request->validated('body');

        $message->replies()->create([
            'user_id' => $request->user()->id,
            'body' => $body,
        ]);

        $message->update(['status' => 'replied', 'replied_at' => now()]);

        Notification::route('mail', $message->email)
            ->notify(new MessageReplyNotification($message, $body, $request->user()->name));

        return back()->with('success', 'Reply sent.');
    }

    public function toggleSpam(Message $message): RedirectResponse
    {
        $this->authorize('update', $message);

        $message->update(['status' => $message->status === 'spam' ? 'read' : 'spam']);

        return back()->with('success', $message->status === 'spam' ? 'Marked as spam.' : 'Unmarked as spam.');
    }

    public function bulkAction(BulkMessageActionRequest $request): RedirectResponse
    {
        $messages = Message::whereIn('id', $request->validated('ids'));

        match ($request->validated('action')) {
            'mark_read' => $messages->update(['status' => 'read']),
            'mark_unread' => $messages->update(['status' => 'unread']),
            'archive' => $messages->update(['archived_at' => now()]),
            'unarchive' => $messages->update(['archived_at' => null]),
            'spam' => $messages->update(['status' => 'spam']),
            'delete' => $messages->delete(),
        };

        return back()->with('success', 'Messages updated.');
    }

    public function destroy(Message $message): RedirectResponse
    {
        $this->authorize('delete', $message);

        $message->delete();

        return back()->with('success', 'Message deleted.');
    }

    /** @return array<string, mixed> */
    private function messageData(Message $message): array
    {
        return [
            'id' => $message->id,
            'name' => $message->name,
            'email' => $message->email,
            'phone' => $message->phone,
            'subject' => $message->subject,
            'body' => $message->body,
            'status' => $message->status,
            'replied_at' => $message->replied_at?->toIso8601String(),
            'archived_at' => $message->archived_at?->toIso8601String(),
            'replies_count' => $message->replies_count ?? 0,
            'is_overdue' => $message->isOverdue(),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
