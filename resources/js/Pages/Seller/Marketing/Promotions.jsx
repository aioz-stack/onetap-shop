import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Badge, Button, Card, Checkbox, ConfirmDialog, DataTable, EmptyState, FormField, Modal, PageHeader, StatusBadge, TextInput } from '../../../Components/ui';
import SellerLayout from '../../../Layouts/SellerLayout';
import { formatDate } from '../../../lib/format';

function PromotionForm({ promotion, products, onClose }) {
  const editing = Boolean(promotion?.id);
  const form = useForm({
    name: promotion?.name ?? '',
    discount_percent: promotion?.discount_percent ?? 10,
    starts_at: promotion?.starts_at ?? '',
    ends_at: promotion?.ends_at ?? '',
    is_active: promotion?.is_active ?? true,
    product_ids: promotion?.product_ids ?? [],
  });
  const toggle = (id) => form.setData('product_ids', form.data.product_ids.includes(id) ? form.data.product_ids.filter((item) => item !== id) : [...form.data.product_ids, id]);
  const save = () => {
    const options = { preserveScroll: true, onSuccess: onClose };
    editing ? form.put(`/seller/promotions/${promotion.id}`, options) : form.post('/seller/promotions', options);
  };
  const productError = form.errors.product_ids ?? Object.entries(form.errors).find(([key]) => key.startsWith('product_ids.'))?.[1];

  return (
    <Modal open size="lg" onClose={onClose} title={editing ? 'Edit promotion' : 'New promotion'} footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button loading={form.processing} onClick={save}>Save promotion</Button></>}>
      <div className="grid gap-4 sm:grid-cols-2">
        <FormField label="Name" required error={form.errors.name}>{(id) => <TextInput id={id} value={form.data.name} placeholder="e.g. Payday sale" onChange={(event) => form.setData('name', event.target.value)} />}</FormField>
        <FormField label="Discount (%)" required error={form.errors.discount_percent}>{(id) => <TextInput id={id} type="number" min="1" max="90" value={form.data.discount_percent} onChange={(event) => form.setData('discount_percent', event.target.value)} />}</FormField>
        <FormField label="Starts" error={form.errors.starts_at}>{(id) => <TextInput id={id} type="datetime-local" value={form.data.starts_at} onChange={(event) => form.setData('starts_at', event.target.value)} />}</FormField>
        <FormField label="Ends" error={form.errors.ends_at}>{(id) => <TextInput id={id} type="datetime-local" value={form.data.ends_at} onChange={(event) => form.setData('ends_at', event.target.value)} />}</FormField>
        <fieldset className="sm:col-span-2">
          <legend className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-400">Products ({form.data.product_ids.length} selected)</legend>
          <div className="max-h-56 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-3 dark:border-gray-800">
            {products.map((product) => <Checkbox key={product.id} label={product.name} checked={form.data.product_ids.includes(product.id)} onChange={() => toggle(product.id)} />)}
          </div>
          {productError && <p className="mt-1 text-xs text-error-500">{productError}</p>}
        </fieldset>
        <div className="sm:col-span-2"><Checkbox label="Active" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} /></div>
        <p className="text-xs text-gray-500 sm:col-span-2">Prices drop automatically on the storefront while the promotion runs. If a product is in several promotions, the biggest discount applies.</p>
      </div>
    </Modal>
  );
}

export default function Promotions({ promotions, products }) {
  const [editing, setEditing] = useState(null);
  const [removing, setRemoving] = useState(null);

  return (
    <SellerLayout>
      <Head title="Promotions" />
      <PageHeader title="Discount promotions" description="Mark down selected products for a period of time." actions={<Button onClick={() => setEditing({})}>New promotion</Button>} />
      <Card bodyClassName="">
        <DataTable
          rows={promotions}
          empty={<EmptyState title="No promotions yet" action={<Button onClick={() => setEditing({})}>New promotion</Button>} />}
          columns={[
            { key: 'name', label: 'Promotion', render: (row) => <span className="font-medium text-gray-800 dark:text-white/90">{row.name}</span> },
            { key: 'discount_percent', label: 'Discount', render: (row) => `${Number(row.discount_percent)}% off` },
            { key: 'products', label: 'Products', render: (row) => <span className="block max-w-xs truncate text-xs text-gray-600 dark:text-gray-400">{row.products.join(', ')}</span> },
            { key: 'dates', label: 'Runs', className: 'text-xs text-gray-500 whitespace-nowrap', render: (row) => `${row.starts_at ? formatDate(row.starts_at) : 'Now'} – ${row.ends_at ? formatDate(row.ends_at) : 'No end'}` },
            { key: 'state', label: 'Status', render: (row) => (row.state === 'pending' ? <Badge tone="info">Scheduled</Badge> : <StatusBadge status={row.state} />) },
            { key: 'actions', label: '', className: 'text-right whitespace-nowrap', render: (row) => <><Button size="sm" variant="ghost" onClick={() => setEditing(row)}>Edit</Button><Button size="sm" variant="ghost" className="text-error-600" onClick={() => setRemoving(row)}>Delete</Button></> },
          ]}
        />
      </Card>
      {editing && <PromotionForm promotion={editing} products={products} onClose={() => setEditing(null)} />}
      <ConfirmDialog open={Boolean(removing)} onClose={() => setRemoving(null)} onConfirm={() => router.delete(`/seller/promotions/${removing.id}`, { preserveScroll: true, onFinish: () => setRemoving(null) })} title="Delete promotion?" message="Prices go back to normal immediately. Past orders keep what customers paid." confirmLabel="Delete" />
    </SellerLayout>
  );
}
