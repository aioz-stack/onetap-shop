import { Link } from '@inertiajs/react';
import { IconClose, IconHamburger, IconMenuLines } from './Icons';
import ThemeToggle from './ThemeToggle';
import UserMenu from './UserMenu';

/**
 * Top bar for back-office panels (admin, seller, logistics).
 */
export default function Header({ sidebarToggle, onToggleSidebar, homeHref = '/dashboard', title }) {
  return (
    <header className="sticky top-0 z-99999 flex w-full border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
      <div className="flex grow items-center justify-between gap-3 px-3 py-3 lg:px-6 lg:py-4">
        <div className="flex min-w-0 items-center gap-3">
          <button
            type="button"
            onClick={onToggleSidebar}
            aria-label="Toggle sidebar"
            className={`z-99999 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border-gray-200 text-gray-500 lg:h-11 lg:w-11 lg:border dark:border-gray-800 dark:text-gray-400 ${
              sidebarToggle ? 'bg-gray-100 dark:bg-gray-800 lg:bg-transparent dark:lg:bg-transparent' : ''
            }`}
          >
            <IconHamburger className="hidden fill-current lg:block" />
            <IconMenuLines className={`fill-current lg:hidden ${sidebarToggle ? 'hidden' : 'block'}`} />
            <IconClose className={`fill-current ${sidebarToggle ? 'block lg:hidden' : 'hidden'}`} />
          </button>

          <Link href={homeHref} className="lg:hidden">
            <img className="h-7 dark:hidden" src="/images/logo/logo.svg" alt="Onetap" />
            <img className="hidden h-7 dark:block" src="/images/logo/logo-dark.svg" alt="Onetap" />
          </Link>

          {title && <span className="hidden truncate text-sm font-medium text-gray-500 lg:block dark:text-gray-400">{title}</span>}
        </div>

        <div className="flex items-center gap-2 sm:gap-3">
          <ThemeToggle />
          <UserMenu />
        </div>
      </div>
    </header>
  );
}
