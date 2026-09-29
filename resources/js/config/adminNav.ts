import {
  BarChart3,
  Bell,
  Calendar,
  ClipboardList,
  Clock,
  FileText,
  FileUp,
  Gift,
  History,
  Image as ImageIcon,
  Images,
  LayoutDashboard,
  LayoutList,
  Library,
  MessageSquare,
  Percent,
  Settings,
  ShieldCheck,
  ShoppingCart,
  Star,
  Tags,
  Users,
  type LucideIcon,
} from 'lucide-react';

export interface AdminNavItem {
  label: string;
  href: string;
  icon: LucideIcon;
  /** Any one of these permissions is enough to see the item. `null` = always visible (e.g. Dashboard). */
  permissions: string[] | null;
}

export interface AdminNavSection {
  label: string;
  items: AdminNavItem[];
}

/**
 * One source of truth for both the sidebar and the command palette (Phase 5), so the two never
 * drift out of sync. Permissions map to the 14 permissions in `RolesAndPermissionsSeeder.php` per
 * Brief §3's module list — an item with `permissions: null` is visible to anyone who reached
 * `/admin` at all (already gated by the `role` route middleware, audit fix 2026-08-26).
 */
export const ADMIN_NAV_SECTIONS: AdminNavSection[] = [
  {
    label: 'Overview',
    items: [{ label: 'Dashboard', href: '/admin', icon: LayoutDashboard, permissions: null }],
  },
  {
    label: 'Bookings',
    items: [
      {
        label: 'Bookings',
        href: '/admin/bookings',
        icon: Calendar,
        permissions: ['bookings.view', 'bookings.manage', 'bookings.view_own'],
      },
      {
        label: 'Availability',
        href: '/admin/availability',
        icon: ClipboardList,
        permissions: ['staff.manage', 'settings.manage'],
      },
    ],
  },
  {
    label: 'Sales',
    items: [
      { label: 'POS', href: '/admin/pos', icon: ShoppingCart, permissions: ['pos.sell'] },
      { label: 'New Sale', href: '/admin/pos/terminal', icon: ShoppingCart, permissions: ['pos.sell'] },
      { label: 'Held Sales', href: '/admin/pos/held-sales', icon: Clock, permissions: ['pos.sell'] },
      {
        label: 'Sales History',
        href: '/admin/pos/sales-history',
        icon: History,
        permissions: ['pos.sell', 'pos.refund', 'reports.view'],
      },
      { label: 'Reports', href: '/admin/reports', icon: BarChart3, permissions: ['reports.view'] },
    ],
  },
  {
    label: 'Catalog',
    items: [
      {
        label: 'Categories',
        href: '/admin/service-categories',
        icon: Tags,
        permissions: ['catalog.manage'],
      },
      {
        label: 'Services',
        href: '/admin/services',
        icon: LayoutList,
        permissions: ['catalog.manage'],
      },
      { label: 'Deals', href: '/admin/deals', icon: Percent, permissions: ['catalog.manage'] },
      { label: 'Pricing', href: '/admin/pricing', icon: Tags, permissions: ['catalog.manage'] },
      {
        label: 'Import Services',
        href: '/admin/services-import',
        icon: FileUp,
        permissions: ['catalog.manage'],
      },
    ],
  },
  {
    label: 'CRM',
    items: [
      { label: 'Leads', href: '/admin/leads', icon: Users, permissions: ['crm.manage'] },
      {
        label: 'Messages',
        href: '/admin/messages',
        icon: MessageSquare,
        permissions: ['crm.manage'],
      },
      { label: 'Reviews', href: '/admin/reviews', icon: Star, permissions: ['crm.manage'] },
      { label: 'Customers', href: '/admin/customers', icon: Users, permissions: ['crm.manage'] },
    ],
  },
  {
    label: 'Content',
    items: [
      { label: 'Blog', href: '/admin/blog', icon: FileText, permissions: ['cms.manage'] },
      { label: 'Gallery', href: '/admin/gallery', icon: Images, permissions: ['cms.manage'] },
      { label: 'Hero Slider', href: '/admin/slider', icon: ImageIcon, permissions: ['cms.manage'] },
      { label: 'Media Library', href: '/admin/media', icon: Library, permissions: ['cms.manage'] },
    ],
  },
  {
    label: 'System',
    items: [
      {
        label: 'Reminders',
        href: '/admin/reminders',
        icon: Bell,
        permissions: ['settings.manage'],
      },
      {
        label: 'Gift Offers',
        href: '/admin/deals#offers',
        icon: Gift,
        permissions: ['catalog.manage'],
      },
      { label: 'Users & Roles', href: '/admin/users', icon: Users, permissions: ['users.manage'] },
      {
        label: 'Settings',
        href: '/admin/settings',
        icon: Settings,
        permissions: ['settings.view', 'settings.manage'],
      },
      {
        label: 'Audit Log',
        href: '/admin/audit-log',
        icon: ShieldCheck,
        permissions: ['audit.view'],
      },
      {
        label: 'Notification Logs',
        href: '/admin/notification-logs',
        icon: Bell,
        permissions: ['reports.view', 'settings.view'],
      },
    ],
  },
];

export function canSeeNavItem(item: AdminNavItem, userPermissions: readonly string[]): boolean {
  if (item.permissions === null) {
    return true;
  }

  return item.permissions.some((permission) => userPermissions.includes(permission));
}

export function visibleAdminNavSections(userPermissions: readonly string[]): AdminNavSection[] {
  return ADMIN_NAV_SECTIONS.map((section) => ({
    ...section,
    items: section.items.filter((item) => canSeeNavItem(item, userPermissions)),
  })).filter((section) => section.items.length > 0);
}
