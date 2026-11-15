import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { IconSearch } from '../Icons';

/**
 * Debounced search box that updates the `param` query string on the current URL,
 * keeping other filters intact. The server does the actual filtering.
 */
export default function SearchInput({ value = '', param = 'search', placeholder = 'Search…', className = '' }) {
  const [term, setTerm] = useState(value ?? '');
  const firstRender = useRef(true);

  useEffect(() => {
    if (firstRender.current) {
      firstRender.current = false;

      return undefined;
    }

    const timer = setTimeout(() => {
      const params = new URLSearchParams(window.location.search);
      term ? params.set(param, term) : params.delete(param);
      params.delete('page');
      router.get(`${window.location.pathname}?${params.toString()}`, {}, { preserveState: true, preserveScroll: true, replace: true });
    }, 350);

    return () => clearTimeout(timer);
  }, [term, param]);

  return (
    <div className={`relative ${className}`}>
      <span className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2">
        <IconSearch className="h-4 w-4 fill-gray-400" />
      </span>
      <input
        type="search"
        value={term}
        onChange={(event) => setTerm(event.target.value)}
        placeholder={placeholder}
        className="h-10 w-full rounded-lg border border-gray-300 bg-transparent pl-10 pr-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
      />
    </div>
  );
}
