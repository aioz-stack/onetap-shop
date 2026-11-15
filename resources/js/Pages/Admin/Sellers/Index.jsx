import { Head, Link } from '@inertiajs/react';
import { FilterBar } from '../../../Components/admin/Filters';
import StatusTabs from '../../../Components/admin/StatusTabs';
import { Badge, Card, DataTable, EmptyState, PageHeader, Pagination, SearchInput, StatusBadge } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatDate, headline } from '../../../lib/format';

export default function Index({ sellers, status, counts, search }) {
  return (
    <AdminLayout>
      <Head title="Sellers" />
      <PageHeader
        title="Sellers"
        description="Applications, approved shops and seller standing."
        breadcrumbs={[{ label: 'Admin', href: '/admin' }, { label: 'Sellers' }]}
      />

      <Card bodyClassName="">
        <StatusTabs
          tabs={[
            { key: 'pending', label: 'Pending approval' },
            { key: 'approved', label: 'Approved' },
            { key: 'rejected', label: 'Rejected' },
            { key: 'suspended', label: 'Suspended' },
          ]}
          active={status}
          counts={counts}
        />
        <FilterBar>
          <SearchInput value={search} placeholder="Search business, shop or owner" className="w-full sm:w-80" />
        </FilterBar>
        <DataTable
          rows={sellers.data}
          onRowClick={null}
          empty={<EmptyState title={status === 'pending' ? 'No applications waiting' : `No ${status} sellers`} />}
          columns={[
            {
              key: 'business_name',
              label: 'Business',
              render: (row) => (
                <Link href={`/admin/sellers/${row.id}`} className="block hover:text-brand-600">
                  <span className="block font-medium text-gray-800 dark:text-white/90">{row.business_name}</span>
                  <span className="text-xs text-gray-500">{headline(row.business_type ?? '')}</span>
                </Link>
              ),
            },
            { key: 'owner', label: 'Owner', render: (row) => <span className="text-sm">{row.owner?.name}<span className="block text-xs text-gray-500">{row.owner?.email}</span></span> },
            { key: 'shop', label: 'Shop', render: (row) => (row.shop ? <span>{row.shop.name} <StatusBadge status={row.shop.status} /></span> : '—') },
            { key: 'violations', label: 'Open violations', render: (row) => (row.violations_count ? <Badge tone="error">{row.violations_count}</Badge> : <span className="text-gray-400">0</span>) },
            { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.status} /> },
            { key: 'created_at', label: 'Applied', className: 'whitespace-nowrap text-gray-500', render: (row) => formatDate(row.created_at) },
            {
              key: 'open',
              label: '',
              className: 'text-right',
              render: (row) => (
                <Link href={`/admin/sellers/${row.id}`} className="text-sm font-medium text-brand-600 hover:text-brand-700">
                  {row.status === 'pending' ? 'Review' : 'Open'}
                </Link>
              ),
            },
          ]}
        />
        <Pagination paginator={sellers} />
      </Card>
    </AdminLayout>
  );
}
