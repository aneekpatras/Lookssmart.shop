import { Head, router, useForm, usePage } from '@inertiajs/react';
import { BadgeCheck, CircleAlert, MoreHorizontal, UserPlus } from 'lucide-react';
import * as React from 'react';

import {
  DataTable,
  type DataTableColumn,
  type DataTablePaginationMeta,
} from '@/Components/admin/DataTable';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/Components/ui/dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import AdminLayout from '@/Layouts/AdminLayout';

interface AdminUserRow {
  id: number;
  name: string;
  email: string;
  roles: string[];
  email_verified: boolean;
  suspended: boolean;
  created_at: string | null;
}

interface UsersPageProps {
  users: {
    data: AdminUserRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
  filters: { search: string | null; sort: string; direction: 'asc' | 'desc' };
  assignableRoles: string[];
}

function InviteDialog({ assignableRoles }: { assignableRoles: string[] }) {
  const [open, setOpen] = React.useState(false);
  const { data, setData, post, processing, errors, reset } = useForm({
    email: '',
    role: assignableRoles[assignableRoles.length - 1] ?? 'staff',
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post('/admin/users/invite', {
      onSuccess: () => {
        setOpen(false);
        reset();
      },
    });
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button type="button">
          <UserPlus className="size-4" />
          Invite
        </Button>
      </DialogTrigger>
      <DialogContent>
        <form onSubmit={submit} className="space-y-4">
          <DialogHeader>
            <DialogTitle>Invite someone</DialogTitle>
            <DialogDescription>
              Sends a signed link, valid for 7 days, to set up their account.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-1.5">
            <Label htmlFor="invite-email">Email</Label>
            <Input
              id="invite-email"
              type="email"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              aria-invalid={!!errors.email}
              required
            />
            {errors.email ? <p className="text-sm text-red-600">{errors.email}</p> : null}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="invite-role">Role</Label>
            <Select value={data.role} onValueChange={(value) => setData('role', value)}>
              <SelectTrigger id="invite-role">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {assignableRoles.map((role) => (
                  <SelectItem key={role} value={role}>
                    {role.replace('-', ' ')}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <DialogFooter>
            <Button type="submit" disabled={processing}>
              Send invitation
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function UserRowActions({
  row,
  assignableRoles,
}: {
  row: AdminUserRow;
  assignableRoles: string[];
}) {
  const { props } = usePage<{ auth: { user: { id: number; roles: string[] } | null } }>();
  const currentUser = props.auth.user;
  const isSuperAdmin = currentUser?.roles.includes('super-admin') ?? false;
  const isSelf = currentUser?.id === row.id;

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button type="button" variant="ghost" size="icon" aria-label={`Actions for ${row.name}`}>
          <MoreHorizontal className="size-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuLabel>Change role</DropdownMenuLabel>
        {assignableRoles.map((role) => (
          <DropdownMenuItem
            key={role}
            disabled={row.roles.includes(role)}
            onSelect={() => router.put(`/admin/users/${row.id}/role`, { role })}
          >
            {role.replace('-', ' ')}
          </DropdownMenuItem>
        ))}
        <DropdownMenuSeparator />
        <DropdownMenuItem onSelect={() => router.post(`/admin/users/${row.id}/force-2fa`)}>
          Reset 2FA
        </DropdownMenuItem>
        {row.suspended ? (
          <DropdownMenuItem onSelect={() => router.post(`/admin/users/${row.id}/unsuspend`)}>
            Reinstate
          </DropdownMenuItem>
        ) : (
          <DropdownMenuItem
            disabled={isSelf}
            onSelect={() => router.post(`/admin/users/${row.id}/suspend`)}
            className="text-red-600 hover:bg-red-50 focus:bg-red-50"
          >
            Suspend
          </DropdownMenuItem>
        )}
        {isSuperAdmin && !isSelf && (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem onSelect={() => router.post(`/admin/users/${row.id}/impersonate`)}>
              Impersonate
            </DropdownMenuItem>
          </>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

export default function Users({ users, filters, assignableRoles }: UsersPageProps) {
  const columns: DataTableColumn<AdminUserRow>[] = [
    { id: 'name', header: 'Name', sortable: true, alwaysVisible: true, cell: (row) => row.name },
    { id: 'email', header: 'Email', sortable: true, cell: (row) => row.email },
    {
      id: 'roles',
      header: 'Roles',
      cell: (row) => (
        <div className="flex flex-wrap gap-1">
          {row.roles.length === 0 ? (
            <span className="text-ink-muted">—</span>
          ) : (
            row.roles.map((role) => (
              <Badge key={role} variant="accent">
                {role}
              </Badge>
            ))
          )}
          {row.suspended && <Badge variant="destructive">suspended</Badge>}
        </div>
      ),
    },
    {
      id: 'email_verified',
      header: 'Verified',
      cell: (row) =>
        row.email_verified ? (
          <BadgeCheck className="size-4 text-emerald-600" />
        ) : (
          <CircleAlert className="text-ink-muted size-4" />
        ),
    },
    {
      id: 'created_at',
      header: 'Joined',
      sortable: true,
      cell: (row) => (row.created_at ? new Date(row.created_at).toLocaleDateString() : '—'),
    },
    {
      id: 'actions',
      header: '',
      alwaysVisible: true,
      className: 'w-10 text-right',
      cell: (row) => <UserRowActions row={row} assignableRoles={assignableRoles} />,
    },
  ];

  return (
    <>
      <Head title="Users &amp; Roles" />
      <div className="space-y-6">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h1 className="font-display text-ink text-2xl font-medium">Users &amp; Roles</h1>
            <p className="text-ink-muted text-sm">
              Every account with admin-panel access — invite, roles, 2FA, and suspension.
            </p>
          </div>
          <InviteDialog assignableRoles={assignableRoles} />
        </div>
        <DataTable<AdminUserRow>
          columns={columns}
          rows={users.data}
          meta={users satisfies DataTablePaginationMeta}
          getRowId={(row) => row.id}
          filters={filters}
          searchPlaceholder="Search by name or email..."
          emptyTitle="No users found"
          emptyDescription="Try a different search."
          csvFilename="users.csv"
        />
      </div>
    </>
  );
}

Users.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
