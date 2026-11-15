import { Link, usePage } from '@inertiajs/react';
import AppIcon from '../Components/AppIcon';
import NotificationBell from '../Components/NotificationBell';
import ThemeToggle from '../Components/ThemeToggle';
import UserMenu from '../Components/UserMenu';
import Toast from '../Components/ui/Toast';
import { isActivePath, riderTabs } from '../lib/navigation';

/**
 * Mobile-first rider shell: compact top bar plus a bottom tab bar.
 */
export default function RiderLayout({ title, children }) {
  const { url, props } = usePage();
  const counts = props.riderCounts ?? {};

  return (
    <div className="min-h-screen bg-gray-50 pb-20 dark:bg-gray-950">
      <Toast />

      <header className="sticky top-0 z-9999 border-b border-gray-200 bg-white/95 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
        <div className="mx-auto flex max-w-2xl items-center justify-between gap-3 px-4 py-3">
          <Link href="/rider" className="flex min-w-0 items-center gap-2">
            <img src="/images/logo/logo-icon.svg" alt="Onetap" className="h-8 w-8" />
            <span className="truncate text-sm font-semibold text-gray-800 dark:text-white/90">{title ?? 'Rider'}</span>
          </Link>
          <div className="flex items-center gap-2">
            <ThemeToggle />
            <NotificationBell />
            <UserMenu compact />
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-2xl px-4 py-5">{children}</main>

      <nav
        aria-label="Rider navigation"
        className="fixed inset-x-0 bottom-0 z-9999 border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] dark:border-gray-800 dark:bg-gray-900"
      >
        <ul className="mx-auto grid max-w-2xl grid-cols-4">
          {riderTabs.map((tab) => {
            const active = isActivePath(url, tab);
            const badge = tab.badge ? counts[tab.badge] : 0;

            return (
              <li key={tab.label}>
                <Link
                  href={tab.href}
                  aria-current={active ? 'page' : undefined}
                  className={`relative flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium ${active ? 'text-brand-600 dark:text-brand-400' : 'text-gray-500 dark:text-gray-400'}`}
                >
                  <AppIcon name={tab.icon} className="h-6 w-6" />
                  {tab.label}
                  {badge > 0 && (
                    <span className="absolute left-1/2 top-1.5 ml-2 min-w-4.5 rounded-full bg-error-500 px-1 text-center text-[10px] leading-4.5 font-semibold text-white">
                      {badge > 99 ? '99+' : badge}
                    </span>
                  )}
                </Link>
              </li>
            );
          })}
        </ul>
      </nav>
    </div>
  );
}
