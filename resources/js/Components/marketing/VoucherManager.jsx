import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Badge, Button, Card, Checkbox, ConfirmDialog, DataTable, EmptyState, FormField, Modal, PageHeader, Select, StatusBadge, TextInput } from '../ui';
import { formatDate, formatMoney } from '../../lib/format';

function VoucherForm({ voucher, types, baseUrl, fundingNote, onClose }) {
  const editing = Boolean(voucher?.id);
  const form = useForm({
    code: voucher?.code ?? '',
    name: voucher?.name ?? '',
    type: voucher?.type ?? 'fixed',
    value: voucher?.value ?? '',
    min_spend: voucher?.min_spend ?? '',
    max_discount: voucher?.max_discount ?? '',
    usage_limit: voucher?.usage_limit ?? '',
    per_user_limit: voucher?.per_user_limit ?? 1,
    starts_at: voucher?.starts_at ?? '',
    ends_at: voucher?.ends_at ?? '',
    is_active: voucher?.is_active ?? true,
  });
  const field = (name, label, props = {}, hint) => (
    <FormField label={label} error={form.errors[name]} hint={hint}>
      {(id) => <TextInput id={id} value={form.data[name] ?? ''} error={form.errors[name]} onChange={(event) => form.setData(name, event.target.value)} {...props} />}
    </FormField>
  );
  const save = () => {
    const options = { preserveScroll: true, onSuccess: onClose };
    editing ? form.put(`${baseUrl}/${voucher.id}`, options) : form.post(baseUrl, options);
  };

  return (
    <Modal open size="lg" onClose={onClose} title={editing ? `Edit ${voucher.code}` : 'New voucher'} footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button loading={form.processing} onClick={save}>Save voucher</Button></>}>
      <div className="grid gap-4 sm:grid-cols-2">
        {field('code', 'Code', { className: 'uppercase', placeholder: 'e.g. BAKERY50', maxLength: 20 }, 'Customers type this at checkout.')}
        {field('name', 'Name', { placeholder: 'e.g. Weekend treat' })}
        <FormField label="Type" error={form.errors.type}>
          {(id) => <Select id={id} value={form.data.type} options={types} onChange={(event) => form.setData('type', event.target.value)} />}
        </FormField>
        {form.data.type !== 'free_shipping' && field('value', form.data.type === 'percent' ? 'Discount (%)' : 'Discount (₱)', { type: 'number', min: 1, step: '0.01' })}
        {field('min_spend', 'Minimum spend (₱)', { type: 'number', min: 0, step: '0.01' })}
        {form.data.type !== 'fixed' && field('max_discount', form.data.type === 'free_shipping' ? 'Cover delivery up to (₱)' : 'Maximum discount (₱)', { type: 'number', min: 1, step: '0.01', placeholder: 'No cap' })}
        {field('usage_limit', 'Total uses', { type: 'number', min: 1, placeholder: 'Unlimited' })}
        {field('per_user_limit', 'Uses per customer', { type: 'number', min: 1, max: 20 })}
        {field('starts_at', 'Starts', { type: 'datetime-local' })}
        {field('ends_at', 'Ends', { type: 'datetime-local' })}
        <div className="sm:col-span-2"><Checkbox label="Active" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} /></div>
        <p className="text-xs text-gray-500 sm:col-span-2">{fundingNote}</p>
      </div>
    </Modal>
  );
}

/**
 * Voucher list + form, shared by the Seller Center (shop vouchers) and the admin panel (platform vouchers).
 */
export default function VoucherManager({ vouchers, types, baseUrl, title, description, emptyDescription, fundingNote }) {
  const [editing, setEditing] = useState(null);
  const [removing, setRemoving] = useState(null);

  return (
    <>
      <PageHeader title={title} description={description} actions={<Button onClick={() => setEditing({})}>New voucher</Button>} />
      <Card bodyClassName="">
        <DataTable
          rows={vouchers}
          empty={<EmptyState title="No vouchers yet" description={emptyDescription} action={<Button onClick={() => setEditing({})}>New voucher</Button>} />}
          columns={[
            { key: 'code', label: 'Code', render: (row) => <span><span className="font-mono font-semibold text-gray-800 dark:text-white/90">{row.code}</span><span className="block text-xs text-gray-500">{row.name}</span></span> },
            { key: 'label', label: 'Offer', render: (row) => <span>{row.label}{Number(row.min_spend) > 0 && <span className="block text-xs text-gray-500">Min. spend {formatMoney(row.min_spend)}</span>}</span> },
            { key: 'used', label: 'Used', render: (row) => `${row.used_count}${row.usage_limit ? ` / ${row.usage_limit}` : ''}` },
            { key: 'discount_given', label: 'Discount given', render: (row) => formatMoney(row.discount_given) },
            { key: 'dates', label: 'Valid', className: 'text-xs text-gray-500 whitespace-nowrap', render: (row) => `${row.starts_at ? formatDate(row.starts_at) : 'Now'} – ${row.ends_at ? formatDate(row.ends_at) : 'No end'}` },
            { key: 'state', label: 'Status', render: (row) => (row.state === 'pending' ? <Badge tone="info">Scheduled</Badge> : <StatusBadge status={row.state} />) },
            { key: 'actions', label: '', className: 'text-right whitespace-nowrap', render: (row) => <><Button size="sm" variant="ghost" onClick={() => setEditing(row)}>Edit</Button><Button size="sm" variant="ghost" className="text-error-600" onClick={() => setRemoving(row)}>Delete</Button></> },
          ]}
        />
      </Card>
      {editing && <VoucherForm voucher={editing} types={types} baseUrl={baseUrl} fundingNote={fundingNote} onClose={() => setEditing(null)} />}
      <ConfirmDialog open={Boolean(removing)} onClose={() => setRemoving(null)} onConfirm={() => router.delete(`${baseUrl}/${removing.id}`, { preserveScroll: true, onFinish: () => setRemoving(null) })} title={`Delete ${removing?.code ?? ''}?`} message="Vouchers that were already used are switched off instead, to keep order records." confirmLabel="Delete" />
    </>
  );
}
