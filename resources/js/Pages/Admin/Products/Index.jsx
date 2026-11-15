import { Head, Link } from '@inertiajs/react';
import { FilterBar, FilterSelect } from '../../../Components/admin/Filters';
import StatusTabs from '../../../Components/admin/StatusTabs';
import { Card, DataTable, EmptyState, PageHeader, Pagination, SearchInput, StatusBadge } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatDate, formatMoney, formatNumber } from '../../../lib/format';

const tabs = [
  { key: null, label: 'All' },
  { key: 'live', label: 'Live' },
  { key: 'under_review', label: 'Under review' },
  { key: 'violation', label: 'Violations' },
  { key: 'unpublished', label: 'Unpublished' },
  { key: 'rejected', label: 'Rejected' },
  { key: 'draft', label: 'Draft' },
];

export default function Index({ products, filters, counts, categories }) {
  return (
    <AdminLayout>
      <Head title="Products" />
      <PageHeader
        title="Products"
        description="Review new listings and keep the catalog within policy."
        breadcrumbs={[{ label: 'Admin', href: '/admin' }, { label: 'Products' }]}
      />

      <Card bodyClassName="">
        <StatusTabs tabs={tabs} active={filters.status} counts={counts} />
        <FilterBar>
          <SearchInput value={filters.search} placeholder="Search product, brand or shop" className="w-full sm:w-80" />
          <FilterSelect param="category" value={filters.category} options={categories.map((category) => ({ value: category.id, label: category.name }))} placeholder="All categories" />
        </FilterBar>
        <DataTable
          rows={products.data}
          empty={<EmptyState title="No products found" description="Products appear here once sellers list them." />}
          columns={[
            {
              key: 'name',
              label: 'Product',
              render: (row) => (
                <Link href={`/admin/products/${row.id}`} className="flex items-center gap-3 hover:text-brand-600">
                  {row.image ? (
                    <img src={`/storage/${row.image}`} alt="" className="h-10 w-10 rounded-lg object-cover" />
                  ) : (
                    <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-xs text-gray-400 dark:bg-white/5">IMG</span>
                  )}
                  <span className="min-w-0">
                    <span className="block truncate font-medium text-gray-800 dark:text-white/90">{row.name}</span>
                    <span className="text-xs text-gray-500">{row.category ?? 'Uncategorized'}</span>
                  </span>
                </Link>
              ),
            },
            { key: 'shop', label: 'Shop', render: (row) => row.shop?.name },
            { key: 'base_price', label: 'Price', className: 'whitespace-nowrap', render: (row) => formatMoney(row.base_price) },
            { key: 'stock', label: 'Stock', render: (row) => <span className={row.stock === 0 ? 'text-error-500' : ''}>{formatNumber(row.stock)}</span> },
            { key: 'sold_count', label: 'Sold', render: (row) => formatNumber(row.sold_count) },
            { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.status} /> },
            { key: 'updated_at', label: 'Updated', className: 'whitespace-nowrap text-gray-500', render: (row) => formatDate(row.updated_at) },
          ]}
        />
        <Pagination paginator={products} />
      </Card>
    </AdminLayout>
  );
}
