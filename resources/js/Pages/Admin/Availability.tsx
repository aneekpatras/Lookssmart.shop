import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AdminLayout from '@/Layouts/AdminLayout';

const WEEKDAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

interface WorkingHourRow { id: number; weekday: number; start_time: string; end_time: string; }
interface TimeOffRow { id: number; starts_at: string | null; ends_at: string | null; reason: string | null; }
interface StaffRow { id: number; name: string; working_hours: WorkingHourRow[]; time_off: TimeOffRow[]; }
interface AvailabilityPageProps { staff: StaffRow[] }
interface PageProps { [key: string]: unknown; auth: { user: { permissions: string[] } | null } }

function StaffScheduleCard({ member, canManage }: { member: StaffRow; canManage: boolean }) {
  const hoursByWeekday = new Map(member.working_hours.map((hour) => [hour.weekday, hour]));
  const [draft, setDraft] = React.useState<Record<number, { start_time: string; end_time: string }>>({});
  const [timeOffForm, setTimeOffForm] = React.useState({ starts_at: '', ends_at: '', reason: '' });

  function saveWorkingHour(weekday: number) {
    const existing = hoursByWeekday.get(weekday);
    const values = draft[weekday] ?? { start_time: existing?.start_time ?? '09:00', end_time: existing?.end_time ?? '17:00' };

    router.post(
      '/admin/availability/working-hours',
      { staff_id: member.id, weekday, ...values },
      { preserveScroll: true, onSuccess: () => toast.success('Working hours saved.') },
    );
  }

  function removeWorkingHour(id: number) {
    router.delete(`/admin/availability/working-hours/${id}`, { preserveScroll: true });
  }

  function addTimeOff(event: React.FormEvent) {
    event.preventDefault();
    router.post(
      '/admin/availability/time-off',
      { staff_id: member.id, ...timeOffForm },
      {
        preserveScroll: true,
        onSuccess: () => { toast.success('Time off added.'); setTimeOffForm({ starts_at: '', ends_at: '', reason: '' }); },
      },
    );
  }

  function removeTimeOff(id: number) {
    router.delete(`/admin/availability/time-off/${id}`, { preserveScroll: true });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{member.name}</CardTitle>
      </CardHeader>
      <CardContent className="space-y-6">
        <div>
          <p className="text-ink-muted mb-2 text-xs font-semibold uppercase tracking-wide">Weekly hours</p>
          <div className="space-y-2">
            {WEEKDAY_NAMES.map((label, weekday) => {
              const existing = hoursByWeekday.get(weekday);
              const values = draft[weekday] ?? { start_time: existing?.start_time ?? '', end_time: existing?.end_time ?? '' };

              return (
                <div key={weekday} className="flex items-center gap-2 text-sm">
                  <span className="w-24 shrink-0">{label}</span>
                  {canManage ? (
                    <>
                      <Input
                        type="time"
                        value={values.start_time}
                        onChange={(event) => setDraft((prev) => ({ ...prev, [weekday]: { ...values, start_time: event.target.value } }))}
                        className="w-32"
                      />
                      <span>–</span>
                      <Input
                        type="time"
                        value={values.end_time}
                        onChange={(event) => setDraft((prev) => ({ ...prev, [weekday]: { ...values, end_time: event.target.value } }))}
                        className="w-32"
                      />
                      <Button type="button" size="sm" variant="outline" onClick={() => saveWorkingHour(weekday)}>
                        Save
                      </Button>
                      {existing && (
                        <Button type="button" size="icon" variant="ghost" aria-label={`Clear ${label} hours`} onClick={() => removeWorkingHour(existing.id)}>
                          <Trash2 className="size-4" />
                        </Button>
                      )}
                    </>
                  ) : (
                    <span className="text-ink-muted">{existing ? `${existing.start_time} – ${existing.end_time}` : 'Off'}</span>
                  )}
                </div>
              );
            })}
          </div>
        </div>

        <div>
          <p className="text-ink-muted mb-2 text-xs font-semibold uppercase tracking-wide">Time off</p>
          <div className="space-y-1.5">
            {member.time_off.length === 0 ? (
              <p className="text-ink-muted text-sm">No upcoming time off.</p>
            ) : (
              member.time_off.map((entry) => (
                <div key={entry.id} className="flex items-center justify-between gap-2 rounded border px-3 py-1.5 text-sm">
                  <span>
                    {entry.starts_at ? new Date(entry.starts_at).toLocaleDateString() : '—'}
                    {' – '}
                    {entry.ends_at ? new Date(entry.ends_at).toLocaleDateString() : '—'}
                    {entry.reason ? ` · ${entry.reason}` : ''}
                  </span>
                  {canManage && (
                    <Button type="button" size="icon" variant="ghost" aria-label="Remove time off" onClick={() => removeTimeOff(entry.id)}>
                      <Trash2 className="size-4" />
                    </Button>
                  )}
                </div>
              ))
            )}
          </div>
          {canManage && (
            <form onSubmit={addTimeOff} className="mt-3 flex flex-wrap items-end gap-2">
              <div className="space-y-1.5">
                <Label htmlFor={`time-off-start-${member.id}`}>From</Label>
                <Input
                  id={`time-off-start-${member.id}`}
                  type="date"
                  required
                  value={timeOffForm.starts_at}
                  onChange={(event) => setTimeOffForm((prev) => ({ ...prev, starts_at: event.target.value }))}
                  className="w-40"
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor={`time-off-end-${member.id}`}>To</Label>
                <Input
                  id={`time-off-end-${member.id}`}
                  type="date"
                  required
                  value={timeOffForm.ends_at}
                  onChange={(event) => setTimeOffForm((prev) => ({ ...prev, ends_at: event.target.value }))}
                  className="w-40"
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor={`time-off-reason-${member.id}`}>Reason (optional)</Label>
                <Input
                  id={`time-off-reason-${member.id}`}
                  value={timeOffForm.reason}
                  onChange={(event) => setTimeOffForm((prev) => ({ ...prev, reason: event.target.value }))}
                  className="w-48"
                />
              </div>
              <Button type="submit" size="sm">
                <Plus className="size-4" /> Add
              </Button>
            </form>
          )}
        </div>
      </CardContent>
    </Card>
  );
}

export default function Availability({ staff }: AvailabilityPageProps) {
  const { props } = usePage<PageProps>();
  const canManage = (props.auth.user?.permissions ?? []).includes('staff.manage');

  return (
    <>
      <Head title="Booking Slots & Availability" />
      <div className="space-y-6">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">Availability</h1>
          <p className="text-ink-muted text-sm">
            Per-staff working hours and time off. Business-wide operating hours are managed under{' '}
            <Link href="/admin/settings" className="underline">
              Settings → Business Hours
            </Link>
            .
          </p>
        </div>

        {staff.length === 0 ? (
          <p className="text-ink-muted text-sm">No active staff members yet.</p>
        ) : (
          <div className="grid gap-6 lg:grid-cols-2">
            {staff.map((member) => (
              <StaffScheduleCard key={member.id} member={member} canManage={canManage} />
            ))}
          </div>
        )}
      </div>
    </>
  );
}

Availability.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
