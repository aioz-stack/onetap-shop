import { Head, useForm } from '@inertiajs/react';
import { Button, Card, Checkbox, FormField, Select, StatusBadge, Textarea, TextInput } from '../../../Components/ui';
import AccountLayout from '../../../Layouts/AccountLayout';

const businessTypes = [
  { value: 'home_based', label: 'Home-based business' },
  { value: 'sole_proprietor', label: 'Sole proprietor' },
  { value: 'partnership', label: 'Partnership' },
  { value: 'corporation', label: 'Corporation' },
  { value: 'cooperative', label: 'Cooperative' },
];

export default function SellerApplication({ application, shopCategories }) {
  const status = application?.status;
  const locked = status === 'pending' || status === 'approved' || status === 'suspended';
  const shop = application?.shop ?? {};
  const form = useForm({
    business_name: application?.business_name ?? '',
    business_type: application?.business_type ?? 'home_based',
    tin: application?.tin ?? '',
    shop_name: shop.name ?? '',
    shop_category_id: shop.shop_category_id ?? '',
    description: shop.description ?? '',
    pickup_street: shop.pickup_street ?? '',
    pickup_barangay: shop.pickup_barangay ?? '',
    pickup_city: shop.pickup_city ?? '',
    pickup_province: shop.pickup_province ?? 'Metro Manila',
    pickup_region: shop.pickup_region ?? 'NCR',
    pickup_phone: shop.pickup_phone ?? '',
    agree: false,
  });

  const field = (name, label, props = {}, required = true, className = '') => (
    <FormField label={label} required={required} error={form.errors[name]} className={className}>
      {(id) => <TextInput id={id} disabled={locked} value={form.data[name] ?? ''} error={form.errors[name]} onChange={(event) => form.setData(name, event.target.value)} {...props} />}
    </FormField>
  );

  return (
    <AccountLayout title="Sell on Onetap">
      <Head title="Sell on Onetap" />

      {status && (
        <div className={`mb-5 rounded-2xl border px-4 py-3 text-sm ${status === 'rejected' ? 'border-error-200 bg-error-50 text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-500' : 'border-brand-200 bg-brand-50 text-brand-800 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-400'}`}>
          <p className="flex items-center gap-2 font-medium">
            Application status: <StatusBadge status={status} />
          </p>
          {status === 'pending' && <p className="mt-1">We are reviewing your application. This usually takes 1–2 business days.</p>}
          {status === 'approved' && <p className="mt-1">You are approved! Open the Seller Center from your account menu.</p>}
          {status === 'rejected' && <p className="mt-1">{application.rejection_reason} — update the details below and submit again.</p>}
        </div>
      )}

      <form
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          form.post('/account/sell', { preserveScroll: true });
        }}
        className="space-y-5"
      >
        <Card title="Business" description="Tell us who runs the shop.">
          <div className="grid gap-4 sm:grid-cols-2">
            {field('business_name', 'Registered or owner name')}
            <FormField label="Business type" required error={form.errors.business_type}>
              {(id) => <Select id={id} disabled={locked} value={form.data.business_type} options={businessTypes} onChange={(event) => form.setData('business_type', event.target.value)} />}
            </FormField>
            {field('tin', 'TIN', { placeholder: 'Optional for home-based sellers' }, false)}
          </div>
        </Card>

        <Card title="Shop" description="How customers will see you.">
          <div className="grid gap-4 sm:grid-cols-2">
            {field('shop_name', 'Shop name')}
            <FormField label="Shop category" error={form.errors.shop_category_id}>
              {(id) => <Select id={id} disabled={locked} value={form.data.shop_category_id ?? ''} placeholder="Choose a category" options={shopCategories.map((category) => ({ value: category.id, label: category.name }))} onChange={(event) => form.setData('shop_category_id', event.target.value)} />}
            </FormField>
            <FormField label="Description" error={form.errors.description} className="sm:col-span-2">
              {(id) => <Textarea id={id} disabled={locked} rows={3} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />}
            </FormField>
          </div>
        </Card>

        <Card title="Pickup address" description="Where riders collect your parcels.">
          <div className="grid gap-4 sm:grid-cols-2">
            {field('pickup_street', 'House no., street, building', {}, true, 'sm:col-span-2')}
            {field('pickup_barangay', 'Barangay')}
            {field('pickup_city', 'City / municipality')}
            {field('pickup_province', 'Province')}
            {field('pickup_region', 'Region')}
            {field('pickup_phone', 'Pickup contact number', { type: 'tel', placeholder: '09171234567' })}
          </div>
        </Card>

        {!locked && (
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <Checkbox label="I agree to the Onetap seller policies and commission terms." checked={form.data.agree} onChange={(event) => form.setData('agree', event.target.checked)} />
              {form.errors.agree && <p className="mt-1 text-xs text-error-500">{form.errors.agree}</p>}
              {form.errors.business_name && status && <p className="mt-1 text-xs text-error-500">{form.errors.business_name}</p>}
            </div>
            <Button type="submit" loading={form.processing}>
              {status === 'rejected' ? 'Resubmit application' : 'Submit application'}
            </Button>
          </div>
        )}
      </form>
    </AccountLayout>
  );
}
