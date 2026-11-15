import { Head, useForm } from '@inertiajs/react';
import { Button, Card, DataTable, EmptyState, FormField, Select, StatusBadge } from '../../../Components/ui';
import AccountLayout from '../../../Layouts/AccountLayout';
import { formatDate } from '../../../lib/format';

export default function Verification({ documents, types }) {
  const form = useForm({ type: types[0]?.value ?? '', file: null });

  return (
    <AccountLayout title="Verification">
      <Head title="Verification" />

      <Card title="Upload a document" description="Verified accounts build trust with sellers. Files are stored privately and only reviewed by Onetap staff.">
        <form
          className="grid max-w-xl gap-4"
          noValidate
          onSubmit={(event) => {
            event.preventDefault();
            form.post('/account/verification', { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset('file') });
          }}
        >
          <FormField label="Document type" required error={form.errors.type}>
            {(id) => <Select id={id} value={form.data.type} options={types} onChange={(event) => form.setData('type', event.target.value)} />}
          </FormField>
          <FormField label="File" required error={form.errors.file} hint="JPG, PNG or PDF, up to 5 MB.">
            {(id) => (
              <input
                id={id}
                type="file"
                accept="image/jpeg,image/png,application/pdf"
                onChange={(event) => form.setData('file', event.target.files[0] ?? null)}
                className="block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100 dark:text-gray-400"
              />
            )}
          </FormField>
          <div>
            <Button type="submit" loading={form.processing} disabled={!form.data.file}>Upload</Button>
          </div>
        </form>
      </Card>

      <Card className="mt-5" title="Your documents" bodyClassName="">
        <DataTable
          rows={documents}
          empty={<EmptyState title="Nothing uploaded yet" />}
          columns={[
            { key: 'type_label', label: 'Document' },
            { key: 'status', label: 'Status', render: (row) => <span><StatusBadge status={row.status} />{row.notes && <span className="block text-xs text-error-500">{row.notes}</span>}</span> },
            { key: 'created_at', label: 'Uploaded', className: 'text-gray-500', render: (row) => formatDate(row.created_at) },
          ]}
        />
      </Card>
    </AccountLayout>
  );
}
