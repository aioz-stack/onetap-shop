import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { FilterBar } from '../../../Components/admin/Filters';
import { Button, Card, Checkbox, DataTable, EmptyState, Modal, PageHeader, Pagination, SearchInput, StatusBadge, TextInput, FormField } from '../../../Components/ui';
import SellerLayout from '../../../Layouts/SellerLayout';
import { formatDateTime, headline } from '../../../lib/format';
import { applyFilters } from '../../../lib/query';

function AdjustModal({ variation, onClose }) {
  const form = useForm({ stock: variation.stock, note: '' });

  return (
    <Modal
      open
      onClose={onClose}
      title={`Update stock · ${variation.product.name}`}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button loading={form.processing} onClick={() => form.post(`/seller/inventory/${variation.id}`, { preserveScroll: true, onSuccess: onClose })}>Save</Button>
        </>
      }
    >
      <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {variation.label} · {variation.reserved} reserved by open orders, so stock cannot go below that.
      </p>
      <div className="grid gap-4 sm:grid-cols-2">
        <FormField label="Units on hand" required error={form.errors.stock}>
          {(id) => <TextInput id={id} type="number" min={variation.reserved} value={form.data.stock} error={form.errors.stock} onChange={(event) => form.setData('stock', event.target.value)} />}
        </FormField>
        <FormField label="Note" error={form.errors.note}>
          {(id) => <TextInput id={id} placeholder="e.g. Morning bake" value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} />}
        </FormField>
      </div>
      {variation.movements.length > 0 && (
        <div className="mt-5">
          <p className="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">Recent movements</p>
          <ul className="space-y-1 text-xs text-gray-600 dark:text-gray-400">
            {variation.movements.map((movement, index) => (
              <li key={index} className="flex justify-between gap-3">
                <span>{headline(movement.type)} {movement.quantity !== 0 && `${movement.quantity > 0 ? '+' : ''}${movement.quantity}`} {movement.note && `· ${movement.note}`}</span>
                <span className="text-gray-400">{formatDateTime(movement.created_at)}</span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </Modal>
  );
}

export default function Inventory({ variations, filters }) {
  const [editing, setEditing] = useState(null);

  return (
    <SellerLayout>
      <Head title="Inventory" />
      <PageHeader title="Inventory" description="Stock per variation. Every change is recorded in the stock history." breadcrumbs={[{ label: 'Products', href: '/seller/products' }, { label: 'Inventory' }]} />
      <Card bodyClassName="">
        <FilterBar>
          <SearchInput value={filters.search} placeholder="Search products" className="w-full sm:w-72" />
          <Checkbox label="Low stock only (5 or fewer)" checked={filters.low} onChange={(event) => applyFilters({ low: event.target.checked ? 1 : null })} />
        </FilterBar>
        <DataTable
          rows={variations.data}
          empty={<EmptyState title="Nothing to show" />}
          columns={[
            { key: 'product', label: 'Product', render: (row) => <Link className="hover:text-brand-600" href={`/seller/products/${row.product.id}/edit`}>{row.product.name}<span className="block text-xs text-gray-500">{row.label} · <span className="font-mono">{row.sku}</span></span></Link> },
            { key: 'status', label: 'Product status', render: (row) => <StatusBadge status={row.product.status} /> },
            { key: 'stock', label: 'On hand' },
            { key: 'reserved', label: 'Reserved' },
            { key: 'available', label: 'Available', render: (row) => <span className={row.available === 0 ? 'font-semibold text-error-600' : row.available <= 5 ? 'font-semibold text-warning-600' : ''}>{row.available}</span> },
            { key: 'is_active', label: 'Selling', render: (row) => (row.is_active ? 'Yes' : 'No') },
            { key: 'actions', label: '', className: 'text-right', render: (row) => <Button size="sm" variant="secondary" onClick={() => setEditing(row)}>Update stock</Button> },
          ]}
        />
        <Pagination paginator={variations} />
      </Card>
      {editing && <AdjustModal variation={editing} onClose={() => setEditing(null)} />}
    </SellerLayout>
  );
}
