import { router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useRef, useState } from 'react';
import AppIcon from './AppIcon';
import { formatDateTime } from '../lib/format';

/**
 * Header bell for the back-office panels and the rider app: unread badge plus the latest alerts.
 */
export default function NotificationBell({ align = 'right' }) {
  const { counts } = usePage().props;
  const [open, setOpen] = useState(false);
  const [state, setState] = useState({ loading: false, items: [], error: false });
  const ref = useRef(null);
  const unread = counts?.notifications ?? 0;

  useEffect(() => {
    if (!open) {
      return undefined;
    }

    setState((current) => ({ ...current, loading: true, error: false }));
    axios
      .get('/notifications/feed')
      .then(({ data }) => setState({ loading: false, items: data.data, error: false }))
      .catch(() => setState({ loading: false, items: [], error: true }));

    const close = (event) => {
      if (ref.current && !ref.current.contains(event.target)) {
        setOpen(false);
      }
    };
    const onKey = (event) => event.key === 'Escape' && setOpen(false);
    document.addEventListener('mousedown', close);
    document.addEventListener('keydown', onKey);

    return () => {
      document.removeEventListener('mousedown', close);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  const readAll = () => router.post('/notifications/read-all', {}, { preserveScroll: true, onSuccess: () => setState((current) => ({ ...current, items: current.items.map((item) => ({ ...item, read: true })) })) });

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        aria-label={unread ? `Notifications, ${unread} unread` : 'Notifications'}
        aria-expanded={open}
        className="relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 text-gray-500 hover:bg-gray-100 lg:h-11 lg:w-11 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800"
      >
        <AppIcon name="bell" className="h-5 w-5" />
        {unread > 0 && (
          <span className="absolute -right-0.5 -top-0.5 min-w-4.5 rounded-full bg-error-500 px-1 text-center text-[10px] font-semibold leading-4.5 text-white">{unread > 99 ? '99+' : unread}</span>
        )}
      </button>

      {open && (
        <div className={`fixed inset-x-3 top-16 z-999999 max-h-[70vh] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-lg sm:absolute sm:inset-x-auto sm:top-full sm:mt-2 sm:w-96 dark:border-gray-800 dark:bg-gray-900 ${align === 'right' ? 'sm:right-0' : 'sm:left-0'}`}>
          <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
            <p className="text-sm font-semibold text-gray-800 dark:text-white/90">Notifications</p>
            {unread > 0 && <button type="button" onClick={readAll} className="text-xs font-medium text-brand-600 dark:text-brand-400">Mark all read</button>}
          </div>
          <div className="max-h-[60vh] overflow-y-auto">
            {state.loading ? (
              <p className="px-4 py-6 text-center text-sm text-gray-500">Loading…</p>
            ) : state.error ? (
              <p className="px-4 py-6 text-center text-sm text-error-600">Could not load notifications.</p>
            ) : state.items.length ? (
              <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                {state.items.map((item) => (
                  <li key={item.id}>
                    <a href={`/notifications/${item.id}/open`} className={`block px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/5 ${item.read ? '' : 'bg-brand-50/60 dark:bg-brand-500/5'}`}>
                      <span className="flex items-start gap-2">
                        {!item.read && <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-500" aria-label="Unread" />}
                        <span className="min-w-0">
                          <span className="block text-sm font-medium text-gray-800 dark:text-white/90">{item.title}</span>
                          <span className="block text-xs text-gray-600 dark:text-gray-400">{item.body}</span>
                          <span className="mt-1 block text-[11px] text-gray-400">{formatDateTime(item.created_at)}</span>
                        </span>
                      </span>
                    </a>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="px-4 py-6 text-center text-sm text-gray-500">You're all caught up.</p>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
