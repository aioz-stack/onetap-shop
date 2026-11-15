import { Head, Link } from '@inertiajs/react';
import ActionButton from '../../../Components/admin/ActionButton';
import DetailList from '../../../Components/admin/DetailList';
import { Avatar } from '../../../Components/UserMenu';
import { Badge, Button, Card, EmptyState, PageHeader, StatusBadge } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatDate, formatDateTime, headline } from '../../../lib/format';

export default function Show({ user, isSelf }) {
  return (
    <AdminLayout>
      <Head title={user.name} />
      <PageHeader
        title={user.name}
        description={`@${user.username}`}
        breadcrumbs={[{ label: 'Users', href: '/admin/users' }, { label: user.name }]}
        actions={
          <>
            <Button variant="secondary" size="sm" href={`/admin/users/${user.id}/edit`}>
              Edit
            </Button>
            {!isSelf &&
              (user.status === 'suspended' ? (
                <ActionButton
                  url={`/admin/users/${user.id}/reactivate`}
                  label="Reactivate"
                  message={`${user.name} will be able to sign in again.`}
                />
              ) : (
                <ActionButton
                  url={`/admin/users/${user.id}/suspend`}
                  label="Suspend"
                  variant="danger"
                  tone="danger"
                  message="The account is signed out everywhere, its API tokens are revoked, and it cannot sign in until reactivated."
                  fields={[{ name: 'reason', label: 'Reason', type: 'textarea', required: true }]}
                />
              ))}
          </>
        }
      />

      <div className="grid gap-6 xl:grid-cols-3">
        <Card title="Account" className="xl:col-span-2">
          <div className="mb-5 flex items-center gap-4">
            <Avatar user={user} size="h-14 w-14" />
            <div className="flex flex-wrap gap-1.5">
              <StatusBadge status={user.status} />
              {user.roles.map((role) => (
                <Badge key={role} tone="brand">
                  {headline(role)}
                </Badge>
              ))}
            </div>
          </div>
          <DetailList
            items={[
              { label: 'Email', value: user.email },
              { label: 'Mobile', value: user.phone },
              { label: 'Email verified', value: user.email_verified_at ? formatDate(user.email_verified_at) : 'Not verified' },
              { label: 'Orders placed', value: user.ordersCount },
              { label: 'Last login', value: formatDateTime(user.last_login_at) },
              { label: 'Joined', value: formatDate(user.created_at) },
              user.status === 'suspended' && { label: 'Suspended', value: `${formatDateTime(user.suspended_at)} — ${user.suspension_reason ?? ''}` },
            ]}
          />
        </Card>

        <div className="space-y-6">
          {user.seller && (
            <Card title="Seller">
              <DetailList
                columns={1}
                items={[
                  { label: 'Business', value: <Link className="text-brand-600 hover:underline" href={`/admin/sellers/${user.seller.id}`}>{user.seller.business_name}</Link> },
                  { label: 'Application', value: <StatusBadge status={user.seller.status} /> },
                  { label: 'Shop', value: user.seller.shop ? `${user.seller.shop.name} (${headline(user.seller.shop.status)})` : 'Not created yet' },
                ]}
              />
            </Card>
          )}
          {user.rider && (
            <Card title="Rider">
              <DetailList
                columns={1}
                items={[
                  { label: 'Partner', value: user.rider.partner },
                  { label: 'Status', value: <StatusBadge status={user.rider.status} /> },
                  { label: 'Availability', value: <StatusBadge status={user.rider.availability} /> },
                ]}
              />
            </Card>
          )}
          {user.logisticsPartner && (
            <Card title="Logistics partner">
              <Link className="text-sm text-brand-600 hover:underline" href={`/admin/logistics/partners/${user.logisticsPartner.id}`}>
                {user.logisticsPartner.name}
              </Link>
            </Card>
          )}
          <Card title="Verification documents" actions={<Link href="/admin/verifications" className="text-sm text-brand-600">Review queue</Link>}>
            {user.documents.length ? (
              <ul className="space-y-2 text-sm">
                {user.documents.map((document) => (
                  <li key={document.id} className="flex items-center justify-between gap-3">
                    <span className="text-gray-700 dark:text-gray-300">{headline(document.type)}</span>
                    <StatusBadge status={document.status} />
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-sm text-gray-500">No documents uploaded.</p>
            )}
          </Card>
        </div>

        <Card title="Addresses" className="xl:col-span-3">
          {user.addresses.length ? (
            <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              {user.addresses.map((address) => (
                <li key={address.id} className="rounded-xl border border-gray-200 p-4 text-sm dark:border-gray-800">
                  <p className="font-medium text-gray-800 dark:text-white/90">
                    {address.label ?? 'Address'} {address.is_default && <Badge tone="brand">Default</Badge>}
                  </p>
                  <p className="mt-1 text-gray-600 dark:text-gray-400">
                    {address.recipient_name} · {address.phone}
                  </p>
                  <p className="text-gray-600 dark:text-gray-400">{[address.street, address.barangay, address.city, address.province].filter(Boolean).join(', ')}</p>
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState title="No saved addresses" />
          )}
        </Card>
      </div>
    </AdminLayout>
  );
}
