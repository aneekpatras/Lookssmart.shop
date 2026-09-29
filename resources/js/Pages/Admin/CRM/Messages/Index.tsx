import { Head, router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { Archive, ArchiveRestore, Mail, Trash2 } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Textarea } from '@/Components/ui/textarea';
import AdminLayout from '@/Layouts/AdminLayout';

type MessageStatus = 'unread' | 'read' | 'replied' | 'spam';

interface MessageRow {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  subject: string | null;
  body: string;
  status: MessageStatus;
  replied_at: string | null;
  archived_at: string | null;
  replies_count: number;
  is_overdue: boolean;
  created_at: string | null;
}

interface ReplyEntry {
  id: number;
  body: string;
  author: string;
  created_at: string | null;
}

interface MessageDetail extends MessageRow {
  replies: ReplyEntry[];
}

interface MessagesIndexPageProps {
  messages: MessageRow[];
  stats: { unread: number; overdue: number; spam: number; archived: number };
  filters: { status: string | null; search: string | null; overdue_only: boolean };
}

const STATUS_BADGE: Record<MessageStatus, 'default' | 'accent' | 'success' | 'warning' | 'destructive'> = {
  unread: 'accent',
  read: 'default',
  replied: 'success',
  spam: 'destructive',
};

const REPLY_TEMPLATES = [
  { label: 'Thank you', body: 'Thank you for reaching out — we appreciate you contacting us and will follow up with the details you need shortly.' },
  { label: 'Booking help', body: 'Thanks for your message! You can book directly on our website, or let us know your preferred date and service and we will help you find a time.' },
  { label: 'Follow up', body: 'Just following up on your message — please let us know if you have any other questions in the meantime.' },
];

function FiltersBar({ filters }: { filters: MessagesIndexPageProps['filters'] }) {
  const [search, setSearch] = React.useState(filters.search ?? '');

  function apply(next: Partial<Record<'status' | 'search', string | null>>) {
    router.get(
      '/admin/messages',
      {
        status: next.status !== undefined ? next.status || undefined : filters.status || undefined,
        search: next.search !== undefined ? next.search || undefined : filters.search || undefined,
        overdue_only: filters.overdue_only ? '1' : undefined,
      },
      { preserveState: true, preserveScroll: true, replace: true },
    );
  }

  return (
    <div className="flex flex-wrap items-end gap-3">
      <form
        className="flex items-end gap-2"
        onSubmit={(e) => {
          e.preventDefault();
          apply({ search });
        }}
      >
        <div className="space-y-1.5">
          <Label htmlFor="msg-search">Search</Label>
          <Input id="msg-search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Name, email, subject..." />
        </div>
        <Button type="submit" variant="outline">
          Search
        </Button>
      </form>

      <div className="space-y-1.5">
        <Label htmlFor="msg-status">Status</Label>
        <Select value={filters.status ?? 'inbox'} onValueChange={(value) => apply({ status: value === 'inbox' ? null : value })}>
          <SelectTrigger id="msg-status" className="w-44">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="inbox">Inbox (all active)</SelectItem>
            <SelectItem value="unread">Unread</SelectItem>
            <SelectItem value="read">Read</SelectItem>
            <SelectItem value="replied">Replied</SelectItem>
            <SelectItem value="spam">Spam</SelectItem>
            <SelectItem value="archived">Archived</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div className="flex items-center gap-2 pb-2">
        <Checkbox
          checked={filters.overdue_only}
          aria-label="Overdue only"
          onCheckedChange={() =>
            router.get(
              '/admin/messages',
              { status: filters.status || undefined, search: filters.search || undefined, overdue_only: filters.overdue_only ? undefined : '1' },
              { preserveState: true, preserveScroll: true, replace: true },
            )
          }
        />
        <span className="text-sm">Overdue only</span>
      </div>
    </div>
  );
}

function ReplyDrawer({ messageId, onClose }: { messageId: number; onClose: () => void }) {
  const [message, setMessage] = React.useState<MessageDetail | null>(null);
  const replyForm = useForm({ body: '' });

  const load = React.useCallback(() => {
    axios.get<{ message: MessageDetail }>(`/admin/messages/${messageId}`).then((response) => {
      setMessage(response.data.message);
    });
  }, [messageId]);

  React.useEffect(() => {
    load();
  }, [load]);

  function submitReply(event: React.FormEvent) {
    event.preventDefault();
    replyForm.post(`/admin/messages/${messageId}/reply`, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Reply sent.');
        replyForm.reset();
        load();
        router.reload({ only: ['messages', 'stats'] });
      },
    });
  }

  function toggleSpam() {
    router.patch(`/admin/messages/${messageId}/spam`, {}, { preserveScroll: true, onSuccess: load });
  }

  return (
    <Sheet open onOpenChange={(open) => !open && onClose()}>
      <SheetContent className="overflow-y-auto sm:max-w-lg">
        <SheetHeader>
          <SheetTitle>{message?.subject || message?.name || 'Loading…'}</SheetTitle>
        </SheetHeader>

        {message && (
          <div className="space-y-6">
            <div className="text-sm">
              <p className="font-medium">{message.name}</p>
              <p className="text-ink-muted">
                {message.email}
                {message.phone ? ` · ${message.phone}` : ''}
              </p>
              <p className="text-ink-muted mt-1">{message.created_at ? new Date(message.created_at).toLocaleString() : ''}</p>
              {message.is_overdue && (
                <Badge variant="destructive" className="mt-2">
                  Overdue
                </Badge>
              )}
            </div>

            <p className="border-border-soft rounded-lg border p-3 text-sm whitespace-pre-line">{message.body}</p>

            <div className="flex gap-2">
              <Button type="button" size="sm" variant="outline" onClick={toggleSpam}>
                <Mail className="size-4" />
                {message.status === 'spam' ? 'Not spam' : 'Mark spam'}
              </Button>
            </div>

            {message.replies.length > 0 && (
              <div className="space-y-3">
                <p className="text-ink-muted text-xs font-semibold tracking-wide uppercase">Thread</p>
                <ul className="space-y-3">
                  {message.replies.map((reply) => (
                    <li key={reply.id} className="border-accent-300 border-l-2 pl-3 text-sm">
                      <p className="whitespace-pre-line">{reply.body}</p>
                      <p className="text-ink-muted mt-1 text-xs">
                        {reply.author} · {reply.created_at ? new Date(reply.created_at).toLocaleString() : ''}
                      </p>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            <form onSubmit={submitReply} className="space-y-2">
              <Label htmlFor="reply-body">Reply</Label>
              <div className="flex flex-wrap gap-1">
                {REPLY_TEMPLATES.map((template) => (
                  <Button
                    key={template.label}
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => replyForm.setData('body', template.body)}
                  >
                    {template.label}
                  </Button>
                ))}
              </div>
              <Textarea
                id="reply-body"
                rows={5}
                value={replyForm.data.body}
                onChange={(e) => replyForm.setData('body', e.target.value)}
              />
              {replyForm.errors.body && <p className="text-sm text-red-600">{replyForm.errors.body}</p>}
              <Button type="submit" disabled={replyForm.processing || !replyForm.data.body}>
                Send reply
              </Button>
            </form>
          </div>
        )}
      </SheetContent>
    </Sheet>
  );
}

export default function MessagesIndex({ messages, stats, filters }: MessagesIndexPageProps) {
  const [openId, setOpenId] = React.useState<number | null>(null);
  const [selected, setSelected] = React.useState<number[]>([]);

  function toggleSelect(id: number) {
    setSelected((current) => (current.includes(id) ? current.filter((i) => i !== id) : [...current, id]));
  }

  function bulk(action: string) {
    if (selected.length === 0) return;
    router.post(
      '/admin/messages/bulk',
      { ids: selected, action },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success('Messages updated.');
          setSelected([]);
        },
      },
    );
  }

  return (
    <>
      <Head title="Messages" />
      <div className="space-y-6">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">Messages</h1>
          <p className="text-ink-muted text-sm">
            {stats.unread} unread · {stats.overdue} overdue · {stats.spam} spam · {stats.archived} archived
          </p>
        </div>

        <FiltersBar filters={filters} />

        {selected.length > 0 && (
          <div className="bg-accent-50 flex flex-wrap items-center gap-2 rounded-lg p-2">
            <span className="px-2 text-sm">{selected.length} selected</span>
            <Button type="button" size="sm" variant="outline" onClick={() => bulk('mark_read')}>
              Mark read
            </Button>
            <Button type="button" size="sm" variant="outline" onClick={() => bulk('mark_unread')}>
              Mark unread
            </Button>
            <Button type="button" size="sm" variant="outline" onClick={() => bulk('archive')}>
              <Archive className="size-4" />
              Archive
            </Button>
            <Button type="button" size="sm" variant="outline" onClick={() => bulk('unarchive')}>
              <ArchiveRestore className="size-4" />
              Unarchive
            </Button>
            <Button type="button" size="sm" variant="outline" onClick={() => bulk('spam')}>
              Mark spam
            </Button>
            <Button
              type="button"
              size="sm"
              variant="destructive"
              onClick={() => {
                if (confirm(`Delete ${selected.length} message(s)?`)) bulk('delete');
              }}
            >
              <Trash2 className="size-4" />
              Delete
            </Button>
          </div>
        )}

        <div className="border-border-soft overflow-x-auto rounded-lg border">
          <table className="w-full text-sm">
            <thead className="bg-accent-50/60">
              <tr>
                <th className="w-10 p-3" />
                <th className="p-3 text-left">From</th>
                <th className="p-3 text-left">Subject</th>
                <th className="p-3 text-left">Status</th>
                <th className="p-3 text-left">Replies</th>
                <th className="p-3 text-left">Received</th>
              </tr>
            </thead>
            <tbody className="divide-border-soft divide-y">
              {messages.map((message) => (
                <tr key={message.id} className="hover:bg-accent-50/40">
                  <td className="p-3" onClick={(e) => e.stopPropagation()}>
                    <Checkbox checked={selected.includes(message.id)} onCheckedChange={() => toggleSelect(message.id)} />
                  </td>
                  <td className="cursor-pointer p-3" onClick={() => setOpenId(message.id)}>
                    <p className={message.status === 'unread' ? 'font-semibold' : ''}>{message.name}</p>
                    <p className="text-ink-muted text-xs">{message.email}</p>
                  </td>
                  <td className="cursor-pointer p-3" onClick={() => setOpenId(message.id)}>
                    {message.subject ?? '—'}
                  </td>
                  <td className="cursor-pointer p-3" onClick={() => setOpenId(message.id)}>
                    <div className="flex flex-wrap gap-1">
                      <Badge variant={STATUS_BADGE[message.status]}>{message.status}</Badge>
                      {message.is_overdue && <Badge variant="destructive">Overdue</Badge>}
                      {message.archived_at && <Badge variant="outline">Archived</Badge>}
                    </div>
                  </td>
                  <td className="cursor-pointer p-3" onClick={() => setOpenId(message.id)}>
                    {message.replies_count}
                  </td>
                  <td className="cursor-pointer p-3" onClick={() => setOpenId(message.id)}>
                    {message.created_at ? new Date(message.created_at).toLocaleDateString() : '—'}
                  </td>
                </tr>
              ))}
              {messages.length === 0 && (
                <tr>
                  <td colSpan={6} className="text-ink-muted p-6 text-center text-sm">
                    No messages found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {openId && <ReplyDrawer messageId={openId} onClose={() => setOpenId(null)} />}
    </>
  );
}

MessagesIndex.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
