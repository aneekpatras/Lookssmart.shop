import { router } from '@inertiajs/react';
import * as React from 'react';

/**
 * Guards against losing unsaved form input two ways: a browser-level `beforeunload` prompt (tab
 * close, refresh, typed URL) and an Inertia-level intercept (clicking any in-app `<Link>` or calling
 * `router.visit`) via the `before` event, which can cancel a pending visit by returning `false`.
 * Neither mechanism alone covers both cases — Inertia navigations never trigger `beforeunload`
 * since the page never actually unloads.
 *
 * The form's own `useForm().post()/put()` call is itself an Inertia visit fired while the form is
 * still "dirty" (Inertia only clears dirty after the round trip completes), so without an escape
 * hatch this guard would intercept the Save action too. Callers must invoke the returned
 * `allowNextVisit()` synchronously right before triggering that visit (see `ResourceForm`, which
 * does this for every consumer) so the very next `before` event is let through untouched.
 */
export function useUnsavedChangesGuard(
  isDirty: boolean,
  message = 'You have unsaved changes. Leave anyway?',
) {
  const allowNextRef = React.useRef(false);

  React.useEffect(() => {
    function onBeforeUnload(event: BeforeUnloadEvent) {
      if (!isDirty) {
        return;
      }

      event.preventDefault();
    }

    window.addEventListener('beforeunload', onBeforeUnload);

    const removeInertiaListener = router.on('before', () => {
      if (allowNextRef.current) {
        allowNextRef.current = false;
        return true;
      }

      if (!isDirty) {
        return true;
      }

      return window.confirm(message);
    });

    return () => {
      window.removeEventListener('beforeunload', onBeforeUnload);
      removeInertiaListener();
    };
  }, [isDirty, message]);

  return React.useCallback(() => {
    allowNextRef.current = true;
  }, []);
}
