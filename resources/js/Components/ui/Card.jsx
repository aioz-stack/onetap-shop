/**
 * `pinActions` keeps small header actions (like an on/off switch) in the top-right corner on every
 * screen size, instead of wrapping them under the title on narrow screens.
 */
export default function Card({ title, description, actions, pinActions = false, className = '', bodyClassName = 'p-5 sm:p-6', children }) {
  return (
    <section
      className={`min-w-0 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] ${className}`}
    >
      {(title || actions) && (
        <header className={`flex items-start justify-between gap-3 border-b border-gray-100 px-5 py-4 sm:px-6 dark:border-gray-800 ${pinActions ? 'flex-nowrap' : 'flex-wrap'}`}>
          <div className={pinActions ? 'min-w-0 flex-1' : undefined}>
            {title && <h2 className="text-base font-semibold text-gray-800 dark:text-white/90">{title}</h2>}
            {description && <p className="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{description}</p>}
          </div>
          {actions && <div className={`flex items-center gap-2 ${pinActions ? 'shrink-0' : 'flex-wrap'}`}>{actions}</div>}
        </header>
      )}
      <div className={bodyClassName}>{children}</div>
    </section>
  );
}
