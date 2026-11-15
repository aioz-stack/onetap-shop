import { Head, Link } from '@inertiajs/react';
import { DateRangeFilter, FilterBar } from '../../../Components/admin/Filters';
import StatusTabs from '../../../Components/admin/StatusTabs';
import { Card, DataTable, EmptyState, PageHeader, Pagination, SearchInput, StatusBadge } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatDateTime, formatMoney } from '../../../lib/format';

export default function Index({ orders, tabs, filters, counts }) {
  return (
    <AdminLayout>
      <Head title="Orders" />
      <PageHeader
        title="Orders"
        description="Every seller order across the marketplace. A customer checkout with several shops creates one seller order per shop."
        breadcrumbs={[{ label: 'Admin', href: '/admin' }, { label: 'Orders' }]}
      />

      <Card bodyClassName="">
        <StatusTabs tabs={[{ key: null, label: 'All' }, ...tabs]} param="tab" active={filters.tab} counts={counts} />
        <FilterBar>
          <SearchInput value={filters.search} placeholder="Order number, reference, customer or shop" className="w-full sm:w-96" />
          <DateRangeFilter from={filters.from} to={filters.to} />
        </FilterBar>
        <DataTable
          rows={orders.data}
          empty={<EmptyState title="No orders found" description="Orders appear here once customers check out." />}
          columns={[
            {
              key: 'reference',
              label: 'Order',
              render: (row) => (
                <Link href={`/admin/orders/${row.id}`} className="hover:text-brand-600">
                  <span className="block font-medium text-gray-800 dark:text-white/90">{row.reference}</span>
                  <span className="text-xs text-gray-500">{row.order_number}</span>
                </Link>
              ),
            },
            { key: 'customer', label: 'Customer' },
            { key: 'shop', label: 'Shop' },
            { key: 'items_count', label: 'Items' },
            { key: 'total', label: 'Total', className: 'whitespace-nowrap', render: (row) => formatMoney(row.total) },
            { key: 'payment', label: 'Payment', render: (row) => <span className="uppercase text-xs">{row.payment_method} · <StatusBadge status={row.payment_status} /></span> },
            { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.status} /> },
            { key: 'created_at', label: 'Placed', className: 'whitespace-nowrap text-gray-500', render: (row) => formatDateTime(row.created_at) },
          ]}
        />
        <Pagination paginator={orders} />
      </Card>
    </AdminLayout>
  );
}
