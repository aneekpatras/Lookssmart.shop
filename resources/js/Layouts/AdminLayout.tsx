import { Link, router, usePage } from '@inertiajs/react';
import { Bell, ChevronsLeft, ChevronsRight, LogOut, Moon, Search, Sun, User } from 'lucide-react';
import * as React from 'react';

import { CommandPalette } from '@/Components/admin/CommandPalette';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Toaster } from '@/Components/ui/toaster';
import { visibleAdminNavSections } from '@/config/adminNav';
import { useDarkMode } from '@/hooks/useDarkMode';

interface PageProps {
  [key: string]: unknown;
  auth: {
    user: {
      id: number;
      name: string;
      email: string;
      roles: string[];
      permissions: string[];
    } | null;
    impersonating: { by: string | null } | null;
  };
}

function ImpersonationBanner({ by }: { by: string | null }) {
  return (
    <div className="flex items-center justify-center gap-3 bg-amber-500 px-4 py-2 text-sm font-medium text-amber-950">
      <span>You&apos;re impersonating this account{by ? ` — logged in as ${by}` : ''}.</span>
      <button
        type="button"
        onClick={() => router.post('/admin/impersonate/stop')}
        className="rounded-md bg-amber-950/10 px-2.5 py-1 font-semibold hover:bg-amber-950/20"
      >
        Exit impersonation
      </button>
    </div>
  );
}

function initials(name: string): string {
  return name
    .split(' ')
    .map((part) => part[0])
    .filter(Boolean)
    .slice(0, 2)
    .join('')
    .toUpperCase();
}

function Sidebar({
  collapsed,
  permissions,
}: {
  collapsed: boolean;
  permissions: readonly string[];
}) {
  const { url } = usePage();
  const sections = visibleAdminNavSections(permissions);

  return (
    <aside
      className={`border-border-soft bg-surface hidden shrink-0 border-r transition-[width] duration-200 md:block ${
        collapsed ? 'w-16' : 'w-64'
      }`}
    >
      <div className="border-border-soft flex h-16 items-center border-b px-4">
        <Link href="/admin" className="font-display text-ink truncate text-lg font-medium">
          {collapsed ? 'LS' : 'Looks Smart'}
        </Link>
      </div>
      <nav className="space-y-6 overflow-y-auto p-3">
        {sections.map((section) => (
          <div key={section.label}>
            {!collapsed && (
              <p className="text-ink-muted px-2 text-xs font-semibold tracking-wide uppercase">
                {section.label}
              </p>
            )}
            <ul className="mt-2 space-y-0.5">
              {section.items.map((item) => {
                const Icon = item.icon;
                const base = item.href.split('#')[0] ?? item.href;
                const active = url.startsWith(base);

                return (
                  <li key={item.href}>
                    <Link
                      href={item.href}
                      title={collapsed ? item.label : undefined}
                      className={`motion-safe-transition flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm transition-colors ${
                        active
                          ? 'bg-accent-50 text-accent-700'
                          : 'text-ink-muted hover:bg-accent-50/60 hover:text-ink'
                      }`}
                    >
                      <Icon className="size-4 shrink-0" />
                      {!collapsed && item.label}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </div>
        ))}
      </nav>
    </aside>
  );
}

function Breadcrumbs() {
  const { url } = usePage();
  const path = url.split('?')[0] ?? url;
  const segments = path.split('/').filter(Boolean).slice(1); // drop leading "admin"

  return (
    <nav aria-label="Breadcrumb" className="text-ink-muted text-sm">
      <ol className="flex items-center gap-1.5">
        <li>
          <Link href="/admin" className="hover:text-ink">
            Admin
          </Link>
        </li>
        {segments.map((segment, index) => (
          <li key={index} className="flex items-center gap-1.5 capitalize">
            <span>/</span>
            <span>{segment.replace(/-/g, ' ')}</span>
          </li>
        ))}
      </ol>
    </nav>
  );
}

function Topbar({
  collapsed,
  onToggleCollapsed,
  onOpenPalette,
}: {
  collapsed: boolean;
  onToggleCollapsed: () => void;
  onOpenPalette: () => void;
}) {
  const { props } = usePage<PageProps>();
  const [isDark, toggleDark] = useDarkMode();
  const user = props.auth.user;

  return (
    <header className="border-border-soft bg-surface flex h-16 items-center justify-between border-b px-6">
      <div className="flex items-center gap-4">
        <button
          type="button"
          onClick={onToggleCollapsed}
          className="text-ink-muted hover:bg-accent-50 hover:text-ink hidden rounded-md p-2 md:block"
          aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
        >
          {collapsed ? <ChevronsRight className="size-4" /> : <ChevronsLeft className="size-4" />}
        </button>
        <Breadcrumbs />
      </div>
      <div className="flex items-center gap-2">
        <button
          type="button"
          onClick={onOpenPalette}
          className="text-ink-muted hover:bg-accent-50 hover:text-ink flex items-center gap-2 rounded-md px-3 py-2 text-sm"
        >
          <Search className="size-4" />
          <span className="hidden sm:inline">Search</span>
          <kbd className="border-border-soft bg-ivory hidden rounded border px-1.5 py-0.5 text-xs sm:inline">
            ⌘K
          </kbd>
        </button>
        <button
          type="button"
          onClick={toggleDark}
          className="text-ink-muted hover:bg-accent-50 hover:text-ink rounded-md p-2"
          aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
        >
          {isDark ? <Sun className="size-5" /> : <Moon className="size-5" />}
        </button>
        <button
          type="button"
          className="text-ink-muted hover:bg-accent-50 hover:text-ink rounded-md p-2"
          aria-label="Notifications"
        >
          <Bell className="size-5" />
        </button>
        {user && (
          <DropdownMenu>
            <DropdownMenuTrigger className="focus-visible:ring-accent-500 ml-1 rounded-full focus-visible:ring-2 focus-visible:outline-none">
              <Avatar>
                <AvatarFallback>{initials(user.name)}</AvatarFallback>
              </Avatar>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuLabel>
                <p className="text-ink truncate font-medium">{user.name}</p>
                <p className="text-ink-muted truncate text-xs font-normal">{user.email}</p>
              </DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem asChild>
                <Link href="/my-account">
                  <User className="size-4" />
                  My profile
                </Link>
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem
                onSelect={() => router.post('/logout')}
                className="text-red-600 hover:bg-red-50 focus:bg-red-50"
              >
                <LogOut className="size-4" />
                Log out
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        )}
      </div>
    </header>
  );
}

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  const { props } = usePage<PageProps>();
  const permissions = props.auth.user?.permissions ?? [];
  const [collapsed, setCollapsed] = React.useState(false);
  const [paletteOpen, setPaletteOpen] = React.useState(false);

  return (
    <div className="bg-ivory flex min-h-screen flex-col">
      {props.auth.impersonating && <ImpersonationBanner by={props.auth.impersonating.by} />}
      <div className="flex flex-1">
        <Sidebar collapsed={collapsed} permissions={permissions} />
        <Toaster />
        <CommandPalette
          permissions={permissions}
          open={paletteOpen}
          onOpenChange={setPaletteOpen}
        />
        <div className="flex flex-1 flex-col">
          <Topbar
            collapsed={collapsed}
            onToggleCollapsed={() => setCollapsed((prev) => !prev)}
            onOpenPalette={() => setPaletteOpen(true)}
          />
          <main className="flex-1 p-6">{children}</main>
        </div>
      </div>
    </div>
  );
}
