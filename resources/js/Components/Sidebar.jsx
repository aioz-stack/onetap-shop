import { Link, usePage } from '@inertiajs/react';
import AppIcon from './AppIcon';
import { IconMenuDots } from './Icons';
import { isActivePath } from '../lib/navigation';

/**
 * Collapsible back-office sidebar. `nav` is a list of { heading, items: [{ label, href?, icon, exact? }] }.
 */
export default function Sidebar({ sidebarToggle, nav = [], panelLabel, homeHref = '/dashboard' }) {
  const { url, props } = usePage();
  const counts = props.adminCounts ?? props.sellerCounts ?? props.logisticsCounts ?? {};

  return (
    <aside
      className={`sidebar fixed left-0 top-0 z-9999 flex h-screen w-[290px] flex-col overflow-y-hidden border-r border-gray-200 bg-white px-5 dark:border-gray-800 dark:bg-black lg:static lg:translate-x-0 ${
        sidebarToggle ? 'translate-x-0 lg:w-[90px]' : '-translate-x-full'
      }`}
    >
      <div className={`sidebar-header flex items-center gap-2 pb-6 pt-8 ${sidebarToggle ? 'justify-center' : 'justify-between'}`}>
        <Link href={homeHref} className="flex flex-col gap-1.5">
          <span className={`logo ${sidebarToggle ? 'lg:hidden' : ''}`}>
            <img className="dark:hidden" src="/images/logo/logo.svg" alt="Onetap" />
            <img className="hidden dark:block" src="/images/logo/logo-dark.svg" alt="Onetap" />
          </span>
          <img className={`logo-icon ${sidebarToggle ? 'hidden lg:block' : 'hidden'}`} src="/images/logo/logo-icon.svg" alt="Onetap" />
          {panelLabel && (
            <span
              className={`text-[11px] font-semibold uppercase tracking-[0.14em] text-brand-600 dark:text-brand-400 ${
                sidebarToggle ? 'lg:hidden' : ''
              }`}
            >
              {panelLabel}
            </span>
          )}
        </Link>
      </div>

      <div className="no-scrollbar flex flex-col overflow-y-auto pb-8 duration-300 ease-linear">
        <nav>
          {nav.map((group) => (
            <div key={group.heading} className="mb-5">
              <h3 className="mb-3 text-xs uppercase leading-[20px] text-gray-400">
                <span className={sidebarToggle ? 'lg:hidden' : ''}>{group.heading}</span>
                <IconMenuDots className={`mx-auto fill-current ${sidebarToggle ? 'hidden lg:block' : 'hidden'}`} />
              </h3>

              <ul className="flex flex-col gap-1">
                {group.items.map((item) => {
                  const active = isActivePath(url, item);
                  const content = (
                    <>
                      <AppIcon name={item.icon} className="h-5 w-5 shrink-0" />
                      <span className={`menu-item-text flex-1 ${sidebarToggle ? 'lg:hidden' : ''}`}>{item.label}</span>
                      {item.badge && counts[item.badge] > 0 && (
                        <span
                          aria-label={`${counts[item.badge]} waiting`}
                          className={`rounded-full bg-orange-500 px-2 py-0.5 text-[11px] font-semibold text-white ${sidebarToggle ? 'lg:hidden' : ''}`}
                        >
                          {counts[item.badge]}
                        </span>
                      )}
                      {!item.href && (
                        <span
                          className={`rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium uppercase text-gray-500 dark:bg-white/5 dark:text-gray-400 ${
                            sidebarToggle ? 'lg:hidden' : ''
                          }`}
                        >
                          Soon
                        </span>
                      )}
                    </>
                  );

                  return (
                    <li key={item.label}>
                      {item.href ? (
                        <Link href={item.href} className={`menu-item group ${active ? 'menu-item-active' : 'menu-item-inactive'}`}>
                          {content}
                        </Link>
                      ) : (
                        <span
                          aria-disabled="true"
                          title="Coming in a later phase"
                          className="menu-item cursor-not-allowed text-gray-400 dark:text-gray-600"
                        >
                          {content}
                        </span>
                      )}
                    </li>
                  );
                })}
              </ul>
            </div>
          ))}
        </nav>
      </div>
    </aside>
  );
}
