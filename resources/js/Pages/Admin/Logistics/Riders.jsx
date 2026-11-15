import { Head, Link } from '@inertiajs/react';
import ActionButton from '../../../Components/admin/ActionButton';
import { FilterBar, FilterSelect } from '../../../Components/admin/Filters';
import StatusTabs from '../../../Components/admin/StatusTabs';
import { Card, DataTable, EmptyState, PageHeader, Pagination, SearchInput, StatusBadge } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatDateTime, formatMoney } from '../../../lib/format';

export default function Riders({ riders, filters, counts, partners, availabilities }) {
  return (
    <AdminLayout>
      <Head title="Riders" />
      <PageHeader
        title="Riders"
        description="Every rider across all logistics partners, with their performance."
        breadcrumbs={[{ label: 'Logistics', href: '/admin/logistics/partners' }, { label: 'Riders' }]}
      />

      <Card bodyClassName="">
        <StatusTabs
          tabs={[
            { key: null, label: 'All' },
            { key: 'active', label: 'Active' },
            { key: 'pending', label: 'Pending approval' },
            { key: 'suspended', label: 'Suspended' },
          ]}
          active={filters.status}
          counts={counts}
        />
        <FilterBar>
          <SearchInput value={filters.search} placeholder="Search name, phone or plate" className="w-full sm:w-72" />
          <FilterSelect param="partner" value={filters.partner} options={partners.map((partner) => ({ value: partner.id, label: partner.name }))} placeholder="All partners" />
          <FilterSelect param="availability" value={filters.availability} options={availabilities} placeholder="Any availability" />
        </FilterBar>
        <DataTable
          rows={riders.data}
          empty={<EmptyState title="No riders found" />}
          columns={[
            { key: 'name', label: 'Rider', render: (row) => <Link href={`/admin/users/${row.user_id}`} className="hover:text-brand-600"><span className="block font-medium text-gray-800 dark:text-white/90">{row.name}</span><span className="text-xs text-gray-500">{row.phone ?? '—'}</span></Link> },
            { key: 'partner', label: 'Partner' },
            { key: 'vehicle', label: 'Vehicle', render: (row) => `${row.vehicle_type ?? '—'} · ${row.plate_number ?? ''}` },
            { key: 'active_count', label: 'Active jobs' },
            { key: 'completed_count', label: 'Delivered' },
            { key: 'cod_collected', label: 'COD collected', className: 'whitespace-nowrap', render: (row) => formatMoney(row.cod_collected) },
            { key: 'availability', label: 'Availability', render: (row) => <span><StatusBadge status={row.availability} /><span className="block text-xs text-gray-500">{row.last_seen_at ? formatDateTime(row.last_seen_at) : ''}</span></span> },
            { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.status} /> },
            {
              key: 'actions',
              label: '',
              className: 'text-right',
              render: (row) =>
                row.status === 'suspended' || row.status === 'pending' ? (
                  <ActionButton url={`/admin/logistics/riders/${row.id}/activate`} label={row.status === 'pending' ? 'Approve' : 'Activate'} variant="primary" message={`${row.name} can take deliveries again.`} />
                ) : (
                  <ActionButton url={`/admin/logistics/riders/${row.id}/suspend`} label="Suspend" tone="danger" message={`${row.name} goes offline and cannot receive assignments.`} />
                ),
            },
          ]}
        />
        <Pagination paginator={riders} />
      </Card>
    </AdminLayout>
  );
}
