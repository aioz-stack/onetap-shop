import { Link } from '@inertiajs/react';

/**
 * Status filter tabs that navigate via query string, e.g. ?status=to_ship.
 *
 * tabs: [{ key, label, href, count? }]
 */
export default function Tabs({ tabs, active }) {
  return (
    <div className="no-scrollbar -mb-px flex gap-1 overflow-x-auto border-b border-gray-200 dark:border-gray-800">
      {tabs.map((tab) => {
        const isActive = tab.key === active;

        return (
          <Link
            key={tab.key}
            href={tab.href}
            preserveScroll
            className={`flex shrink-0 items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition ${
              isActive
                ? 'border-brand-500 text-brand-600 dark:text-brand-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
            }`}
          >
            {tab.label}
            {tab.count !== undefined && (
              <span
                className={`rounded-full px-2 py-0.5 text-xs ${
                  isActive ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15' : 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400'
                }`}
              >
                {tab.count}
              </span>
            )}
          </Link>
        );
      })}
    </div>
  );
}
