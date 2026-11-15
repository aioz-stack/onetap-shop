/**
 * Label/value pairs for detail pages. items: [{ label, value }]
 */
export default function DetailList({ items, columns = 2 }) {
  return (
    <dl className={`grid gap-x-6 gap-y-4 ${columns === 2 ? 'sm:grid-cols-2' : columns === 3 ? 'sm:grid-cols-3' : ''}`}>
      {items
        .filter((item) => item !== null && item !== false)
        .map((item) => (
          <div key={item.label} className="min-w-0">
            <dt className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{item.label}</dt>
            <dd className="mt-1 break-words text-sm text-gray-800 dark:text-white/90">{item.value ?? '—'}</dd>
          </div>
        ))}
    </dl>
  );
}
