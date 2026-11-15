import { Head, Link } from '@inertiajs/react';
import ActionButton from '../../../Components/admin/ActionButton';
import StatusTabs from '../../../Components/admin/StatusTabs';
import { Card, DataTable, EmptyState, PageHeader, Pagination, StatusBadge } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatDateTime, headline } from '../../../lib/format';

export default function Index({ documents, status, counts }) {
  return (
    <AdminLayout>
      <Head title="Verification" />
      <PageHeader
        title="Verification"
        description="Review identity and business documents. Files are stored privately and only open for admins."
        breadcrumbs={[{ label: 'Admin', href: '/admin' }, { label: 'Verification' }]}
      />

      <Card bodyClassName="">
        <StatusTabs
          tabs={[
            { key: 'pending', label: 'Pending' },
            { key: 'approved', label: 'Approved' },
            { key: 'rejected', label: 'Rejected' },
          ]}
          active={status}
          counts={counts}
        />
        <DataTable
          rows={documents.data}
          empty={<EmptyState title={status === 'pending' ? 'Nothing to review' : `No ${status} documents`} description="Documents customers and sellers upload appear here." />}
          columns={[
            {
              key: 'user',
              label: 'Account',
              render: (row) => (
                <Link href={`/admin/users/${row.user?.id}`} className="hover:text-brand-600">
                  <span className="block font-medium text-gray-800 dark:text-white/90">{row.user?.name}</span>
                  <span className="text-xs text-gray-500">{row.user?.email}</span>
                </Link>
              ),
            },
            { key: 'type', label: 'Document', render: (row) => headline(row.type) },
            { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.status} /> },
            { key: 'created_at', label: 'Uploaded', className: 'whitespace-nowrap text-gray-500', render: (row) => formatDateTime(row.created_at) },
            { key: 'notes', label: 'Notes', render: (row) => row.notes ?? (row.reviewer ? `Reviewed by ${row.reviewer}` : '—') },
            {
              key: 'actions',
              label: '',
              className: 'text-right',
              render: (row) => (
                <div className="flex justify-end gap-2">
                  <a href={`/admin/verifications/${row.id}/file`} target="_blank" rel="noreferrer" className="inline-flex h-9 items-center rounded-lg px-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-500/10">
                    View file
                  </a>
                  {row.status === 'pending' && (
                    <>
                      <ActionButton url={`/admin/verifications/${row.id}/approve`} label="Approve" variant="primary" message="Mark this document as verified." fields={[{ name: 'notes', label: 'Note (optional)', type: 'text' }]} />
                      <ActionButton url={`/admin/verifications/${row.id}/reject`} label="Reject" tone="danger" fields={[{ name: 'notes', label: 'Reason', type: 'textarea', required: true }]} />
                    </>
                  )}
                </div>
              ),
            },
          ]}
        />
        <Pagination paginator={documents} />
      </Card>
    </AdminLayout>
  );
}
