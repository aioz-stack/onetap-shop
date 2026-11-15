import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AddressForm, { AddressText } from '../../../Components/store/AddressForm';
import { Badge, Button, ConfirmDialog, EmptyState } from '../../../Components/ui';
import AccountLayout from '../../../Layouts/AccountLayout';

export default function Addresses({ addresses }) {
  const [editing, setEditing] = useState(null);
  const [removing, setRemoving] = useState(null);

  return (
    <AccountLayout title="My addresses" actions={<Button size="sm" onClick={() => setEditing({})}>Add address</Button>}>
      <Head title="My addresses" />

      {addresses.length ? (
        <ul className="grid gap-4 md:grid-cols-2">
          {addresses.map((address) => (
            <li key={address.id} className="rounded-2xl border border-gray-200 bg-white p-4 text-sm dark:border-gray-800 dark:bg-white/[0.03]">
              <div className="mb-2 flex items-center gap-2">
                <span className="font-semibold text-gray-800 dark:text-white/90">{address.label ?? 'Address'}</span>
                {address.is_default && <Badge tone="brand">Default</Badge>}
              </div>
              <AddressText address={address} />
              <div className="mt-4 flex flex-wrap gap-2">
                <Button size="sm" variant="secondary" onClick={() => setEditing(address)}>Edit</Button>
                {!address.is_default && (
                  <Button size="sm" variant="ghost" onClick={() => router.post(`/account/addresses/${address.id}/default`, {}, { preserveScroll: true })}>
                    Set as default
                  </Button>
                )}
                <Button size="sm" variant="ghost" className="text-error-600" onClick={() => setRemoving(address)}>Remove</Button>
              </div>
            </li>
          ))}
        </ul>
      ) : (
        <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
          <EmptyState title="No saved addresses" description="Add where you want your orders delivered." action={<Button onClick={() => setEditing({})}>Add address</Button>} />
        </div>
      )}

      {editing && <AddressForm address={editing} onClose={() => setEditing(null)} />}
      <ConfirmDialog
        open={Boolean(removing)}
        onClose={() => setRemoving(null)}
        onConfirm={() => router.delete(`/account/addresses/${removing.id}`, { preserveScroll: true, onFinish: () => setRemoving(null) })}
        title="Remove this address?"
        message="Orders already placed keep their delivery address."
        confirmLabel="Remove"
      />
    </AccountLayout>
  );
}
