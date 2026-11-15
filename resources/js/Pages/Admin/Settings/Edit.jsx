import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Card, Checkbox, FormField, PageHeader, Select, TextInput } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';

function Field({ field, form }) {
  const { data, setData, errors } = form;
  const error = errors[field.key];

  if (field.type === 'boolean') {
    return (
      <div className="sm:col-span-2">
        <Checkbox label={field.label} checked={data[field.key] === true || data[field.key] === '1'} onChange={(event) => setData(field.key, event.target.checked)} />
        {error && <p className="mt-1.5 text-xs text-error-500">{error}</p>}
      </div>
    );
  }

  return (
    <FormField label={field.label} error={error} hint={field.hint}>
      {(id) =>
        field.type === 'select' ? (
          <Select id={id} value={data[field.key] ?? ''} options={field.options.map((option) => ({ value: option, label: option }))} onChange={(event) => setData(field.key, event.target.value)} />
        ) : field.type === 'color' ? (
          <div className="flex items-center gap-3">
            <input type="color" aria-label={field.label} value={data[field.key] ?? '#000000'} onChange={(event) => setData(field.key, event.target.value)} className="h-11 w-14 cursor-pointer rounded-lg border border-gray-300 bg-transparent p-1 dark:border-gray-700" />
            <TextInput id={id} value={data[field.key] ?? ''} error={error} onChange={(event) => setData(field.key, event.target.value)} />
          </div>
        ) : (
          <TextInput
            id={id}
            type={field.type === 'money' || field.type === 'number' ? 'number' : field.type}
            step={field.type === 'money' ? '0.01' : undefined}
            value={data[field.key] ?? ''}
            error={error}
            onChange={(event) => setData(field.key, event.target.value)}
          />
        )
      }
    </FormField>
  );
}

export default function Edit({ group, groups, definition, values }) {
  const initial = Object.fromEntries(
    definition.fields.map((field) => [field.key, field.type === 'boolean' ? values[field.key] === '1' : values[field.key] ?? '']),
  );
  const form = useForm(initial);

  return (
    <AdminLayout>
      <Head title={`${definition.label} settings`} />
      <PageHeader title="Settings" description="Marketplace-wide configuration. Every change is recorded in the audit log." breadcrumbs={[{ label: 'Admin', href: '/admin' }, { label: 'Settings' }]} />

      <div className="grid gap-6 lg:grid-cols-[220px_1fr]">
        <nav aria-label="Settings sections" className="min-w-0">
          <ul className="no-scrollbar flex gap-1 overflow-x-auto lg:flex-col">
            {groups.map((item) => (
              <li key={item.key}>
                <Link
                  href={`/admin/settings/${item.key}`}
                  className={`block whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium ${
                    item.key === group ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5'
                  }`}
                >
                  {item.label}
                </Link>
              </li>
            ))}
            <li>
              <Link href="/admin/audit-logs" className="block whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5">
                Audit logs →
              </Link>
            </li>
          </ul>
        </nav>

        <form
          onSubmit={(event) => {
            event.preventDefault();
            form.put(`/admin/settings/${group}`, { preserveScroll: true });
          }}
          noValidate
          className="min-w-0"
        >
          <Card title={definition.label} description={definition.description}>
            <div className="grid gap-5 sm:grid-cols-2">
              {definition.fields.map((field) => (
                <Field key={field.key} field={field} form={form} />
              ))}
            </div>
            <div className="mt-6 flex justify-end border-t border-gray-100 pt-5 dark:border-gray-800">
              <Button type="submit" loading={form.processing} disabled={!form.isDirty}>
                Save {definition.label.toLowerCase()} settings
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AdminLayout>
  );
}
