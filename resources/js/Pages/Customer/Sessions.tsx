import { Head, useForm } from '@inertiajs/react';
import { Laptop, Smartphone } from 'lucide-react';
import * as React from 'react';

import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import PublicLayout from '@/Layouts/PublicLayout';

interface SessionRow {
  id: string;
  ip_address: string;
  user_agent: string | null;
  is_current_device: boolean;
  last_active: string;
}

function isMobileAgent(userAgent: string | null): boolean {
  return !!userAgent && /Mobile|Android|iPhone/i.test(userAgent);
}

function LogoutOtherDevicesDialog() {
  const [open, setOpen] = React.useState(false);
  const { data, setData, delete: destroy, processing, errors, reset } = useForm({ password: '' });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    destroy('/user/sessions/other', {
      onSuccess: () => {
        setOpen(false);
        reset();
      },
    });
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button variant="outline">Log out of all other devices</Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Log out of all other devices</DialogTitle>
          <DialogDescription>
            Enter your password to confirm. This will not log out your current device.
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={submit} className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="confirm_password">Password</Label>
            <Input
              id="confirm_password"
              type="password"
              value={data.password}
              onChange={(e) => setData('password', e.target.value)}
            />
            {errors.password ? <p className="text-sm text-red-600">{errors.password}</p> : null}
          </div>
          <DialogFooter>
            <Button type="submit" disabled={processing}>
              Confirm
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function Sessions({ sessions }: { sessions: SessionRow[] }) {
  return (
    <>
      <Head title="Active Sessions" />

      <div className="mx-auto max-w-2xl space-y-6 px-6 py-16">
        <div>
          <h1 className="font-display text-ink text-3xl font-medium">Active Sessions</h1>
          <p className="text-ink-muted mt-1 text-sm">
            This is a list of devices that have logged into your account.
          </p>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Devices</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {sessions.map((session) => {
              const Icon = isMobileAgent(session.user_agent) ? Smartphone : Laptop;

              return (
                <div key={session.id} className="flex items-center gap-3">
                  <Icon className="text-ink-muted size-5" />
                  <div className="flex-1">
                    <p className="text-ink text-sm">
                      {session.ip_address}
                      {session.is_current_device ? (
                        <Badge variant="accent" className="ml-2">
                          This device
                        </Badge>
                      ) : null}
                    </p>
                    <p className="text-ink-muted text-xs">Last active {session.last_active}</p>
                  </div>
                </div>
              );
            })}
          </CardContent>
        </Card>

        <LogoutOtherDevicesDialog />
      </div>
    </>
  );
}

Sessions.layout = (page: React.ReactNode) => <PublicLayout>{page}</PublicLayout>;
