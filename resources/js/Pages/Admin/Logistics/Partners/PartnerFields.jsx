import { FormField, Select, TextInput } from '../../../../Components/ui';

/**
 * Partner detail fields shared by the create page and the edit form.
 */
export default function PartnerFields({ form, statuses }) {
  const { data, setData, errors } = form;

  const text = (name, label, props = {}, required = false) => (
    <FormField label={label} required={required} error={errors[name]}>
      {(id) => <TextInput id={id} value={data[name] ?? ''} error={errors[name]} onChange={(event) => setData(name, event.target.value)} {...props} />}
    </FormField>
  );

  return (
    <div className="grid gap-5 sm:grid-cols-2">
      {text('name', 'Company name', {}, true)}
      {text('code', 'Code', { placeholder: 'e.g. OTX' }, true)}
      {text('contact_phone', 'Contact phone', { type: 'tel' })}
      {text('contact_email', 'Contact email', { type: 'email' })}
      <div className="sm:col-span-2">{text('address', 'Office address')}</div>
      {text('base_delivery_fee', 'Base delivery fee (₱)', { type: 'number', min: 0, step: '0.01' }, true)}
      <FormField label="Status" required error={errors.status}>
        {(id) => <Select id={id} value={data.status} options={statuses} onChange={(event) => setData('status', event.target.value)} />}
      </FormField>
    </div>
  );
}
