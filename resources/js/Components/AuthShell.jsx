import { Link } from '@inertiajs/react';
import GuestLayout from '../Layouts/GuestLayout';
import ThemeToggle from './ThemeToggle';
import Toast from './ui/Toast';

const highlights = [
  'Order from shops in your barangay',
  'Pay cash when your order arrives',
  'Track every delivery to your door',
];

/**
 * Split-screen frame used by the login and registration pages.
 */
export default function AuthShell({ title, subtitle, children, footer }) {
  return (
    <GuestLayout>
      <Toast />
      <div className="relative flex min-h-screen bg-white dark:bg-gray-900">
        <div className="flex w-full flex-1 flex-col px-5 py-8 sm:px-10 lg:w-1/2">
          <div className="flex items-center justify-between">
            <Link href="/">
              <img className="h-8 dark:hidden" src="/images/logo/logo.svg" alt="Onetap" />
              <img className="hidden h-8 dark:block" src="/images/logo/logo-dark.svg" alt="Onetap" />
            </Link>
            <ThemeToggle />
          </div>

          <div className="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-10">
            <h1 className="text-title-sm font-semibold text-gray-800 dark:text-white/90">{title}</h1>
            {subtitle && <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">{subtitle}</p>}
            <div className="mt-8">{children}</div>
            {footer && <div className="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">{footer}</div>}
          </div>
        </div>

        <aside className="relative hidden w-1/2 overflow-hidden bg-brand-900 lg:flex lg:items-center lg:justify-center">
          <div className="absolute -right-24 -top-24 h-96 w-96 rounded-full border-[48px] border-brand-500/30" aria-hidden="true" />
          <div className="absolute -bottom-32 -left-20 h-[28rem] w-[28rem] rounded-full border-[64px] border-brand-700/60" aria-hidden="true" />
          <div className="absolute right-24 top-40 h-6 w-6 rounded-full bg-orange-400" aria-hidden="true" />

          <div className="relative max-w-sm px-8 text-brand-50">
            <img src="/images/logo/logo-dark.svg" alt="" className="h-10" />
            <p className="mt-6 text-2xl font-semibold leading-snug text-white">Your neighborhood marketplace, one tap away.</p>
            <ul className="mt-8 space-y-3 text-sm">
              {highlights.map((item) => (
                <li key={item} className="flex items-center gap-3">
                  <span className="flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-xs text-white">✓</span>
                  {item}
                </li>
              ))}
            </ul>
          </div>
        </aside>
      </div>
    </GuestLayout>
  );
}
