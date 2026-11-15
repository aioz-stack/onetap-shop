import { Head, useForm } from '@inertiajs/react';
import { Button, Card, Checkbox, FormField, PageHeader, PasswordInput, Select, TextInput } from '../../../Components/ui';
import LogisticsLayout from '../../../Layouts/LogisticsLayout';

export default function Form({ rider, vehicles }) {
  const editing = Boolean(rider);
  const form = useForm({
    name: rider?.name ?? '',
    username: rider?.username ?? '',
    email: rider?.email ?? '',
    phone: rider?.phone ?? '',
    password: '',
    vehicle_type: rider?.vehicle_type ?? 'motorcycle',
    plate_number: rider?.plate_number ?? '',
    license_number: rider?.license_number ?? '',
    activate: false,
  });
  const text = (name, label, props = {}, required = true) => (
    <FormField label={label} required={required} error={form.errors[name]}>
      {(id) => <TextInput id={id} value={form.data[name] ?? ''} error={form.errors[name]} onChange={(event) => form.setData(name, event.target.value)} {...props} />}
    </FormField>
  );

  return (
    <LogisticsLayout>
      <Head title={editing ? `Edit ${rider.name}` : 'Add rider'} />
      <PageHeader title={editing ? `Edit ${rider.name}` : 'Add rider'} breadcrumbs={[{ label: 'Riders', href: '/logistics/riders' }, { label: editing ? 'Edit' : 'Add' }]} />
      <form
        noValidate
        className="max-w-3xl space-y-6"
        onSubmit={(event) => {
          event.preventDefault();
          editing ? form.put(`/logistics/riders/${rider.id}`) : form.post('/logistics/riders');
        }}
      >
        <Card title="Account" description="The rider signs in to the Onetap Rider app with these details.">
          <div className="grid gap-4 sm:grid-cols-2">
            {text('name', 'Full name', { autoComplete: 'off' })}
            {editing ? <FormField label="Username">{(id) => <TextInput id={id} value={rider.username} disabled />}</FormField> : text('username', 'Username', { autoComplete: 'off' })}
            {text('email', 'Email', { type: 'email', autoComplete: 'off' })}
            {text('phone', 'Mobile number', { type: 'tel', placeholder: '09171234567' })}
            <FormField label={editing ? 'New password' : 'Temporary password'} required={!editing} error={form.errors.password} hint={editing ? 'Leave blank to keep the current one.' : 'Share it with the rider securely.'}>
              {(id) => <PasswordInput id={id} autoComplete="new-password" value={form.data.password} error={form.errors.password} onChange={(event) => form.setData('password', event.target.value)} />}
            </FormField>
          </div>
        </Card>
        <Card title="Vehicle">
          <div className="grid gap-4 sm:grid-cols-3">
            <FormField label="Vehicle" required error={form.errors.vehicle_type}>
              {(id) => <Select id={id} value={form.data.vehicle_type} options={vehicles} onChange={(event) => form.setData('vehicle_type', event.target.value)} />}
            </FormField>
            {text('plate_number', 'Plate number', {}, false)}
            {text('license_number', "Driver's license no.", {}, false)}
          </div>
        </Card>
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          {!editing ? <Checkbox label="Documents checked — activate this rider now" checked={form.data.activate} onChange={(event) => form.setData('activate', event.target.checked)} /> : <span />}
          <div className="flex gap-3">
            <Button variant="secondary" href={editing ? `/logistics/riders/${rider.id}` : '/logistics/riders'}>Cancel</Button>
            <Button type="submit" loading={form.processing}>{editing ? 'Save rider' : 'Add rider'}</Button>
          </div>
        </div>
      </form>
    </LogisticsLayout>
  );
}
