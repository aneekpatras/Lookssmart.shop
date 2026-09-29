import '../css/app.css';

import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import * as Sentry from '@sentry/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot, hydrateRoot } from 'react-dom/client';

import { ErrorBoundary } from '@/Components/ErrorBoundary';

const appName = import.meta.env.VITE_APP_NAME || 'Looks Smart Beauty Salon';

// Browser error tracking — only enabled when a DSN is configured and we're actually in production,
// mirroring the backend's sentry/sentry-laravel setup. A blank VITE_SENTRY_DSN (the local/dev
// default) leaves this a no-op, so nothing is ever sent from a developer's machine.
if (import.meta.env.VITE_SENTRY_DSN && import.meta.env.VITE_APP_ENV === 'production') {
  Sentry.init({
    dsn: import.meta.env.VITE_SENTRY_DSN,
    environment: import.meta.env.VITE_APP_ENV,
    tracesSampleRate: 0.1,
  });
}

createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  resolve: (name): Promise<ResolvedComponent> =>
    resolvePageComponent<ResolvedComponent>(
      `./Pages/${name}.tsx`,
      import.meta.glob<ResolvedComponent>('./Pages/**/*.tsx', { import: 'default' }),
    ),
  setup({ el, App, props }) {
    const app = (
      <ErrorBoundary>
        <App {...props} />
      </ErrorBoundary>
    );

    if (import.meta.env.SSR) {
      hydrateRoot(el, app);
      return;
    }

    createRoot(el).render(app);
  },
  progress: {
    color: '#c9a66b',
  },
});
