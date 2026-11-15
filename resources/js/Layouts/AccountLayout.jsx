import { Link, usePage } from '@inertiajs/react';
import AppIcon from '../Components/AppIcon';
import { Avatar } from '../Components/UserMenu';
import CustomerLayout from './CustomerLayout';

const sections = [
  { heading: 'My purchases', items: [
    { label: 'Orders', href: '/account/orders', icon: 'receipt' },
    { label: 'Cancellations', href: '/account/cancellations', icon: 'cart' },
    { label: 'Returns & refunds', href: '/account/refunds', icon: 'wallet' },
    { label: 'My reviews', href: '/account/reviews', icon: 'star' },
  ] },
  { heading: 'Inbox', items: [
    { label: 'Notifications', href: '/account/notifications', icon: 'bell', count: 'notifications' },
    { label: 'Chat', href: '/account/chat', icon: 'chat', count: 'messages' },
  ] },
  { heading: 'Account', items: [
    { label: 'Profile', href: '/account/profile', icon: 'user' },
    { label: 'Addresses', href: '/account/addresses', icon: 'pin' },
    { label: 'Security', href: '/account/security', icon: 'shield' },
    { label: 'Verification', href: '/account/verification', icon: 'clipboard' },
    { label: 'Sell on Onetap', href: '/account/sell', icon: 'store' },
  ] },
];

/**
 * My Account: storefront chrome plus a section menu (horizontal on phones, sidebar on desktop).
 */
export default function AccountLayout({ title, actions, children }) {
  const { url, props } = usePage();
  const path = url.split('?')[0];
  const counts = props.counts ?? {};

  return (
    <CustomerLayout>
      <div className="grid gap-6 lg:grid-cols-[240px_1fr]">
        <aside className="min-w-0">
          <div className="mb-4 hidden items-center gap-3 lg:flex">
            <Avatar user={props.auth.user} size="h-11 w-11" />
            <div className="min-w-0">
              <p className="truncate font-semibold text-gray-800 dark:text-white/90">{props.auth.user.name}</p>
              <Link href="/account/profile" className="text-xs text-brand-600 hover:underline">
                Edit profile
              </Link>
            </div>
          </div>
          <nav aria-label="My account" className="no-scrollbar -mx-4 flex gap-4 overflow-x-auto px-4 lg:mx-0 lg:block lg:space-y-5 lg:px-0">
            {sections.map((section) => (
              <div key={section.heading} className="shrink-0 lg:shrink">
                <p className="mb-1.5 hidden text-xs font-semibold uppercase tracking-wide text-gray-400 lg:block">{section.heading}</p>
                <ul className="flex gap-1 lg:flex-col">
                  {section.items.map((item) => {
                    const active = path === item.href || path.startsWith(`${item.href}/`);
                    const count = item.count ? counts[item.count] : 0;

                    return (
                      <li key={item.href}>
                        <Link
                          href={item.href}
                          className={`flex items-center gap-2.5 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium ${
                            active ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5'
                          }`}
                        >
                          <AppIcon name={item.icon} className="hidden h-4.5 w-4.5 lg:block" />
                          <span className="flex-1">{item.label}</span>
                          {count > 0 && <span className="rounded-full bg-orange-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">{count}</span>}
                        </Link>
                      </li>
                    );
                  })}
                </ul>
              </div>
            ))}
          </nav>
        </aside>

        <section className="min-w-0">
          {(title || actions) && (
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
              {title && <h1 className="text-xl font-semibold text-gray-800 dark:text-white/90">{title}</h1>}
              {actions}
            </div>
          )}
          {children}
        </section>
      </div>
    </CustomerLayout>
  );
}
