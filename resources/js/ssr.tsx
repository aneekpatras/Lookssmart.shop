import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ReactDOMServer from 'react-dom/server';

import { ErrorBoundary } from '@/Components/ErrorBoundary';

const appName = 'Looks Smart Beauty Salon';

createServer((page) =>
  createInertiaApp({
    page,
    render: ReactDOMServer.renderToString,
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name): Promise<ResolvedComponent> =>
      resolvePageComponent<ResolvedComponent>(
        `./Pages/${name}.tsx`,
        import.meta.glob<ResolvedComponent>('./Pages/**/*.tsx', { import: 'default' }),
      ),
    setup: ({ App, props }) => (
      <ErrorBoundary>
        <App {...props} />
      </ErrorBoundary>
    ),
  }),
);
