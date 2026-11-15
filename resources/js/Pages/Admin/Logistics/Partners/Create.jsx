import { Head, useForm } from '@inertiajs/react';
import { Button, Card, FormField, PageHeader, PasswordInput, TextInput } from '../../../../Components/ui';
import AdminLayout from '../../../../Layouts/AdminLayout';
import PartnerFields from './PartnerFields';

export default function Create({ statuses }) {
  const form = useForm({
    name: '',
    code: '',
    contact_phone: '',
    contact_email: '',
    address: '',
    base_delivery_fee: '59.00',
    rider_fee_per_delivery: '35.00',
    status: 'active',
    owner_name: '',
    owner_username: '',
    owner_email: '',
    owner_password: '',
  });
  const { data, setData, errors, post, processing } = form;

  const owner = (name, label, props = {}) => (
    <FormField label={label} required error={errors[name]}>
      {(id) => <TextInput id={id} value={data[name]} error={errors[name]} onChange={(event) => setData(name, event.target.value)} {...props} />}
    </FormField>
  );

  return (
    <AdminLayout>
      <Head title="Add logistics partner" />
      <PageHeader title="Add logistics partner" breadcrumbs={[{ label: 'Logistics partners', href: '/admin/logistics/partners' }, { label: 'Add' }]} />

      <form
        onSubmit={(event) => {
          event.preventDefault();
          post('/admin/logistics/partners');
        }}
        noValidate
        className="max-w-3xl space-y-6"
      >
        <Card title="Company">
          <PartnerFields form={form} statuses={statuses} />
        </Card>

        <Card title="Operator account" description="This person signs in to the Logistics Panel to manage riders and shipments.">
          <div className="grid gap-5 sm:grid-cols-2">
            {owner('owner_name', 'Full name')}
            {owner('owner_username', 'Username')}
            {owner('owner_email', 'Email', { type: 'email' })}
            <FormField label="Temporary password" required error={errors.owner_password} hint="Share it with the operator securely.">
              {(id) => <PasswordInput id={id} autoComplete="new-password" value={data.owner_password} error={errors.owner_password} onChange={(event) => setData('owner_password', event.target.value)} />}
            </FormField>
          </div>
        </Card>

        <div className="flex justify-end gap-3">
          <Button variant="secondary" href="/admin/logistics/partners">
            Cancel
          </Button>
          <Button type="submit" loading={processing}>
            Create partner
          </Button>
        </div>
      </form>
    </AdminLayout>
  );
}
