import { useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import useClickOutside from '../lib/useClickOutside';
import { initials } from '../lib/format';
import AppIcon from './AppIcon';
import { IconChevronDown } from './Icons';

const panels = [
  { role: 'admin', label: 'Admin Panel', href: '/admin', icon: 'shield' },
  { role: 'seller', label: 'Seller Center', href: '/seller', icon: 'store' },
  { role: 'logistics', label: 'Logistics Panel', href: '/logistics', icon: 'truck' },
  { role: 'rider', label: 'Rider App', href: '/rider', icon: 'bike' },
];

export function Avatar({ user, size = 'h-10 w-10' }) {
  if (user?.profile_picture) {
    return <img src={`/storage/${user.profile_picture}`} alt="" className={`${size} rounded-full object-cover`} />;
  }

  return (
    <span className={`${size} flex items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700 dark:bg-brand-500/20 dark:text-brand-400`}>
      {initials(user?.name)}
    </span>
  );
}

/**
 * Account dropdown with links to every panel the user can access.
 */
export default function UserMenu({ compact = false }) {
  const { auth } = usePage().props;
  const user = auth?.user;
  const [open, setOpen] = useState(false);
  const ref = useRef(null);

  useClickOutside(ref, () => setOpen(false), open);

  if (!user) {
    return null;
  }

  const available = panels.filter((panel) => user.roles?.includes(panel.role));

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        className="flex items-center gap-2 text-gray-700 dark:text-gray-300"
        aria-expanded={open}
        aria-haspopup="menu"
      >
        <Avatar user={user} />
        {!compact && <span className="hidden max-w-[140px] truncate text-theme-sm font-medium sm:block">{user.name}</span>}
        <IconChevronDown className={`hidden stroke-gray-500 transition sm:block dark:stroke-gray-400 ${open ? 'rotate-180' : ''}`} />
      </button>

      {open && (
        <div
          role="menu"
          className="absolute right-0 z-999999 mt-3 flex w-[260px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900"
        >
          <div className="px-2 pb-3">
            <span className="block truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">{user.name}</span>
            <span className="mt-0.5 block truncate text-theme-xs text-gray-500 dark:text-gray-400">{user.email}</span>
          </div>

          <ul className="flex flex-col gap-0.5 border-y border-gray-100 py-2 dark:border-gray-800">
            <li>
              <Link href="/" className="flex items-center gap-3 rounded-lg px-3 py-2 text-theme-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">
                <AppIcon name="cart" className="h-4.5 w-4.5" />
                Marketplace
              </Link>
            </li>
            {available.map((panel) => (
              <li key={panel.role}>
                <Link
                  href={panel.href}
                  className="flex items-center gap-3 rounded-lg px-3 py-2 text-theme-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
                >
                  <AppIcon name={panel.icon} className="h-4.5 w-4.5" />
                  {panel.label}
                </Link>
              </li>
            ))}
          </ul>

          <button
            type="button"
            onClick={() => router.post('/logout')}
            className="mt-2 flex items-center gap-3 rounded-lg px-3 py-2 text-left text-theme-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
          >
            <AppIcon name="power" className="h-4.5 w-4.5" />
            Sign out
          </button>
        </div>
      )}
    </div>
  );
}
