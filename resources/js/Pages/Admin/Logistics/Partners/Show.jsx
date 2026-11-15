import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { icon } from '../../../../Components/AppIcon';
import { Badge, Button, Card, Checkbox, ConfirmDialog, DataTable, EmptyState, FormField, Modal, PageHeader, StatCard, StatusBadge, TextInput } from '../../../../Components/ui';
import AdminLayout from '../../../../Layouts/AdminLayout';
import { formatMoney } from '../../../../lib/format';
import PartnerFields from './PartnerFields';

function AreaForm({ partner, area, onClose }) {
  const editing = Boolean(area?.id);
  const { data, setData, post, put, processing, errors } = useForm({
    region: area?.region ?? 'NCR',
    province: area?.province ?? 'Metro Manila',
    city: area?.city ?? '',
    barangay: area?.barangay ?? '',
    delivery_fee: area?.delivery_fee ?? '',
    is_active: area?.is_active ?? true,
  });

  const submit = (event) => {
    event.preventDefault();
    const url = `/admin/logistics/partners/${partner.id}/areas${editing ? `/${area.id}` : ''}`;
    const options = { preserveScroll: true, onSuccess: onClose };
    editing ? put(url, options) : post(url, options);
  };

  const field = (name, label, props = {}, required = false) => (
    <FormField label={label} required={required} error={errors[name]} hint={props.hint}>
      {(id) => <TextInput id={id} value={data[name] ?? ''} error={errors[name]} onChange={(event) => setData(name, event.target.value)} {...props} hint={undefined} />}
    </FormField>
  );

  return (
    <Modal
      open
      onClose={onClose}
      title={editing ? 'Edit coverage area' : 'Add coverage area'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="area-form" loading={processing}>
            Save
          </Button>
        </>
      }
    >
      <form id="area-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2" noValidate>
        {field('region', 'Region', {}, true)}
        {field('province', 'Province', {}, true)}
        {field('city', 'City / municipality', {}, true)}
        {field('barangay', 'Barangay', { hint: 'Leave empty to cover the whole city.' })}
        {field('delivery_fee', 'Delivery fee override (₱)', { type: 'number', min: 0, step: '0.01', hint: `Empty uses the base fee (${formatMoney(partner.base_delivery_fee)}).` })}
        <div className="flex items-end pb-3">
          <Checkbox label="Accepting deliveries" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} />
        </div>
      </form>
    </Modal>
  );
}

export default function Show({ partner, areas, riders, stats, statuses }) {
  const form = useForm({
    name: partner.name,
    code: partner.code,
    contact_phone: partner.contact_phone ?? '',
    contact_email: partner.contact_email ?? '',
    address: partner.address ?? '',
    base_delivery_fee: partner.base_delivery_fee,
    status: partner.status,
  });
  const [editingArea, setEditingArea] = useState(null);
  const [removingArea, setRemovingArea] = useState(null);

  return (
    <AdminLayout>
      <Head title={partner.name} />
      <PageHeader
        title={partner.name}
        description={<span className="inline-flex items-center gap-2"><StatusBadge status={partner.status} /> Code {partner.code} · operator {partner.owner?.name ?? '—'}</span>}
        breadcrumbs={[{ label: 'Logistics partners', href: '/admin/logistics/partners' }, { label: partner.name }]}
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <StatCard label="Awaiting pickup" value={stats.pendingPickups} icon={icon('box')} tone="warning" />
        <StatCard label="In transit" value={stats.inTransit} icon={icon('truck')} tone="brand" />
        <StatCard label="Delivered" value={stats.delivered} icon={icon('clipboard')} tone="success" />
        <StatCard label="Failed" value={stats.failed} icon={icon('receipt')} tone="error" />
        <StatCard label="Delivery fees earned" value={formatMoney(stats.fees)} icon={icon('wallet')} tone="orange" />
      </div>

      <div className="mt-6 grid gap-6 xl:grid-cols-5">
        <Card title="Service coverage" description="Barangays and cities this partner delivers to." className="xl:col-span-3" bodyClassName="" actions={<Button size="sm" onClick={() => setEditingArea({})}>Add area</Button>}>
          <DataTable
            rows={areas}
            empty={<EmptyState title="No coverage yet" description="Add the cities or barangays this partner serves." />}
            columns={[
              { key: 'city', label: 'City', render: (row) => <span>{row.city}<span className="block text-xs text-gray-500">{row.province}, {row.region}</span></span> },
              { key: 'barangay', label: 'Barangay', render: (row) => row.barangay ?? <Badge tone="brand">Whole city</Badge> },
              { key: 'delivery_fee', label: 'Fee', render: (row) => (row.delivery_fee !== null ? formatMoney(row.delivery_fee) : <span className="text-gray-500">Base ({formatMoney(partner.base_delivery_fee)})</span>) },
              { key: 'is_active', label: 'Active', render: (row) => (row.is_active ? <StatusBadge status="active" /> : <StatusBadge status="inactive" />) },
              {
                key: 'actions',
                label: '',
                className: 'text-right whitespace-nowrap',
                render: (row) => (
                  <>
                    <Button size="sm" variant="ghost" onClick={() => setEditingArea(row)}>
                      Edit
                    </Button>
                    <Button size="sm" variant="ghost" className="text-error-600" onClick={() => setRemovingArea(row)}>
                      Remove
                    </Button>
                  </>
                ),
              },
            ]}
          />
        </Card>

        <Card title="Company details" className="xl:col-span-2">
          <form
            onSubmit={(event) => {
              event.preventDefault();
              form.put(`/admin/logistics/partners/${partner.id}`, { preserveScroll: true });
            }}
            noValidate
          >
            <PartnerFields form={form} statuses={statuses} />
            <div className="mt-5 flex justify-end">
              <Button type="submit" loading={form.processing} disabled={!form.isDirty}>
                Save details
              </Button>
            </div>
          </form>
        </Card>
      </div>

      <Card className="mt-6" title="Riders" bodyClassName="" actions={<Link href={`/admin/logistics/riders?partner=${partner.id}`} className="text-sm text-brand-600">Manage riders</Link>}>
        <DataTable
          rows={riders}
          empty={<EmptyState title="No riders yet" description="The partner adds riders from the Logistics Panel." />}
          columns={[
            { key: 'name', label: 'Rider', render: (row) => <span>{row.name}<span className="block text-xs text-gray-500">{row.phone}</span></span> },
            { key: 'vehicle', label: 'Vehicle', render: (row) => `${row.vehicle_type ?? '—'} · ${row.plate_number ?? ''}` },
            { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.status} /> },
            { key: 'availability', label: 'Availability', render: (row) => <StatusBadge status={row.availability} /> },
          ]}
        />
      </Card>

      {editingArea && <AreaForm partner={partner} area={editingArea} onClose={() => setEditingArea(null)} />}

      <ConfirmDialog
        open={Boolean(removingArea)}
        onClose={() => setRemovingArea(null)}
        onConfirm={() => router.delete(`/admin/logistics/partners/${partner.id}/areas/${removingArea.id}`, { preserveScroll: true, onFinish: () => setRemovingArea(null) })}
        title="Remove coverage area?"
        message={`${partner.name} will stop receiving shipments for ${removingArea?.barangay ?? 'all of'} ${removingArea?.city ?? ''}.`}
        confirmLabel="Remove"
      />
    </AdminLayout>
  );
}
