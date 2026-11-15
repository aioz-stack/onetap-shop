import { applyFilters } from '../../lib/query';
import { Select, TextInput } from '../ui';

/**
 * A select that writes its value to a query-string filter.
 */
export function FilterSelect({ param, value, options, placeholder = 'All', className = 'w-full sm:w-48' }) {
  return (
    <Select
      aria-label={placeholder}
      className={`h-10 ${className}`}
      value={value ?? ''}
      placeholder={placeholder}
      options={options}
      onChange={(event) => applyFilters({ [param]: event.target.value })}
    />
  );
}

export function DateRangeFilter({ from, to }) {
  return (
    <div className="flex items-center gap-2">
      <TextInput
        type="date"
        aria-label="From date"
        className="h-10 w-full sm:w-40"
        value={from ?? ''}
        onChange={(event) => applyFilters({ from: event.target.value })}
      />
      <span className="text-sm text-gray-400">to</span>
      <TextInput
        type="date"
        aria-label="To date"
        className="h-10 w-full sm:w-40"
        value={to ?? ''}
        onChange={(event) => applyFilters({ to: event.target.value })}
      />
    </div>
  );
}

/**
 * Row of filter controls above a table.
 */
export function FilterBar({ children }) {
  return <div className="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row sm:flex-wrap sm:items-center dark:border-gray-800">{children}</div>;
}
