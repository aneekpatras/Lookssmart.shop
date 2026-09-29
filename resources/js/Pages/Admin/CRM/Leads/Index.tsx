import { Head, router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { LayoutGrid, List, Mail, MessageCircle, Phone, Trash2 } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
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
import { cn } from '@/lib/utils';

type LeadStatus = 'new' | 'contacted' | 'qualified' | 'converted' | 'lost';

interface LeadRow {
  id: number;
  name: string;
  phone: string | null;
  email: string | null;
  source: string;
  status: LeadStatus;
  notes: string | null;
  assigned_to: number | null;
  assigned_to_name: string | null;
  converted_booking_id: number | null;
  internal_notes_count: number;
  created_at: string | null;
}

interface NoteEntry {
  id: number;
  body: string;
  author: string;
  created_at: string | null;
}

interface LeadDetail extends LeadRow {
  notes_timeline: NoteEntry[];
}

interface AssignableUser {
  id: number;
  name: string;
}

interface LeadsIndexPageProps {
  leads: LeadRow[];
  sources: string[];
  statuses: LeadStatus[];
  stats: { total: number; converted: number; conversion_rate: number };
  filters: { status: string | null; source: string | null; from: string | null; to: string | null };
}

const STATUS_LABELS: Record<LeadStatus, string> = {
  new: 'New',
  contacted: 'Contacted',
  qualified: 'Qualified',
  converted: 'Converted',
  lost: 'Lost',
};

const STATUS_BADGE: Record<LeadStatus, 'default' | 'accent' | 'success' | 'warning' | 'destructive'> = {
  new: 'default',
  contacted: 'warning',
  qualified: 'accent',
  converted: 'success',
  lost: 'destructive',
};

function digitsOnly(value: string): string {
  return value.replace(/[^\d+]/g, '');
}

function FiltersBar({
  filters,
  sources,
  statuses,
}: {
  filters: LeadsIndexPageProps['filters'];
  sources: string[];
  statuses: LeadStatus[];
}) {
  function apply(next: Partial<Record<'status' | 'source' | 'from' | 'to', string | null>>) {
    router.get(
      '/admin/leads',
      {
        status: next.status !== undefined ? next.status || undefined : filters.status || undefined,
        source: next.source !== undefined ? next.source || undefined : filters.source || undefined,
        from: next.from !== undefined ? next.from || undefined : filters.from || undefined,
        to: next.to !== undefined ? next.to || undefined : filters.to || undefined,
      },
      { preserveState: true, preserveScroll: true, replace: true },
    );
  }

  return (
    <div className="flex flex-wrap items-end gap-3">
      <div className="space-y-1.5">
        <Label htmlFor="lead-status">Status</Label>
        <Select value={filters.status ?? 'all'} onValueChange={(value) => apply({ status: value === 'all' ? null : value })}>
          <SelectTrigger id="lead-status" className="w-40">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            {statuses.map((status) => (
              <SelectItem key={status} value={status}>
                {STATUS_LABELS[status]}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="lead-source">Source</Label>
        <Select value={filters.source ?? 'all'} onValueChange={(value) => apply({ source: value === 'all' ? null : value })}>
          <SelectTrigger id="lead-source" className="w-40">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All sources</SelectItem>
            {sources.map((source) => (
              <SelectItem key={source} value={source}>
                {source}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="lead-from">From</Label>
        <Input id="lead-from" type="date" value={filters.from ?? ''} onChange={(e) => apply({ from: e.target.value })} />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="lead-to">To</Label>
        <Input id="lead-to" type="date" value={filters.to ?? ''} onChange={(e) => apply({ to: e.target.value })} />
      </div>
    </div>
  );
}

function LeadCard({
  lead,
  onOpen,
  draggable,
}: {
  lead: LeadRow;
  onOpen: () => void;
  draggable?: boolean;
}) {
  return (
    <Card
      draggable={draggable}
      onDragStart={(e) => e.dataTransfer.setData('text/lead-id', String(lead.id))}
      className={cn('cursor-pointer', draggable && 'cursor-grab active:cursor-grabbing')}
    >
      <CardContent className="space-y-2 p-3" onClick={onOpen}>
        <div className="flex items-center justify-between gap-2">
          <p className="text-sm font-medium">{lead.name}</p>
          <Badge variant="outline" className="text-[10px]">
            {lead.source}
          </Badge>
        </div>
        {lead.assigned_to_name && (
          <p className="text-ink-muted text-xs">Assigned: {lead.assigned_to_name}</p>
        )}
        <p className="text-ink-muted text-xs">
          {lead.internal_notes_count} note{lead.internal_notes_count === 1 ? '' : 's'}
        </p>
      </CardContent>
    </Card>
  );
}

function KanbanBoard({
  leads,
  statuses,
  onOpen,
  onMove,
}: {
  leads: LeadRow[];
  statuses: LeadStatus[];
  onOpen: (lead: LeadRow) => void;
  onMove: (leadId: number, status: LeadStatus) => void;
}) {
  const [dragOver, setDragOver] = React.useState<LeadStatus | null>(null);

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
      {statuses.map((status) => (
        <div
          key={status}
          onDragOver={(e) => {
            e.preventDefault();
            setDragOver(status);
          }}
          onDragLeave={() => setDragOver(null)}
          onDrop={(e) => {
            e.preventDefault();
            setDragOver(null);
            const leadId = Number(e.dataTransfer.getData('text/lead-id'));
            if (leadId) onMove(leadId, status);
          }}
          className={cn(
            'border-border-soft min-h-40 space-y-2 rounded-lg border p-2',
            dragOver === status && 'border-accent-500 bg-accent-50',
          )}
        >
          <div className="flex items-center justify-between px-1">
            <h3 className="text-sm font-semibold">{STATUS_LABELS[status]}</h3>
            <Badge variant={STATUS_BADGE[status]}>{leads.filter((l) => l.status === status).length}</Badge>
          </div>
          <div className="space-y-2">
            {leads
              .filter((lead) => lead.status === status)
              .map((lead) => (
                <LeadCard key={lead.id} lead={lead} draggable onOpen={() => onOpen(lead)} />
              ))}
          </div>
        </div>
      ))}
    </div>
  );
}

function LeadTable({ leads, onOpen }: { leads: LeadRow[]; onOpen: (lead: LeadRow) => void }) {
  return (
    <div className="border-border-soft overflow-x-auto rounded-lg border">
      <table className="w-full text-sm">
        <thead className="bg-accent-50/60">
          <tr>
            <th className="p-3 text-left">Name</th>
            <th className="p-3 text-left">Contact</th>
            <th className="p-3 text-left">Source</th>
            <th className="p-3 text-left">Status</th>
            <th className="p-3 text-left">Assigned</th>
            <th className="p-3 text-left">Notes</th>
          </tr>
        </thead>
        <tbody className="divide-border-soft divide-y">
          {leads.map((lead) => (
            <tr key={lead.id} onClick={() => onOpen(lead)} className="hover:bg-accent-50/40 cursor-pointer">
              <td className="p-3 font-medium">{lead.name}</td>
              <td className="p-3">{lead.phone ?? lead.email ?? '—'}</td>
              <td className="p-3">{lead.source}</td>
              <td className="p-3">
                <Badge variant={STATUS_BADGE[lead.status]}>{STATUS_LABELS[lead.status]}</Badge>
              </td>
              <td className="p-3">{lead.assigned_to_name ?? '—'}</td>
              <td className="p-3">{lead.internal_notes_count}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function LeadDrawer({
  leadId,
  assignableUsersFallback,
  onClose,
}: {
  leadId: number;
  assignableUsersFallback: AssignableUser[];
  onClose: () => void;
}) {
  const [lead, setLead] = React.useState<LeadDetail | null>(null);
  const [assignableUsers, setAssignableUsers] = React.useState<AssignableUser[]>(assignableUsersFallback);
  const noteForm = useForm({ body: '' });

  const load = React.useCallback(() => {
    axios.get<{ lead: LeadDetail; assignableUsers: AssignableUser[] }>(`/admin/leads/${leadId}`).then((response) => {
      setLead(response.data.lead);
      setAssignableUsers(response.data.assignableUsers);
    });
  }, [leadId]);

  React.useEffect(() => {
    load();
  }, [load]);

  function updateStatus(status: LeadStatus) {
    router.patch(`/admin/leads/${leadId}/status`, { status }, { preserveScroll: true, onSuccess: load });
  }

  function assign(value: string) {
    router.patch(
      `/admin/leads/${leadId}/assign`,
      { assigned_to: value === 'unassigned' ? null : value },
      { preserveScroll: true, onSuccess: load },
    );
  }

  function submitNote(event: React.FormEvent) {
    event.preventDefault();
    noteForm.post(`/admin/leads/${leadId}/notes`, {
      preserveScroll: true,
      onSuccess: () => {
        noteForm.reset();
        toast.success('Note added.');
        load();
      },
    });
  }

  return (
    <Sheet open onOpenChange={(open) => !open && onClose()}>
      <SheetContent className="overflow-y-auto">
        <SheetHeader>
          <SheetTitle>{lead?.name ?? 'Loading…'}</SheetTitle>
        </SheetHeader>

        {lead && (
          <div className="space-y-6">
            <div className="flex flex-wrap gap-2">
              {lead.phone && (
                <Button asChild size="sm" variant="outline">
                  <a href={`tel:${digitsOnly(lead.phone)}`}>
                    <Phone className="size-4" />
                    Call
                  </a>
                </Button>
              )}
              {lead.email && (
                <Button asChild size="sm" variant="outline">
                  <a href={`mailto:${lead.email}`}>
                    <Mail className="size-4" />
                    Email
                  </a>
                </Button>
              )}
              {lead.phone && (
                <Button asChild size="sm" variant="outline">
                  <a
                    href={`https://wa.me/${digitsOnly(lead.phone).replace('+', '')}`}
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    <MessageCircle className="size-4" />
                    WhatsApp
                  </a>
                </Button>
              )}
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label htmlFor="drawer-status">Status</Label>
                <Select value={lead.status} onValueChange={(value) => updateStatus(value as LeadStatus)}>
                  <SelectTrigger id="drawer-status">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {(Object.keys(STATUS_LABELS) as LeadStatus[]).map((status) => (
                      <SelectItem key={status} value={status}>
                        {STATUS_LABELS[status]}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="drawer-assign">Assigned to</Label>
                <Select value={lead.assigned_to ? String(lead.assigned_to) : 'unassigned'} onValueChange={assign}>
                  <SelectTrigger id="drawer-assign">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="unassigned">Unassigned</SelectItem>
                    {assignableUsers.map((user) => (
                      <SelectItem key={user.id} value={String(user.id)}>
                        {user.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>

            {lead.notes && (
              <div>
                <p className="text-ink-muted text-xs font-semibold tracking-wide uppercase">Original message</p>
                <p className="mt-1 text-sm whitespace-pre-line">{lead.notes}</p>
              </div>
            )}

            <div className="space-y-3">
              <p className="text-ink-muted text-xs font-semibold tracking-wide uppercase">Internal notes</p>
              <form onSubmit={submitNote} className="space-y-2">
                <Textarea
                  rows={2}
                  placeholder="Add a note..."
                  value={noteForm.data.body}
                  onChange={(e) => noteForm.setData('body', e.target.value)}
                />
                <Button type="submit" size="sm" disabled={noteForm.processing || !noteForm.data.body}>
                  Add note
                </Button>
              </form>
              <ul className="space-y-3">
                {lead.notes_timeline.map((note) => (
                  <li key={note.id} className="border-border-soft border-l-2 pl-3 text-sm">
                    <p>{note.body}</p>
                    <p className="text-ink-muted mt-1 text-xs">
                      {note.author} · {note.created_at ? new Date(note.created_at).toLocaleString() : ''}
                    </p>
                  </li>
                ))}
                {lead.notes_timeline.length === 0 && (
                  <p className="text-ink-muted text-sm">No internal notes yet.</p>
                )}
              </ul>
            </div>

            <Button
              type="button"
              variant="destructive"
              size="sm"
              onClick={() => {
                if (confirm(`Delete lead "${lead.name}"?`)) {
                  router.delete(`/admin/leads/${leadId}`, { onSuccess: onClose });
                }
              }}
            >
              <Trash2 className="size-4" />
              Delete lead
            </Button>
          </div>
        )}
      </SheetContent>
    </Sheet>
  );
}

export default function LeadsIndex({ leads, sources, statuses, stats, filters }: LeadsIndexPageProps) {
  const [view, setView] = React.useState<'kanban' | 'list'>('kanban');
  const [openLeadId, setOpenLeadId] = React.useState<number | null>(null);

  function moveLead(leadId: number, status: LeadStatus) {
    router.patch(`/admin/leads/${leadId}/status`, { status }, { preserveScroll: true });
  }

  return (
    <>
      <Head title="Leads" />
      <div className="space-y-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 className="font-display text-ink text-2xl font-medium">Leads</h1>
            <p className="text-ink-muted text-sm">
              {stats.total} total · {stats.converted} converted · {stats.conversion_rate}% conversion rate
            </p>
          </div>
          <div className="flex gap-1">
            <Button
              type="button"
              variant={view === 'kanban' ? 'accent' : 'outline'}
              size="icon"
              aria-label="Kanban view"
              onClick={() => setView('kanban')}
            >
              <LayoutGrid className="size-4" />
            </Button>
            <Button
              type="button"
              variant={view === 'list' ? 'accent' : 'outline'}
              size="icon"
              aria-label="List view"
              onClick={() => setView('list')}
            >
              <List className="size-4" />
            </Button>
          </div>
        </div>

        <FiltersBar filters={filters} sources={sources} statuses={statuses} />

        {view === 'kanban' ? (
          <KanbanBoard leads={leads} statuses={statuses} onOpen={(lead) => setOpenLeadId(lead.id)} onMove={moveLead} />
        ) : (
          <LeadTable leads={leads} onOpen={(lead) => setOpenLeadId(lead.id)} />
        )}
      </div>

      {openLeadId && (
        <LeadDrawer leadId={openLeadId} assignableUsersFallback={[]} onClose={() => setOpenLeadId(null)} />
      )}
    </>
  );
}

LeadsIndex.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
