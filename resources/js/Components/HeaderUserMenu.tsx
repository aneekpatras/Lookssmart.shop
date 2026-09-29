import { Link, router } from '@inertiajs/react';
import { CalendarCheck, LogOut, Settings, UserRound } from 'lucide-react';
import * as React from 'react';

import { HeaderAuthModal } from '@/Components/HeaderAuthModal';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

interface HeaderUserMenuProps {
  user: { name: string; email: string } | null;
}

function initials(name: string): string {
  const parts = name.trim().split(/\s+/);
  return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || 'U';
}

/**
 * The header's user account icon — logged out, it opens `HeaderAuthModal`; logged in, it opens a
 * real dropdown (Radix, same primitive `AdminLayout`'s own user menu already uses) with the
 * account's name/email, a link to `/my-bookings`, `/my-account` for profile settings, and a real
 * `POST /logout`.
 */
export function HeaderUserMenu({ user }: HeaderUserMenuProps) {
  const [authModalOpen, setAuthModalOpen] = React.useState(false);

  if (!user) {
    return (
      <>
        <button
          type="button"
          aria-label="Log in or create an account"
          onClick={() => setAuthModalOpen(true)}
          className="border-border-soft text-ink hover:border-accent-500 flex size-9 items-center justify-center rounded-full border"
        >
          <UserRound className="size-4" aria-hidden="true" />
        </button>
        <HeaderAuthModal open={authModalOpen} onOpenChange={setAuthModalOpen} />
      </>
    );
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button type="button" aria-label="Your account" className="rounded-full">
          <Avatar>
            <AvatarFallback>{initials(user.name)}</AvatarFallback>
          </Avatar>
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-64">
        <DropdownMenuLabel className="flex flex-col gap-0.5 font-normal">
          <span className="text-ink text-sm font-medium">{user.name}</span>
          <span className="text-ink-muted text-xs">{user.email}</span>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem asChild>
          <Link href="/my-bookings">
            <CalendarCheck className="size-4" aria-hidden="true" /> My Bookings
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href="/my-account">
            <Settings className="size-4" aria-hidden="true" /> Profile Settings
          </Link>
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem onSelect={() => router.post('/logout')}>
          <LogOut className="size-4" aria-hidden="true" /> Log out
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
