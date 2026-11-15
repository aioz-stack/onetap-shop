import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from './lib/ThemeContext';

const appName = import.meta.env.VITE_APP_NAME || 'Onetap Marketplace';

createInertiaApp({
  title: (title) => (title ? `${title} | ${appName}` : appName),
  // Pages are code-split so each panel only downloads what it renders.
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.jsx');
    const page = pages[`./Pages/${name}.jsx`];

    if (!page) {
      throw new Error(`Inertia page not found: ${name}`);
    }

    return page();
  },
  setup({ el, App, props }) {
    createRoot(el).render(
      <ThemeProvider>
        <App {...props} />
      </ThemeProvider>,
    );
  },
  progress: {
    color: '#0f9d76',
  },
});
