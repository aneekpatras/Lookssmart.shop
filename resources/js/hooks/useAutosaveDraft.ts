import * as React from 'react';

/**
 * Optional autosave draft for `ResourceForm` (Phase 5 spec) — debounced localStorage persistence
 * per form, keyed so different resources/records don't collide. Loading a draft is an explicit,
 * separate call (`loadDraft`), never automatic on mount: a page decides for itself whether "restore
 * my draft?" is the right UX for that resource, rather than this hook silently overwriting fresh
 * server data with a stale local one.
 */
export function useAutosaveDraft<T>(key: string, data: T, enabled: boolean, debounceMs = 1000) {
  const timeoutRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);

  React.useEffect(() => {
    if (!enabled) {
      return;
    }

    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
    }

    timeoutRef.current = setTimeout(() => {
      try {
        localStorage.setItem(`draft:${key}`, JSON.stringify(data));
      } catch {
        // Private browsing / storage full — a lost draft is a minor inconvenience, not worth
        // surfacing an error over.
      }
    }, debounceMs);

    return () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
      }
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- intentionally keyed on serialized data, not the object identity
  }, [key, enabled, debounceMs, JSON.stringify(data)]);

  const loadDraft = React.useCallback((): T | null => {
    try {
      const raw = localStorage.getItem(`draft:${key}`);
      return raw ? (JSON.parse(raw) as T) : null;
    } catch {
      return null;
    }
  }, [key]);

  const clearDraft = React.useCallback(() => {
    try {
      localStorage.removeItem(`draft:${key}`);
    } catch {
      // Nothing to clean up if storage isn't available in the first place.
    }
  }, [key]);

  return { loadDraft, clearDraft };
}
