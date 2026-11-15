import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Badge, Button, Card, Checkbox, ConfirmDialog, DataTable, EmptyState, FormField, Modal, PageHeader, StatusBadge, TextInput } from '../../Components/ui';
import LogisticsLayout from '../../Layouts/LogisticsLayout';
import { formatMoney } from '../../lib/format';

function AreaForm({ area, baseFee, onClose }) {
  const editing = Boolean(area?.id);
  const form = useForm({ region: area?.region ?? 'NCR', province: area?.province ?? 'Metro Manila', city: area?.city ?? '', barangay: area?.barangay ?? '', delivery_fee: area?.delivery_fee ?? '', is_active: area?.is_active ?? true });
  const field = (name, label, props = {}, required = true, hint) => (
    <FormField label={label} required={required} error={form.errors[name]} hint={hint}>
      {(id) => <TextInput id={id} value={form.data[name] ?? ''} error={form.errors[name]} onChange={(event) => form.setData(name, event.target.value)} {...props} />}
    </FormField>
  );
  const save = () => {
    const options = { preserveScroll: true, onSuccess: onClose };
    editing ? form.put(`/logistics/coverage/${area.id}`, options) : form.post('/logistics/coverage', options);
  };

  return (
    <Modal open onClose={onClose} title={editing ? 'Edit area' : 'Add area'} footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button loading={form.processing} onClick={save}>Save</Button></>}>
      <div className="grid gap-4 sm:grid-cols-2">
        {field('region', 'Region')}
        {field('province', 'Province')}
        {field('city', 'City / municipality')}
        {field('barangay', 'Barangay', {}, false, 'Leave empty to cover the whole city.')}
        {field('delivery_fee', 'Delivery fee (₱)', { type: 'number', min: 0, step: '0.01' }, false, `Empty uses your base fee (${formatMoney(baseFee)}).`)}
        <div className="flex items-end pb-3"><Checkbox label="Accepting deliveries" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} /></div>
      </div>
    </Modal>
  );
}

function RiderPay({ partner }) {
  const form = useForm({ rider_fee_per_delivery: partner.rider_fee_per_delivery ?? '0.00' });

  return (
    <Card className="mt-6" title="Rider pay" description="Paid to your rider for every parcel they deliver. Riders see it as their daily earnings.">
      <form
        className="flex flex-wrap items-end gap-3"
        onSubmit={(event) => {
          event.preventDefault();
          form.put('/logistics/rider-pay', { preserveScroll: true });
        }}
      >
        <FormField label="Pay per delivery (₱)" error={form.errors.rider_fee_per_delivery} className="w-full sm:w-56">
          {(id) => <TextInput id={id} type="number" min="0" step="0.01" value={form.data.rider_fee_per_delivery} error={form.errors.rider_fee_per_delivery} onChange={(event) => form.setData('rider_fee_per_delivery', event.target.value)} />}
        </FormField>
        <Button type="submit" loading={form.processing}>Save</Button>
      </form>
    </Card>
  );
}

export default function Coverage({ partner, areas }) {
  const [editing, setEditing] = useState(null);
  const [removing, setRemoving] = useState(null);

  return (
    <LogisticsLayout>
      <Head title="Service coverage" />
      <PageHeader title="Service coverage" description="Orders delivered to these areas are routed to you. When several partners cover an address, the cheapest fee wins." actions={<Button onClick={() => setEditing({})}>Add area</Button>} />
      <Card bodyClassName="">
        <DataTable
          rows={areas}
          empty={<EmptyState title="No coverage yet" description="Add the cities or barangays your riders deliver to." action={<Button onClick={() => setEditing({})}>Add area</Button>} />}
          columns={[
            { key: 'city', label: 'City', render: (row) => <span>{row.city}<span className="block text-xs text-gray-500">{row.province}, {row.region}</span></span> },
            { key: 'barangay', label: 'Barangay', render: (row) => row.barangay ?? <Badge tone="brand">Whole city</Badge> },
            { key: 'delivery_fee', label: 'Fee', render: (row) => (row.delivery_fee !== null ? formatMoney(row.delivery_fee) : <span className="text-gray-500">Base ({formatMoney(partner.base_delivery_fee)})</span>) },
            { key: 'is_active', label: 'Status', render: (row) => <StatusBadge status={row.is_active ? 'active' : 'inactive'} /> },
            { key: 'actions', label: '', className: 'text-right whitespace-nowrap', render: (row) => <><Button size="sm" variant="ghost" onClick={() => setEditing(row)}>Edit</Button><Button size="sm" variant="ghost" className="text-error-600" onClick={() => setRemoving(row)}>Remove</Button></> },
          ]}
        />
      </Card>
      <RiderPay partner={partner} />
      {editing && <AreaForm area={editing} baseFee={partner.base_delivery_fee} onClose={() => setEditing(null)} />}
      <ConfirmDialog open={Boolean(removing)} onClose={() => setRemoving(null)} onConfirm={() => router.delete(`/logistics/coverage/${removing.id}`, { preserveScroll: true, onFinish: () => setRemoving(null) })} title="Remove this area?" message="New orders there will no longer be routed to you. Shipments already assigned are not affected." confirmLabel="Remove" />
    </LogisticsLayout>
  );
}
