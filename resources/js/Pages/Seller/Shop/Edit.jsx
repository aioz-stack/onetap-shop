import { useRef } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Button, Card, Checkbox, FormField, PageHeader, Select, Textarea, TextInput } from '../../../Components/ui';
import SellerLayout from '../../../Layouts/SellerLayout';

function ImagePicker({ label, current, file, onPick, onRemove, removed, aspect, hint }) {
  const ref = useRef(null);
  const preview = file ? URL.createObjectURL(file) : removed ? null : current;

  return (
    <div>
      <p className="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400">{label}</p>
      <div className={`overflow-hidden rounded-xl border border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-white/5 ${aspect}`}>
        {preview ? <img src={preview} alt="" className="h-full w-full object-cover" /> : <div className="flex h-full items-center justify-center text-xs text-gray-400">No image</div>}
      </div>
      <div className="mt-2 flex gap-2">
        <Button size="sm" variant="secondary" onClick={() => ref.current?.click()}>Choose</Button>
        {(current || file) && !removed && <Button size="sm" variant="ghost" onClick={onRemove}>Remove</Button>}
      </div>
      {hint && <p className="mt-1 text-xs text-gray-500">{hint}</p>}
      <input ref={ref} type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={(event) => onPick(event.target.files[0] ?? null)} />
    </div>
  );
}

export default function Edit({ shop, categories, templates }) {
  const profile = useForm({
    description: shop.description ?? '',
    shop_category_id: shop.shop_category_id ?? '',
    pickup_street: shop.pickup_street ?? '',
    pickup_barangay: shop.pickup_barangay ?? '',
    pickup_city: shop.pickup_city ?? '',
    pickup_province: shop.pickup_province ?? '',
    pickup_region: shop.pickup_region ?? '',
    pickup_phone: shop.pickup_phone ?? '',
  });
  const branding = useForm({ logo: null, banner: null, remove_logo: false, remove_banner: false, template: shop.template ?? 'classic', accent_color: shop.decoration?.accent_color ?? '#0f9d76', announcement: shop.decoration?.announcement ?? '' });
  const vacation = useForm({ vacation_mode: shop.vacation_mode, vacation_message: shop.vacation_message ?? '' });

  const field = (name, label, props = {}, className = '') => (
    <FormField label={label} required error={profile.errors[name]} className={className}>
      {(id) => <TextInput id={id} value={profile.data[name]} error={profile.errors[name]} onChange={(event) => profile.setData(name, event.target.value)} {...props} />}
    </FormField>
  );

  return (
    <SellerLayout>
      <Head title="Shop settings" />
      <PageHeader title="Shop settings" description={`Your shop page: /shops/${shop.slug}`} actions={<Button size="sm" variant="secondary" href={`/shops/${shop.slug}`}>View shop</Button>} />

      <div className="grid gap-6 xl:grid-cols-2">
        <Card title="Shop profile">
          <form className="grid gap-4 sm:grid-cols-2" noValidate onSubmit={(event) => { event.preventDefault(); profile.put('/seller/shop', { preserveScroll: true }); }}>
            <FormField label="Shop name" className="sm:col-span-2" hint="Contact Onetap support to rename your shop.">{(id) => <TextInput id={id} value={shop.name} disabled />}</FormField>
            <FormField label="Category" error={profile.errors.shop_category_id}>
              {(id) => <Select id={id} value={profile.data.shop_category_id} placeholder="Choose" options={categories.map((category) => ({ value: category.id, label: category.name }))} onChange={(event) => profile.setData('shop_category_id', event.target.value)} />}
            </FormField>
            {field('pickup_phone', 'Pickup contact number', { type: 'tel' })}
            <FormField label="Description" error={profile.errors.description} className="sm:col-span-2">
              {(id) => <Textarea id={id} rows={3} value={profile.data.description} maxLength={1000} onChange={(event) => profile.setData('description', event.target.value)} />}
            </FormField>
            {field('pickup_street', 'Pickup street', {}, 'sm:col-span-2')}
            {field('pickup_barangay', 'Barangay')}
            {field('pickup_city', 'City')}
            {field('pickup_province', 'Province')}
            {field('pickup_region', 'Region')}
            <div className="sm:col-span-2">
              <Button type="submit" loading={profile.processing} disabled={!profile.isDirty}>Save profile</Button>
            </div>
          </form>
        </Card>

        <div className="space-y-6">
          <Card title="Branding & decoration">
            <form
              noValidate
              onSubmit={(event) => {
                event.preventDefault();
                branding.post('/seller/shop/branding', { forceFormData: true, preserveScroll: true, onSuccess: () => branding.reset('logo', 'banner', 'remove_logo', 'remove_banner') });
              }}
            >
              <div className="grid gap-4 sm:grid-cols-[120px_1fr]">
                <ImagePicker label="Logo" aspect="aspect-square" current={shop.logo_url} file={branding.data.logo} removed={branding.data.remove_logo} onPick={(file) => branding.setData({ ...branding.data, logo: file, remove_logo: false })} onRemove={() => branding.setData({ ...branding.data, logo: null, remove_logo: true })} />
                <ImagePicker label="Banner" aspect="aspect-[4/1]" current={shop.banner_url} file={branding.data.banner} removed={branding.data.remove_banner} hint="At least 800×200." onPick={(file) => branding.setData({ ...branding.data, banner: file, remove_banner: false })} onRemove={() => branding.setData({ ...branding.data, banner: null, remove_banner: true })} />
              </div>
              {['logo', 'banner'].map((key) => branding.errors[key] && <p key={key} className="mt-2 text-xs text-error-500">{branding.errors[key]}</p>)}

              <fieldset className="mt-5">
                <legend className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-400">Shop page template</legend>
                <div className="space-y-2">
                  {templates.map((template) => (
                    <label key={template.value} className={`flex cursor-pointer gap-3 rounded-xl border p-3 text-sm ${branding.data.template === template.value ? 'border-brand-500 bg-brand-50/50 dark:bg-brand-500/10' : 'border-gray-200 dark:border-gray-800'}`}>
                      <input type="radio" name="template" className="accent-brand-500" checked={branding.data.template === template.value} onChange={() => branding.setData('template', template.value)} />
                      <span className="text-gray-700 dark:text-gray-300">{template.label}</span>
                    </label>
                  ))}
                </div>
              </fieldset>

              <div className="mt-5 grid gap-4 sm:grid-cols-[160px_1fr]">
                <FormField label="Accent color" error={branding.errors.accent_color}>
                  {() => <input type="color" aria-label="Accent color" value={branding.data.accent_color} onChange={(event) => branding.setData('accent_color', event.target.value)} className="h-11 w-full cursor-pointer rounded-lg border border-gray-300 p-1 dark:border-gray-700" />}
                </FormField>
                <FormField label="Announcement bar" error={branding.errors.announcement} hint="Shown at the top of your shop page.">
                  {(id) => <TextInput id={id} maxLength={160} placeholder="e.g. Free delivery on orders over ₱500 this week!" value={branding.data.announcement} onChange={(event) => branding.setData('announcement', event.target.value)} />}
                </FormField>
              </div>
              <Button type="submit" className="mt-5" loading={branding.processing} disabled={!branding.isDirty}>Save branding</Button>
            </form>
          </Card>

          <Card title="Vacation mode" description="Pause new orders while you are away. Your products stay visible.">
            <form noValidate onSubmit={(event) => { event.preventDefault(); vacation.put('/seller/shop/vacation', { preserveScroll: true }); }} className="space-y-4">
              <Checkbox label="My shop is on vacation" checked={vacation.data.vacation_mode} onChange={(event) => vacation.setData('vacation_mode', event.target.checked)} />
              <FormField label="Message for customers" error={vacation.errors.vacation_message}>
                {(id) => <TextInput id={id} maxLength={160} placeholder="e.g. Back on Monday!" value={vacation.data.vacation_message} onChange={(event) => vacation.setData('vacation_message', event.target.value)} />}
              </FormField>
              <Button type="submit" variant={vacation.data.vacation_mode ? 'accent' : 'primary'} loading={vacation.processing} disabled={!vacation.isDirty}>Save</Button>
            </form>
          </Card>
        </div>
      </div>
    </SellerLayout>
  );
}
