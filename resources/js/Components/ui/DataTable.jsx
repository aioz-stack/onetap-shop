import { EmptyState } from './States';
import { LoadingState } from './Spinner';

/**
 * Generic responsive table.
 *
 * columns: [{ key, label, render?: (row) => node, className?, headerClassName? }]
 */
export default function DataTable({ columns, rows, rowKey = 'id', loading = false, empty, onRowClick }) {
  if (loading) {
    return <LoadingState />;
  }

  if (!rows?.length) {
    return empty ?? <EmptyState title="No records found" description="Try changing your search or filters." />;
  }

  return (
    <div className="max-w-full overflow-x-auto custom-scrollbar">
      <table className="min-w-full text-left text-sm">
        <thead>
          <tr className="border-b border-gray-100 dark:border-gray-800">
            {columns.map((column) => (
              <th
                key={column.key}
                scope="col"
                className={`whitespace-nowrap px-5 py-3 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 ${
                  column.headerClassName ?? ''
                }`}
              >
                {column.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
          {rows.map((row) => (
            <tr
              key={typeof rowKey === 'function' ? rowKey(row) : row[rowKey]}
              onClick={onRowClick ? () => onRowClick(row) : undefined}
              className={onRowClick ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-white/[0.02]' : ''}
            >
              {columns.map((column) => (
                <td key={column.key} className={`px-5 py-3.5 text-gray-700 dark:text-gray-300 ${column.className ?? ''}`}>
                  {column.render ? column.render(row) : row[column.key]}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
