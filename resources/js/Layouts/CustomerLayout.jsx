import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import AppIcon from '../Components/AppIcon';
import ThemeToggle from '../Components/ThemeToggle';
import UserMenu from '../Components/UserMenu';
import Toast from '../Components/ui/Toast';

function SearchBar({ className = '' }) {
  const [term, setTerm] = useState(() => new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '').get('q') ?? '');

  const submit = (event) => {
    event.preventDefault();
    router.get('/', term ? { q: term } : {}, { preserveState: true });
  };

  return (
    <form onSubmit={submit} role="search" className={`relative ${className}`}>
      <input
        type="search"
        value={term}
        onChange={(event) => setTerm(event.target.value)}
        placeholder="Search products and local shops"
        aria-label="Search products and shops"
        className="h-11 w-full rounded-full border border-gray-200 bg-gray-50 pl-5 pr-14 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:bg-white focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-800 dark:bg-white/[0.03] dark:text-white/90"
      />
      <button
        type="submit"
        aria-label="Search"
        className="absolute right-1.5 top-1/2 flex h-8 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-brand-500 text-white hover:bg-brand-600"
      >
        <AppIcon name="search" className="h-4 w-4" strokeWidth={2.2} />
      </button>
    </form>
  );
}

/**
 * Public storefront shell: header with search, category strip, footer.
 */
export default function CustomerLayout({ categories = [], children }) {
  const { auth, marketplace } = usePage().props;
  const user = auth?.user;

  return (
    <div className="flex min-h-screen flex-col bg-gray-50 dark:bg-gray-950">
      <Toast />

      <div className="bg-brand-700 text-xs text-brand-50">
        <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-1.5">
          <span className="truncate">{marketplace?.tagline ?? 'Shop local, delivered fast.'}</span>
          <div className="hidden items-center gap-4 sm:flex">
            {user?.roles?.includes('seller') ? (
              <Link href="/seller" className="hover:text-white">
                Seller Center
              </Link>
            ) : (
              <span>Sell on Onetap — coming soon</span>
            )}
            <span aria-hidden="true">·</span>
            <span>Cash on Delivery available</span>
          </div>
        </div>
      </div>

      <header className="sticky top-0 z-9999 border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
        <div className="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 sm:gap-6">
          <Link href="/" className="shrink-0">
            <img className="h-8 dark:hidden" src="/images/logo/logo.svg" alt={marketplace?.name ?? 'Onetap'} />
            <img className="hidden h-8 dark:block" src="/images/logo/logo-dark.svg" alt={marketplace?.name ?? 'Onetap'} />
          </Link>

          <SearchBar className="hidden flex-1 md:block" />

          <div className="ml-auto flex items-center gap-2 sm:gap-3">
            <ThemeToggle className="hidden sm:flex" />
            <span
              className="relative flex h-10 w-10 items-center justify-center rounded-full text-gray-400 dark:text-gray-600"
              title="Cart opens in Phase 4"
              aria-label="Cart (coming soon)"
            >
              <AppIcon name="cart" className="h-6 w-6" />
            </span>
            {user ? (
              <UserMenu />
            ) : (
              <div className="flex items-center gap-2">
                <Link href="/login" className="rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">
                  Log in
                </Link>
                <Link href="/register" className="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                  Sign up
                </Link>
              </div>
            )}
          </div>
        </div>

        <div className="px-4 pb-3 md:hidden">
          <SearchBar />
        </div>

        {categories.length > 0 && (
          <nav aria-label="Categories" className="border-t border-gray-100 dark:border-gray-800">
            <ul className="no-scrollbar mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4">
              {categories.map((category) => (
                <li key={category.id}>
                  <Link
                    href={`/?category=${category.slug}`}
                    className="block whitespace-nowrap px-3 py-2.5 text-sm text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400"
                  >
                    {category.name}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
        )}
      </header>

      <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-6">{children}</main>

      <footer className="border-t border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
        <div className="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:grid-cols-3">
          <div>
            <img className="h-7 dark:hidden" src="/images/logo/logo.svg" alt="Onetap" />
            <img className="hidden h-7 dark:block" src="/images/logo/logo-dark.svg" alt="Onetap" />
            <p className="mt-3 max-w-xs text-sm text-gray-500 dark:text-gray-400">
              A marketplace for neighborhood businesses, with local riders delivering to your door.
            </p>
          </div>
          <div>
            <h3 className="text-sm font-semibold text-gray-800 dark:text-white/90">Shop</h3>
            <ul className="mt-3 space-y-2 text-sm text-gray-500 dark:text-gray-400">
              <li>Categories</li>
              <li>Popular shops</li>
              <li>Cash on Delivery</li>
            </ul>
          </div>
          <div>
            <h3 className="text-sm font-semibold text-gray-800 dark:text-white/90">Account</h3>
            <ul className="mt-3 space-y-2 text-sm text-gray-500 dark:text-gray-400">
              {user ? (
                <li>
                  <Link href={user.home} className="hover:text-brand-600">
                    My dashboard
                  </Link>
                </li>
              ) : (
                <>
                  <li>
                    <Link href="/login" className="hover:text-brand-600">
                      Log in
                    </Link>
                  </li>
                  <li>
                    <Link href="/register" className="hover:text-brand-600">
                      Create an account
                    </Link>
                  </li>
                </>
              )}
            </ul>
          </div>
        </div>
        <p className="border-t border-gray-100 py-4 text-center text-xs text-gray-400 dark:border-gray-800">
          © {new Date().getFullYear()} {marketplace?.name ?? 'Onetap Marketplace'}
        </p>
      </footer>
    </div>
  );
}
