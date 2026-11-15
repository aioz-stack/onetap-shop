import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { DateRangeFilter, FilterBar, FilterSelect } from '../../../Components/admin/Filters';
import { Card, DataTable, EmptyState, PageHeader, Pagination, SearchInput } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatDateTime, headline } from '../../../lib/format';

function Changes({ log }) {
  const [open, setOpen] = useState(false);

  if (!log.old_values && !log.new_values) {
    return <span className="text-gray-400">—</span>;
  }

  return (
    <div>
      <button type="button" onClick={() => setOpen((value) => !value)} className="text-xs font-medium text-brand-600 hover:underline">
        {open ? 'Hide' : 'Show'} changes
      </button>
      {open && (
        <pre className="mt-2 max-w-md overflow-x-auto rounded-lg bg-gray-50 p-3 text-[11px] leading-relaxed text-gray-700 dark:bg-white/5 dark:text-gray-300">
          {JSON.stringify({ before: log.old_values, after: log.new_values }, null, 2)}
        </pre>
      )}
    </div>
  );
}

export default function Index({ logs, filters, actionGroups }) {
  return (
    <AdminLayout>
      <Head title="Audit logs" />
      <PageHeader
        title="Audit logs"
        description="Who did what and when: sign-ins, approvals, status changes, money movements and settings."
        breadcrumbs={[{ label: 'Settings', href: '/admin/settings' }, { label: 'Audit logs' }]}
      />

      <Card bodyClassName="">
        <FilterBar>
          <SearchInput value={filters.search} placeholder="User or IP address" className="w-full sm:w-64" />
          <FilterSelect param="action" value={filters.action} options={actionGroups.map((group) => ({ value: group, label: headline(group) }))} placeholder="All areas" />
          <DateRangeFilter from={filters.from} to={filters.to} />
        </FilterBar>
        <DataTable
          rows={logs.data}
          empty={<EmptyState title="No entries match" />}
          columns={[
            { key: 'created_at', label: 'When', className: 'whitespace-nowrap', render: (row) => formatDateTime(row.created_at) },
            { key: 'user', label: 'User', render: (row) => (row.user ? <span>{row.user.name}<span className="block text-xs text-gray-500">@{row.user.username}</span></span> : 'System') },
            { key: 'action', label: 'Action', className: 'font-mono text-xs' },
            { key: 'subject', label: 'Record', render: (row) => row.subject ?? '—' },
            { key: 'ip_address', label: 'IP', className: 'font-mono text-xs text-gray-500' },
            { key: 'changes', label: 'Changes', render: (row) => <Changes log={row} /> },
          ]}
        />
        <Pagination paginator={logs} />
      </Card>
    </AdminLayout>
  );
}
