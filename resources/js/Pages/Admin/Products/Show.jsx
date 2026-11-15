import { Head, Link } from '@inertiajs/react';
import ActionButton from '../../../Components/admin/ActionButton';
import DetailList from '../../../Components/admin/DetailList';
import { Card, DataTable, EmptyState, PageHeader, StatusBadge } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatDate, formatMoney, formatNumber } from '../../../lib/format';

function Actions({ product }) {
  const base = `/admin/products/${product.id}`;
  const reason = [{ name: 'reason', label: 'Reason (shown to the seller)', type: 'textarea', required: true }];
  const canApprove = ['under_review', 'rejected', 'unpublished'].includes(product.status);

  return (
    <>
      {canApprove && <ActionButton url={`${base}/approve`} label="Approve & publish" variant="primary" message="The product goes live on the storefront." />}
      {['under_review', 'live'].includes(product.status) && <ActionButton url={`${base}/reject`} label="Reject" tone="danger" fields={reason} />}
      {product.status === 'live' && (
        <ActionButton url={`${base}/unpublish`} label="Unpublish" fields={[{ name: 'reason', label: 'Reason (optional)', type: 'textarea' }]} message="Hide the product from the storefront." />
      )}
      {product.status === 'violation' ? (
        <ActionButton url={`${base}/resolve`} label="Resolve violations" variant="primary" message="Close all open violations. The product goes back to review before it can be published." />
      ) : (
        <ActionButton
          url={`${base}/flag`}
          label="Flag violation"
          variant="danger"
          tone="danger"
          message="Record a policy violation and take the product down."
          fields={[
            { name: 'reason', label: 'Violation', type: 'text', required: true, placeholder: 'e.g. Counterfeit item, prohibited product' },
            { name: 'details', label: 'Details', type: 'textarea' },
          ]}
        />
      )}
    </>
  );
}

export default function Show({ product }) {
  return (
    <AdminLayout>
      <Head title={product.name} />
      <PageHeader
        title={product.name}
        description={<span className="inline-flex items-center gap-2"><StatusBadge status={product.status} /> by {product.shop?.name}</span>}
        breadcrumbs={[{ label: 'Products', href: '/admin/products' }, { label: product.name }]}
        actions={<Actions product={product} />}
      />

      {product.rejection_reason && (
        <div className="mb-6 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-orange-400">
          <strong>Note to seller:</strong> {product.rejection_reason}
        </div>
      )}

      <div className="grid gap-6 xl:grid-cols-3">
        <Card title="Details" className="xl:col-span-2">
          <DetailList
            items={[
              { label: 'Shop', value: <Link className="text-brand-600 hover:underline" href={`/admin/products?search=${encodeURIComponent(product.shop?.name ?? '')}`}>{product.shop?.name}</Link> },
              { label: 'Category', value: product.category },
              { label: 'Brand', value: product.brand },
              { label: 'Base price', value: formatMoney(product.base_price) },
              { label: 'Weight', value: `${formatNumber(product.weight_grams)} g` },
              { label: 'Sold', value: formatNumber(product.sold_count) },
              { label: 'Rating', value: `${Number(product.rating_avg).toFixed(2)} ★ (${product.rating_count})` },
              { label: 'Published', value: formatDate(product.published_at) },
            ]}
          />
          {product.description && (
            <div className="mt-6 border-t border-gray-100 pt-5 dark:border-gray-800">
              <h3 className="text-xs font-medium uppercase tracking-wide text-gray-500">Description</h3>
              <p className="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{product.description}</p>
            </div>
          )}
        </Card>

        <Card title="Images">
          {product.images.length ? (
            <div className="grid grid-cols-3 gap-2">
              {product.images.map((image) => (
                <img key={image.id} src={`/storage/${image.path}`} alt="" className={`aspect-square w-full rounded-lg object-cover ${image.is_primary ? 'ring-2 ring-brand-500' : ''}`} />
              ))}
            </div>
          ) : (
            <EmptyState title="No images" description="Products need images before approval." />
          )}
        </Card>
      </div>

      <Card className="mt-6" title="Variations & stock" bodyClassName="">
        <DataTable
          rows={product.variations}
          columns={[
            { key: 'label', label: 'Variation' },
            { key: 'sku', label: 'SKU', className: 'font-mono text-xs' },
            { key: 'price', label: 'Price', render: (row) => formatMoney(row.price) },
            { key: 'stock', label: 'On hand', render: (row) => formatNumber(row.stock) },
            { key: 'reserved', label: 'Reserved', render: (row) => formatNumber(row.reserved) },
            { key: 'available', label: 'Available', render: (row) => formatNumber(Math.max(0, row.stock - row.reserved)) },
            { key: 'is_active', label: 'Active', render: (row) => (row.is_active ? 'Yes' : 'No') },
          ]}
        />
      </Card>

      <Card className="mt-6" title="Violation history" bodyClassName="">
        <DataTable
          rows={product.violations}
          empty={<EmptyState title="No violations recorded" />}
          columns={[
            { key: 'reason', label: 'Violation' },
            { key: 'details', label: 'Details', render: (row) => row.details ?? '—' },
            { key: 'reporter', label: 'Raised by' },
            { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.status === 'open' ? 'violation' : 'completed'} /> },
            { key: 'created_at', label: 'Date', className: 'text-gray-500', render: (row) => formatDate(row.created_at) },
          ]}
        />
      </Card>
    </AdminLayout>
  );
}
