import { Head, useForm } from '@inertiajs/react';
import { Button, Card, Checkbox, FormField, PageHeader, PasswordInput, TextInput } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';

export default function Form({ user, roles }) {
  const editing = Boolean(user);
  const { data, setData, post, put, processing, errors } = useForm({
    name: user?.name ?? '',
    username: user?.username ?? '',
    email: user?.email ?? '',
    phone: user?.phone ?? '',
    password: '',
    roles: user?.roles ?? ['customer'],
  });

  const toggleRole = (role) =>
    setData('roles', data.roles.includes(role) ? data.roles.filter((item) => item !== role) : [...data.roles, role]);

  const submit = (event) => {
    event.preventDefault();
    editing ? put(`/admin/users/${user.id}`) : post('/admin/users');
  };

  const text = (name, label, props = {}, required = true) => (
    <FormField label={label} required={required} error={errors[name]}>
      {(id) => <TextInput id={id} value={data[name]} error={errors[name]} onChange={(event) => setData(name, event.target.value)} {...props} />}
    </FormField>
  );

  return (
    <AdminLayout>
      <Head title={editing ? `Edit ${user.name}` : 'Add account'} />
      <PageHeader
        title={editing ? `Edit ${user.name}` : 'Add account'}
        description={editing ? 'Update account details and roles.' : 'Create an administrator or another staff account.'}
        breadcrumbs={[{ label: 'Users', href: '/admin/users' }, { label: editing ? 'Edit' : 'Add' }]}
      />

      <form onSubmit={submit} noValidate>
        <Card className="max-w-3xl">
          <div className="grid gap-5 sm:grid-cols-2">
            {text('name', 'Full name', { autoComplete: 'name' })}
            {text('username', 'Username', { autoComplete: 'username' })}
            {text('email', 'Email', { type: 'email', autoComplete: 'email' })}
            {text('phone', 'Mobile number', { type: 'tel', placeholder: '09171234567' }, false)}
            <FormField
              label={editing ? 'New password' : 'Password'}
              required={!editing}
              error={errors.password}
              hint={editing ? 'Leave blank to keep the current password.' : 'At least 8 characters with letters and numbers.'}
              className="sm:col-span-2"
            >
              {(id) => <PasswordInput id={id} autoComplete="new-password" value={data.password} error={errors.password} onChange={(event) => setData('password', event.target.value)} />}
            </FormField>
          </div>

          <fieldset className="mt-6">
            <legend className="mb-3 text-sm font-medium text-gray-700 dark:text-gray-400">
              Roles <span className="text-error-500">*</span>
            </legend>
            <div className="grid gap-3 sm:grid-cols-3">
              {roles.map((role) => (
                <Checkbox key={role.value} label={role.label} checked={data.roles.includes(role.value)} onChange={() => toggleRole(role.value)} />
              ))}
            </div>
            <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
              Sellers are normally approved from Sellers, and riders are linked to a partner from the Logistics panel.
            </p>
            {(errors.roles || errors['roles.0']) && <p className="mt-1.5 text-xs text-error-500">{errors.roles ?? errors['roles.0']}</p>}
          </fieldset>

          <div className="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-5 dark:border-gray-800">
            <Button variant="secondary" href={editing ? `/admin/users/${user.id}` : '/admin/users'}>
              Cancel
            </Button>
            <Button type="submit" loading={processing}>
              {editing ? 'Save changes' : 'Create account'}
            </Button>
          </div>
        </Card>
      </form>
    </AdminLayout>
  );
}
