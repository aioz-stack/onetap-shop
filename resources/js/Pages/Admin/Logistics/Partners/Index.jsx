import { Head, Link } from '@inertiajs/react';
import { FilterBar } from '../../../../Components/admin/Filters';
import { Button, Card, DataTable, EmptyState, PageHeader, Pagination, SearchInput, StatusBadge } from '../../../../Components/ui';
import AdminLayout from '../../../../Layouts/AdminLayout';
import { formatMoney } from '../../../../lib/format';

export default function Index({ partners, search }) {
  return (
    <AdminLayout>
      <Head title="Logistics partners" />
      <PageHeader
        title="Logistics partners"
        description="Delivery companies, the areas they cover, and their fees."
        breadcrumbs={[{ label: 'Admin', href: '/admin' }, { label: 'Logistics partners' }]}
        actions={<Button href="/admin/logistics/partners/create">Add partner</Button>}
      />

      <Card bodyClassName="">
        <FilterBar>
          <SearchInput value={search} placeholder="Search name or code" className="w-full sm:w-72" />
        </FilterBar>
        <DataTable
          rows={partners.data}
          empty={<EmptyState title="No partners yet" action={<Button href="/admin/logistics/partners/create">Add the first partner</Button>} />}
          columns={[
            {
              key: 'name',
              label: 'Partner',
              render: (row) => (
                <Link href={`/admin/logistics/partners/${row.id}`} className="hover:text-brand-600">
                  <span className="block font-medium text-gray-800 dark:text-white/90">{row.name}</span>
                  <span className="text-xs text-gray-500">{row.code} · operator {row.owner?.name ?? '—'}</span>
                </Link>
              ),
            },
            { key: 'service_areas_count', label: 'Areas' },
            { key: 'riders_count', label: 'Riders' },
            { key: 'active_shipments_count', label: 'Active shipments' },
            { key: 'base_delivery_fee', label: 'Base fee', render: (row) => formatMoney(row.base_delivery_fee) },
            { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.status} /> },
          ]}
        />
        <Pagination paginator={partners} />
      </Card>
    </AdminLayout>
  );
}
