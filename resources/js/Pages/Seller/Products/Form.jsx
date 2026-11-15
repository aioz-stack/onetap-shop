import { useMemo, useRef } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Button, Card, Checkbox, FormField, PageHeader, Select, StatusBadge, Textarea, TextInput } from '../../../Components/ui';
import SellerLayout from '../../../Layouts/SellerLayout';

const MAX_IMAGES = 8;
const blankVariation = () => ({ id: null, label: '', price: '', compare_at_price: '', stock: 0, reserved: 0, is_active: true });

export default function Form({ product, categories }) {
  const editing = Boolean(product);
  const fileRef = useRef(null);
  const form = useForm({
    name: product?.name ?? '',
    description: product?.description ?? '',
    brand: product?.brand ?? '',
    product_category_id: product?.product_category_id ?? '',
    weight_grams: product?.weight_grams ?? 500,
    variations: product?.variations?.length ? product.variations : [{ ...blankVariation(), label: 'Default' }],
    images: [],
    remove_images: [],
    primary_image_id: product?.images?.find((image) => image.is_primary)?.id ?? null,
    submit: false,
  });
  const { data, setData, errors, processing } = form;
  const keptImages = (product?.images ?? []).filter((image) => !data.remove_images.includes(image.id));
  const previews = useMemo(() => data.images.map((file) => URL.createObjectURL(file)), [data.images]);
  const totalImages = keptImages.length + data.images.length;

  const setVariation = (index, field, value) => setData('variations', data.variations.map((row, position) => (position === index ? { ...row, [field]: value } : row)));
  const addImages = (files) => setData('images', [...data.images, ...Array.from(files)].slice(0, Math.max(0, MAX_IMAGES - keptImages.length)));

  const save = (submit) => {
    form.transform((payload) => ({ ...payload, submit }));
    const url = editing ? `/seller/products/${product.id}` : '/seller/products';
    form.post(url, { forceFormData: true, preserveScroll: true, onSuccess: () => setData('images', []) });
  };

  const error = (key) => errors[key];
  const variationError = (index, field) => errors[`variations.${index}.${field}`];
  const imageErrors = Object.entries(errors).filter(([key]) => key.startsWith('images')).map(([, message]) => message);
  const locked = product?.status === 'violation';

  return (
    <SellerLayout>
      <Head title={editing ? `Edit ${product.name}` : 'Add product'} />
      <PageHeader
        title={editing ? product.name : 'Add product'}
        description={editing ? <span className="inline-flex items-center gap-2"><StatusBadge status={product.status} /> Changing the name, description, category, brand or photos of a live product sends it back for review.</span> : 'New products are reviewed by Onetap before they go live.'}
        breadcrumbs={[{ label: 'Products', href: '/seller/products' }, { label: editing ? 'Edit' : 'Add' }]}
        actions={editing && product.status === 'live' && <Button variant="secondary" size="sm" href={`/products/${product.slug}`}>View on store</Button>}
      />

      {product?.rejection_reason && (
        <div className="mb-5 rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-500">
          <strong>{product.status === 'violation' ? 'Policy violation' : 'Onetap feedback'}:</strong> {product.rejection_reason}
          {product.violations?.map((violation, index) => <p key={index} className="mt-1">{violation.reason}{violation.details ? ` — ${violation.details}` : ''}</p>)}
        </div>
      )}
      {(errors.status || errors.variations) && <p className="mb-4 rounded-lg bg-error-50 px-3 py-2 text-sm text-error-700 dark:bg-error-500/10 dark:text-error-500">{errors.status ?? errors.variations}</p>}

      <div className="grid gap-6 xl:grid-cols-3">
        <div className="min-w-0 space-y-6 xl:col-span-2">
          <Card title="Basic information">
            <div className="grid gap-4 sm:grid-cols-2">
              <FormField label="Product name" required error={error('name')} className="sm:col-span-2">
                {(id) => <TextInput id={id} disabled={locked} value={data.name} error={error('name')} maxLength={150} onChange={(event) => setData('name', event.target.value)} placeholder="e.g. Ube Ensaymada" />}
              </FormField>
              <FormField label="Category" required error={error('product_category_id')}>
                {(id) => <Select id={id} disabled={locked} value={data.product_category_id} placeholder="Choose a category" options={categories} onChange={(event) => setData('product_category_id', event.target.value)} />}
              </FormField>
              <FormField label="Brand" error={error('brand')}>
                {(id) => <TextInput id={id} disabled={locked} value={data.brand ?? ''} placeholder="Optional" onChange={(event) => setData('brand', event.target.value)} />}
              </FormField>
              <FormField label="Description" error={error('description')} className="sm:col-span-2" hint="Ingredients, size, materials, care instructions — what a buyer would ask.">
                {(id) => <Textarea id={id} disabled={locked} rows={6} value={data.description ?? ''} maxLength={5000} onChange={(event) => setData('description', event.target.value)} />}
              </FormField>
              <FormField label="Weight (grams)" error={error('weight_grams')} hint="Used by riders to plan pickups.">
                {(id) => <TextInput id={id} disabled={locked} type="number" min="0" value={data.weight_grams} onChange={(event) => setData('weight_grams', event.target.value)} />}
              </FormField>
            </div>
          </Card>

          <Card title="Variations, price & stock" description="Add one row per option customers can pick (size, flavor, pack). Stock is what you have on hand." bodyClassName="">
            <div className="overflow-x-auto custom-scrollbar">
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800">
                    <th className="px-4 py-2 font-medium">Name</th>
                    <th className="px-4 py-2 font-medium">Price (₱)</th>
                    <th className="px-4 py-2 font-medium">Original (₱)</th>
                    <th className="px-4 py-2 font-medium">Stock</th>
                    <th className="px-4 py-2 font-medium">Active</th>
                    <th className="px-4 py-2" />
                  </tr>
                </thead>
                <tbody>
                  {data.variations.map((row, index) => (
                    <tr key={row.id ?? `new-${index}`} className="border-b border-gray-50 align-top dark:border-gray-800/60">
                      <td className="px-4 py-2">
                        <TextInput aria-label="Variation name" disabled={locked} className="h-10 min-w-[140px]" value={row.label} error={variationError(index, 'label')} onChange={(event) => setVariation(index, 'label', event.target.value)} placeholder="e.g. Box of 6" />
                        {variationError(index, 'label') && <p className="mt-1 text-xs text-error-500">{variationError(index, 'label')}</p>}
                        {row.sku && <p className="mt-1 font-mono text-[10px] text-gray-400">{row.sku}</p>}
                      </td>
                      <td className="px-4 py-2">
                        <TextInput aria-label="Price" disabled={locked} type="number" min="1" step="0.01" className="h-10 w-28" value={row.price} error={variationError(index, 'price')} onChange={(event) => setVariation(index, 'price', event.target.value)} />
                        {variationError(index, 'price') && <p className="mt-1 text-xs text-error-500">{variationError(index, 'price')}</p>}
                      </td>
                      <td className="px-4 py-2">
                        <TextInput aria-label="Original price" disabled={locked} type="number" min="0" step="0.01" className="h-10 w-28" placeholder="Optional" value={row.compare_at_price ?? ''} error={variationError(index, 'compare_at_price')} onChange={(event) => setVariation(index, 'compare_at_price', event.target.value)} />
                        {variationError(index, 'compare_at_price') && <p className="mt-1 text-xs text-error-500">{variationError(index, 'compare_at_price')}</p>}
                      </td>
                      <td className="px-4 py-2">
                        <TextInput aria-label="Stock" disabled={locked} type="number" min={row.reserved ?? 0} className="h-10 w-24" value={row.stock} error={variationError(index, 'stock')} onChange={(event) => setVariation(index, 'stock', event.target.value)} />
                        {row.reserved > 0 && <p className="mt-1 text-xs text-gray-500">{row.reserved} reserved by open orders</p>}
                        {variationError(index, 'stock') && <p className="mt-1 text-xs text-error-500">{variationError(index, 'stock')}</p>}
                      </td>
                      <td className="px-4 py-2 pt-4">
                        <input type="checkbox" aria-label="Active" disabled={locked} checked={row.is_active} onChange={(event) => setVariation(index, 'is_active', event.target.checked)} className="h-4 w-4 accent-brand-500" />
                      </td>
                      <td className="px-4 py-2 pt-3 text-right">
                        {data.variations.length > 1 && !locked && (
                          <button type="button" className="text-xs text-error-600 hover:underline" onClick={() => setData('variations', data.variations.filter((_, position) => position !== index))}>
                            Remove
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {!locked && (
              <div className="p-4">
                <Button size="sm" variant="secondary" onClick={() => setData('variations', [...data.variations, blankVariation()])} disabled={data.variations.length >= 30}>
                  Add variation
                </Button>
              </div>
            )}
          </Card>
        </div>

        <div className="min-w-0 space-y-6">
          <Card title={`Photos (${totalImages}/${MAX_IMAGES})`} description="The first or starred photo is the cover. JPG, PNG or WebP up to 4 MB, at least 200×200.">
            <ul className="grid grid-cols-3 gap-2">
              {keptImages.map((image) => (
                <li key={image.id} className="group relative aspect-square overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
                  <img src={image.url} alt="" className="h-full w-full object-cover" />
                  <button type="button" disabled={locked} aria-label="Use as cover" onClick={() => setData('primary_image_id', image.id)} className={`absolute left-1 top-1 rounded-full px-1.5 text-xs ${data.primary_image_id === image.id ? 'bg-warning-500 text-white' : 'bg-white/80 text-gray-600'}`}>
                    ★
                  </button>
                  {!locked && (
                    <button type="button" aria-label="Remove photo" onClick={() => setData('remove_images', [...data.remove_images, image.id])} className="absolute right-1 top-1 rounded-full bg-gray-900/70 px-1.5 text-xs text-white">
                      ✕
                    </button>
                  )}
                </li>
              ))}
              {previews.map((url, index) => (
                <li key={url} className="relative aspect-square overflow-hidden rounded-lg border border-dashed border-brand-300">
                  <img src={url} alt="" className="h-full w-full object-cover" />
                  <span className="absolute bottom-1 left-1 rounded bg-brand-500 px-1 text-[10px] text-white">New</span>
                  <button type="button" aria-label="Remove new photo" onClick={() => setData('images', data.images.filter((_, position) => position !== index))} className="absolute right-1 top-1 rounded-full bg-gray-900/70 px-1.5 text-xs text-white">
                    ✕
                  </button>
                </li>
              ))}
              {totalImages < MAX_IMAGES && !locked && (
                <li>
                  <button type="button" onClick={() => fileRef.current?.click()} className="flex aspect-square w-full flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 text-xs text-gray-500 hover:border-brand-400 hover:text-brand-600 dark:border-gray-700">
                    <span className="text-2xl">+</span>
                    Add photo
                  </button>
                </li>
              )}
            </ul>
            <input ref={fileRef} type="file" multiple accept="image/jpeg,image/png,image/webp" className="hidden" onChange={(event) => { addImages(event.target.files); event.target.value = ''; }} />
            {imageErrors.map((message) => <p key={message} className="mt-2 text-xs text-error-500">{message}</p>)}
          </Card>

          {!locked && (
            <Card>
              <div className="flex flex-col gap-2">
                {(!editing || ['draft', 'rejected'].includes(product.status)) && (
                  <Button loading={processing && data.submit} onClick={() => save(true)}>
                    {product?.status === 'rejected' ? 'Save & resubmit for review' : 'Submit for review'}
                  </Button>
                )}
                <Button variant={!editing || ['draft', 'rejected'].includes(product.status) ? 'secondary' : 'primary'} loading={processing && !data.submit} onClick={() => save(false)}>
                  {editing ? 'Save changes' : 'Save as draft'}
                </Button>
                <Button variant="ghost" href="/seller/products">Cancel</Button>
              </div>
            </Card>
          )}
        </div>
      </div>
    </SellerLayout>
  );
}
