import { Link } from '@inertiajs/react';

function decodeLabel(label) {
  return String(label).replace('&laquo;', '«').replace('&raquo;', '»');
}

/**
 * Renders a Laravel length-aware paginator ({ links, from, to, total }).
 */
export default function Pagination({ paginator }) {
  if (!paginator || paginator.last_page <= 1) {
    return null;
  }

  return (
    <nav
      aria-label="Pagination"
      className="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-5 py-4 sm:flex-row dark:border-gray-800"
    >
      <p className="text-sm text-gray-500 dark:text-gray-400">
        Showing <span className="font-medium text-gray-700 dark:text-gray-300">{paginator.from}</span>–
        <span className="font-medium text-gray-700 dark:text-gray-300">{paginator.to}</span> of{' '}
        <span className="font-medium text-gray-700 dark:text-gray-300">{paginator.total}</span>
      </p>
      <ul className="flex flex-wrap items-center gap-1">
        {paginator.links.map((link, index) => (
          <li key={`${link.label}-${index}`}>
            {link.url ? (
              <Link
                href={link.url}
                preserveScroll
                preserveState
                className={`flex h-9 min-w-9 items-center justify-center rounded-lg px-3 text-sm ${
                  link.active
                    ? 'bg-brand-500 text-white'
                    : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5'
                }`}
              >
                {decodeLabel(link.label)}
              </Link>
            ) : (
              <span className="flex h-9 min-w-9 items-center justify-center px-3 text-sm text-gray-400">
                {decodeLabel(link.label)}
              </span>
            )}
          </li>
        ))}
      </ul>
    </nav>
  );
}
