import { useForm } from '@inertiajs/react';
import { Button, Checkbox, FormField, Modal, TextInput } from '../ui';

/**
 * Add or edit a delivery address in a modal. `returnTo` sends the user back to checkout after saving.
 */
export default function AddressForm({ address, onClose, returnTo }) {
  const editing = Boolean(address?.id);
  const { data, setData, post, put, processing, errors } = useForm({
    label: address?.label ?? 'Home',
    recipient_name: address?.recipient_name ?? '',
    phone: address?.phone ?? '',
    street: address?.street ?? '',
    barangay: address?.barangay ?? '',
    city: address?.city ?? '',
    province: address?.province ?? 'Metro Manila',
    region: address?.region ?? 'NCR',
    postal_code: address?.postal_code ?? '',
    is_default: address?.is_default ?? false,
  });

  const submit = (event) => {
    event.preventDefault();
    const options = { preserveScroll: true, onSuccess: onClose };
    editing ? put(`/account/addresses/${address.id}`, options) : post(`/account/addresses${returnTo ? `?return=${returnTo}` : ''}`, options);
  };

  const field = (name, label, props = {}, required = true, className = '') => (
    <FormField label={label} required={required} error={errors[name]} className={className}>
      {(id) => <TextInput id={id} value={data[name] ?? ''} error={errors[name]} onChange={(event) => setData(name, event.target.value)} {...props} />}
    </FormField>
  );

  return (
    <Modal
      open
      onClose={onClose}
      size="lg"
      title={editing ? 'Edit address' : 'New address'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="address-form" loading={processing}>
            Save address
          </Button>
        </>
      }
    >
      <form id="address-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2" noValidate>
        {field('recipient_name', 'Recipient name', { autoComplete: 'name' })}
        {field('phone', 'Mobile number', { type: 'tel', autoComplete: 'tel', placeholder: '09171234567' })}
        {field('street', 'House no., street, building', { autoComplete: 'address-line1' }, true, 'sm:col-span-2')}
        {field('barangay', 'Barangay')}
        {field('city', 'City / municipality', { autoComplete: 'address-level2' })}
        {field('province', 'Province', { autoComplete: 'address-level1' })}
        {field('region', 'Region')}
        {field('postal_code', 'Postal code', { inputMode: 'numeric', autoComplete: 'postal-code' }, false)}
        {field('label', 'Label', { placeholder: 'Home, Office…' }, false)}
        <div className="sm:col-span-2">
          <Checkbox label="Use as my default address" checked={data.is_default} onChange={(event) => setData('is_default', event.target.checked)} />
        </div>
      </form>
    </Modal>
  );
}

export function AddressText({ address }) {
  if (!address) {
    return null;
  }

  return (
    <span>
      <span className="font-medium text-gray-800 dark:text-white/90">{address.recipient_name}</span>
      <span className="text-gray-500"> · {address.phone}</span>
      <span className="block text-gray-600 dark:text-gray-400">{[address.street, address.barangay, address.city, address.province].filter(Boolean).join(', ')}</span>
    </span>
  );
}
