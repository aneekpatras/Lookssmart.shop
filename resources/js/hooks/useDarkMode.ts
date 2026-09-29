import * as React from 'react';

const STORAGE_KEY = 'admin-theme';

/**
 * Scoped to the admin dashboard (Phase 5) — toggles `.dark` on <html> while an AdminLayout is
 * mounted, and removes it on unmount so navigating back to the public site (dark mode is Phase 13
 * there, and opt-in, not tied to this preference) never inherits an admin's choice. Persisted per
 * browser via localStorage, not per-user in the database — a "nice to have" UI preference, not
 * something that needs to sync across devices.
 */
export function useDarkMode(): [boolean, () => void] {
  const [isDark, setIsDark] = React.useState<boolean>(() => {
    try {
      return localStorage.getItem(STORAGE_KEY) === 'dark';
    } catch {
      return false;
    }
  });

  React.useEffect(() => {
    document.documentElement.classList.toggle('dark', isDark);

    return () => {
      document.documentElement.classList.remove('dark');
    };
  }, [isDark]);

  const toggle = React.useCallback(() => {
    setIsDark((prev) => {
      const next = !prev;

      try {
        localStorage.setItem(STORAGE_KEY, next ? 'dark' : 'light');
      } catch {
        // Private browsing / storage disabled — the toggle still works for the session, just
        // doesn't persist. Not worth surfacing an error for a cosmetic preference.
      }

      return next;
    });
  }, []);

  return [isDark, toggle];
}
