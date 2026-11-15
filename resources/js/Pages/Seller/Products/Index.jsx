import { Head, Link } from '@inertiajs/react';
import ActionButton from '../../../Components/admin/ActionButton';
import { FilterBar } from '../../../Components/admin/Filters';
import StatusTabs from '../../../Components/admin/StatusTabs';
import { ProductImage } from '../../../Components/store/ProductCard';
import { Button, Card, DataTable, EmptyState, PageHeader, Pagination, SearchInput, StatusBadge } from '../../../Components/ui';
import SellerLayout from '../../../Layouts/SellerLayout';
import { formatDate, formatMoney, formatNumber } from '../../../lib/format';

const tabs = [
  { key: null, label: 'All' },
  { key: 'live', label: 'Live' },
  { key: 'under_review', label: 'Under review' },
  { key: 'violation', label: 'Violations' },
  { key: 'unpublished', label: 'Unpublished' },
  { key: 'draft', label: 'Drafts' },
  { key: 'rejected', label: 'Rejected' },
];

function Actions({ product }) {
  const base = `/seller/products/${product.id}`;

  return (
    <div className="flex justify-end gap-1">
      <Button size="sm" variant="ghost" href={`${base}/edit`}>Edit</Button>
      {['draft', 'rejected'].includes(product.status) && <ActionButton url={`${base}/submit`} label="Submit" variant="ghost" message="Send this product to Onetap for review. Reviews usually take less than a day." />}
      {product.status === 'live' && <ActionButton url={`${base}/unpublish`} label="Unpublish" variant="ghost" message="Hide this product from the storefront. You can republish it anytime." />}
      {product.status === 'unpublished' && <ActionButton url={`${base}/republish`} label="Republish" variant="ghost" message={product.published ? 'Put this product back on the storefront.' : 'Send this product for review.'} />}
      <ActionButton url={base} method="delete" label="Delete" variant="ghost" tone="danger" message="The product is removed from your shop and the storefront. Past orders keep their details." />
    </div>
  );
}

export default function Index({ products, filters, counts }) {
  return (
    <SellerLayout>
      <Head title="Products" />
      <PageHeader title="Products" description="Everything you sell. New products and big edits are reviewed before going live." actions={<Button href="/seller/products/create">Add product</Button>} />

      <Card bodyClassName="">
        <StatusTabs tabs={tabs} active={filters.status} counts={counts} />
        <FilterBar>
          <SearchInput value={filters.search} placeholder="Search your products" className="w-full sm:w-72" />
        </FilterBar>
        <DataTable
          rows={products.data}
          empty={<EmptyState title="No products here" description="Add your first product to start selling." action={<Button href="/seller/products/create">Add product</Button>} />}
          columns={[
            {
              key: 'name',
              label: 'Product',
              render: (row) => (
                <Link href={`/seller/products/${row.id}/edit`} className="flex items-center gap-3 hover:text-brand-600">
                  <span className="h-11 w-11 shrink-0 overflow-hidden rounded-lg bg-gray-50 dark:bg-white/5">
                    <ProductImage src={row.image ? `/storage/${row.image}` : null} name={row.name} />
                  </span>
                  <span className="min-w-0">
                    <span className="block max-w-xs truncate font-medium text-gray-800 dark:text-white/90">{row.name}</span>
                    <span className="text-xs text-gray-500">{row.category} · {row.variations_count} variation{row.variations_count === 1 ? '' : 's'}</span>
                  </span>
                </Link>
              ),
            },
            { key: 'price', label: 'From', className: 'whitespace-nowrap', render: (row) => formatMoney(row.price) },
            { key: 'available', label: 'Available', render: (row) => <span className={row.available === 0 ? 'font-semibold text-error-600' : ''}>{formatNumber(row.available)}</span> },
            { key: 'sold_count', label: 'Sold', render: (row) => formatNumber(row.sold_count) },
            { key: 'status', label: 'Status', render: (row) => <span><StatusBadge status={row.status} />{row.rejection_reason && <span className="mt-1 block max-w-[220px] text-xs text-error-500">{row.rejection_reason}</span>}</span> },
            { key: 'updated_at', label: 'Updated', className: 'whitespace-nowrap text-gray-500', render: (row) => formatDate(row.updated_at) },
            { key: 'actions', label: '', className: 'text-right', render: (row) => <Actions product={row} /> },
          ]}
        />
        <Pagination paginator={products} />
      </Card>
    </SellerLayout>
  );
}
