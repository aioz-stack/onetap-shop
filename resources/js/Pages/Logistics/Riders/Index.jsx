import { Head, Link } from '@inertiajs/react';
import ActionButton from '../../../Components/admin/ActionButton';
import { FilterBar } from '../../../Components/admin/Filters';
import StatusTabs from '../../../Components/admin/StatusTabs';
import { Button, Card, DataTable, EmptyState, PageHeader, Pagination, SearchInput, StatusBadge } from '../../../Components/ui';
import LogisticsLayout from '../../../Layouts/LogisticsLayout';
import { formatDateTime, formatMoney } from '../../../lib/format';

export default function Index({ riders, filters, counts }) {
  return (
    <LogisticsLayout>
      <Head title="Riders" />
      <PageHeader title="Riders" description="Your delivery team: approvals, availability and workload." actions={<Button href="/logistics/riders/create">Add rider</Button>} />
      <Card bodyClassName="">
        <StatusTabs tabs={[{ key: null, label: 'All' }, { key: 'active', label: 'Active' }, { key: 'pending', label: 'Waiting for approval' }, { key: 'suspended', label: 'Suspended' }]} active={filters.status} counts={counts} />
        <FilterBar>
          <SearchInput value={filters.search} placeholder="Name, phone or plate" className="w-full sm:w-72" />
        </FilterBar>
        <DataTable
          rows={riders.data}
          empty={<EmptyState title="No riders yet" action={<Button href="/logistics/riders/create">Add your first rider</Button>} />}
          columns={[
            { key: 'name', label: 'Rider', render: (row) => <Link href={`/logistics/riders/${row.id}`} className="hover:text-brand-600"><span className="block font-medium text-gray-800 dark:text-white/90">{row.name}</span><span className="text-xs text-gray-500">{row.phone} · {row.vehicle}</span></Link> },
            { key: 'availability', label: 'Availability', render: (row) => <span><StatusBadge status={row.availability} /><span className="block text-xs text-gray-500">{row.last_seen_at ? `Seen ${formatDateTime(row.last_seen_at)}` : ''}</span></span> },
            { key: 'open_jobs', label: 'Open jobs' },
            { key: 'completed_jobs', label: 'Completed' },
            { key: 'unremitted', label: 'Cash on hand', render: (row) => <span className={Number(row.unremitted) > 0 ? 'font-semibold text-warning-600' : ''}>{formatMoney(row.unremitted)}</span> },
            { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.status} /> },
            {
              key: 'actions',
              label: '',
              className: 'text-right',
              render: (row) =>
                row.status === 'active' ? (
                  <ActionButton url={`/logistics/riders/${row.id}/suspend`} label="Suspend" tone="danger" variant="ghost" message={`${row.name} goes offline. Pickups they have not collected yet go back to the queue.`} />
                ) : (
                  <ActionButton url={`/logistics/riders/${row.id}/approve`} label={row.status === 'pending' ? 'Approve' : 'Reactivate'} variant="primary" message={`${row.name} can receive deliveries.`} />
                ),
            },
          ]}
        />
        <Pagination paginator={riders} />
      </Card>
    </LogisticsLayout>
  );
}
