import { router } from '@inertiajs/react';
import { Command } from 'cmdk';
import { Search } from 'lucide-react';
import * as React from 'react';

import { visibleAdminNavSections } from '@/config/adminNav';

/**
 * cmd+K (ctrl+K on non-Mac) jump-to destination palette (Phase 5 spec). Doubles as the phase's
 * "global search" item for now — it only searches the nav destinations a user can actually reach
 * (same permission filter as the sidebar), not real entity data, since no searchable admin entity
 * exists until Phase 6+. Extending it to search real records is a natural follow-up once those
 * endpoints exist, not a gap in this component's own scope.
 */
interface CommandPaletteProps {
  permissions: readonly string[];
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function CommandPalette({ permissions, open, onOpenChange }: CommandPaletteProps) {
  React.useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      if (event.key === 'k' && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        onOpenChange(!open);
      }
    }

    document.addEventListener('keydown', onKeyDown);

    return () => document.removeEventListener('keydown', onKeyDown);
  }, [open, onOpenChange]);

  const sections = visibleAdminNavSections(permissions);

  function go(href: string) {
    onOpenChange(false);
    router.visit(href);
  }

  return (
    <Command.Dialog
      open={open}
      onOpenChange={onOpenChange}
      label="Command palette"
      className="glass shadow-glass fixed top-24 left-1/2 z-50 w-full max-w-lg -translate-x-1/2 overflow-hidden rounded-xl p-0"
    >
      <div className="border-border-soft flex items-center gap-2 border-b px-4">
        <Search className="text-ink-muted size-4 shrink-0" />
        <Command.Input
          placeholder="Jump to..."
          className="text-ink placeholder:text-ink-muted h-12 w-full bg-transparent text-sm outline-none"
        />
      </div>
      <Command.List className="max-h-80 overflow-y-auto p-2">
        <Command.Empty className="text-ink-muted px-2 py-6 text-center text-sm">
          No matching page.
        </Command.Empty>
        {sections.map((section) => (
          <Command.Group
            key={section.label}
            heading={section.label}
            className="text-ink-muted [&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:py-1.5 [&_[cmdk-group-heading]]:text-xs [&_[cmdk-group-heading]]:font-semibold"
          >
            {section.items.map((item) => (
              <Command.Item
                key={item.href}
                value={`${section.label} ${item.label}`}
                onSelect={() => go(item.href)}
                className="text-ink hover:bg-accent-50 data-[selected=true]:bg-accent-50 flex cursor-pointer items-center gap-2.5 rounded-md px-2.5 py-2 text-sm"
              >
                <item.icon className="size-4" />
                {item.label}
              </Command.Item>
            ))}
          </Command.Group>
        ))}
      </Command.List>
    </Command.Dialog>
  );
}
