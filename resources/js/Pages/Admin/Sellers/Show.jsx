import { Head, Link } from '@inertiajs/react';
import { icon } from '../../../Components/AppIcon';
import ActionButton from '../../../Components/admin/ActionButton';
import DetailList from '../../../Components/admin/DetailList';
import { Card, DataTable, EmptyState, PageHeader, StatCard, StatusBadge } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatDate, formatMoney, headline } from '../../../lib/format';

function Actions({ seller }) {
  const base = `/admin/sellers/${seller.id}`;
  const reason = [{ name: 'reason', label: 'Reason (shared with the seller)', type: 'textarea', required: true }];

  switch (seller.status) {
    case 'pending':
      return (
        <>
          <ActionButton url={`${base}/approve`} label="Approve" variant="primary" message="The applicant gets the seller role and their shop opens." />
          <ActionButton url={`${base}/reject`} label="Reject" variant="danger" tone="danger" fields={reason} />
        </>
      );
    case 'rejected':
      return <ActionButton url={`${base}/approve`} label="Approve anyway" variant="primary" message="Approve this previously rejected application." />;
    case 'approved':
      return (
        <ActionButton
          url={`${base}/suspend`}
          label="Suspend seller"
          variant="danger"
          tone="danger"
          message="The shop is suspended, live products are unpublished, and the seller loses Seller Center access."
          fields={reason}
        />
      );
    case 'suspended':
      return <ActionButton url={`${base}/reinstate`} label="Reinstate" variant="primary" message="Restore the shop and Seller Center access. Products stay unpublished until the seller republishes them." />;
    default:
      return null;
  }
}

export default function Show({ seller, performance, violations }) {
  return (
    <AdminLayout>
      <Head title={seller.business_name} />
      <PageHeader
        title={seller.business_name}
        description={<span className="inline-flex items-center gap-2">Seller application <StatusBadge status={seller.status} /></span>}
        breadcrumbs={[{ label: 'Sellers', href: '/admin/sellers' }, { label: seller.business_name }]}
        actions={<Actions seller={seller} />}
      />

      {seller.rejection_reason && (
        <div className="mb-6 rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-500">
          <strong>{seller.status === 'suspended' ? 'Suspension reason' : 'Rejection reason'}:</strong> {seller.rejection_reason}
        </div>
      )}

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Completed sales" value={formatMoney(performance.gmv)} icon={icon('wallet')} tone="brand" />
        <StatCard label="Orders" value={performance.orders} icon={icon('receipt')} tone="orange" hint={`${performance.completedOrders} completed · ${performance.cancellationRate}% cancelled`} />
        <StatCard label="Products" value={performance.products} icon={icon('box')} tone="gray" hint={`${performance.liveProducts} live`} />
        <StatCard label="Shop rating" value={`${Number(performance.rating).toFixed(2)} ★`} icon={icon('star')} tone="warning" />
      </div>

      <div className="mt-6 grid gap-6 xl:grid-cols-2">
        <Card title="Business">
          <DetailList
            items={[
              { label: 'Owner', value: <Link className="text-brand-600 hover:underline" href={`/admin/users/${seller.owner.id}`}>{seller.owner.name}</Link> },
              { label: 'Owner account', value: <StatusBadge status={seller.owner.status} /> },
              { label: 'Email', value: seller.owner.email },
              { label: 'Mobile', value: seller.owner.phone },
              { label: 'Business type', value: headline(seller.business_type ?? '') },
              { label: 'TIN', value: seller.tin },
              { label: 'Applied', value: formatDate(seller.created_at) },
              { label: 'Approved', value: seller.approved_at ? `${formatDate(seller.approved_at)} by ${seller.approver ?? '—'}` : '—' },
            ]}
          />
        </Card>
        <Card title="Shop">
          {seller.shop ? (
            <DetailList
              items={[
                { label: 'Name', value: seller.shop.name },
                { label: 'Status', value: <StatusBadge status={seller.shop.status} /> },
                { label: 'Category', value: seller.shop.category },
                { label: 'Vacation mode', value: seller.shop.vacation_mode ? 'On' : 'Off' },
                { label: 'Pickup address', value: seller.shop.pickup },
                { label: 'Description', value: seller.shop.description },
              ]}
            />
          ) : (
            <EmptyState title="No shop yet" description="The shop is created when the application is approved." />
          )}
        </Card>
      </div>

      <Card className="mt-6" title="Violations" description="Policy issues raised against this seller's products." bodyClassName="">
        <DataTable
          rows={violations}
          empty={<EmptyState title="No violations" description="This seller has a clean record." />}
          columns={[
            { key: 'product', label: 'Product', render: (row) => <Link className="hover:text-brand-600" href={`/admin/products/${row.product?.id}`}>{row.product?.name ?? 'Deleted product'}</Link> },
            { key: 'reason', label: 'Reason' },
            { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.status === 'open' ? 'violation' : 'completed'} /> },
            { key: 'created_at', label: 'Raised', className: 'text-gray-500', render: (row) => formatDate(row.created_at) },
          ]}
        />
      </Card>
    </AdminLayout>
  );
}
