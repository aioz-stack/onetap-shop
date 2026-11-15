import { router } from '@inertiajs/react';

/**
 * Builds a URL for `path` from the current query string with `updates` applied.
 * Empty values are removed and pagination resets, so filters always start on page 1.
 */
export function withQuery(updates, path) {
  const params = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');

  Object.entries(updates).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '') {
      params.delete(key);
    } else {
      params.set(key, value);
    }
  });

  params.delete('page');
  const base = path ?? (typeof window !== 'undefined' ? window.location.pathname : '/');
  const query = params.toString();

  return query ? `${base}?${query}` : base;
}

export function applyFilters(updates) {
  router.get(withQuery(updates), {}, { preserveState: true, preserveScroll: true, replace: true });
}
