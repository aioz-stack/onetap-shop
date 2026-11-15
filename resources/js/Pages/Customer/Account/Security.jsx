import { Head, useForm } from '@inertiajs/react';
import { Badge, Button, Card, FormField, PasswordInput } from '../../../Components/ui';
import AccountLayout from '../../../Layouts/AccountLayout';
import { formatDateTime } from '../../../lib/format';

export default function Security({ sessions, tokens, lastLogin }) {
  const password = useForm({ current_password: '', password: '', password_confirmation: '' });
  const signOut = useForm({ password: '' });

  const pw = (name, label, autoComplete) => (
    <FormField label={label} required error={password.errors[name]}>
      {(id) => <PasswordInput id={id} autoComplete={autoComplete} value={password.data[name]} error={password.errors[name]} onChange={(event) => password.setData(name, event.target.value)} />}
    </FormField>
  );

  return (
    <AccountLayout title="Account security">
      <Head title="Account security" />

      <Card title="Change password" description="Changing your password signs you out on every other device.">
        <form
          className="grid max-w-xl gap-4"
          noValidate
          onSubmit={(event) => {
            event.preventDefault();
            password.put('/account/security/password', { preserveScroll: true, onSuccess: () => password.reset() });
          }}
        >
          {pw('current_password', 'Current password', 'current-password')}
          {pw('password', 'New password', 'new-password')}
          {pw('password_confirmation', 'Confirm new password', 'new-password')}
          <div>
            <Button type="submit" loading={password.processing}>Update password</Button>
          </div>
        </form>
      </Card>

      <Card className="mt-5" title="Where you're signed in" description={`Last sign-in ${formatDateTime(lastLogin)} · ${tokens} mobile app token${tokens === 1 ? '' : 's'}`}>
        <ul className="divide-y divide-gray-100 text-sm dark:divide-gray-800">
          {sessions.map((session, index) => (
            <li key={index} className="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0">
              <span className="min-w-0">
                <span className="block truncate text-gray-800 dark:text-white/90">{session.device || 'Unknown device'}</span>
                <span className="text-xs text-gray-500">{session.ip_address} · active {formatDateTime(session.last_active)}</span>
              </span>
              {session.current && <Badge tone="success">This device</Badge>}
            </li>
          ))}
        </ul>
        <form
          className="mt-4 flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-end dark:border-gray-800"
          noValidate
          onSubmit={(event) => {
            event.preventDefault();
            signOut.post('/account/security/sign-out-others', { preserveScroll: true, onSuccess: () => signOut.reset() });
          }}
        >
          <FormField label="Confirm with your password" error={signOut.errors.password} className="sm:w-72">
            {(id) => <PasswordInput id={id} autoComplete="current-password" value={signOut.data.password} error={signOut.errors.password} onChange={(event) => signOut.setData('password', event.target.value)} />}
          </FormField>
          <Button type="submit" variant="secondary" loading={signOut.processing}>Sign out other devices</Button>
        </form>
      </Card>
    </AccountLayout>
  );
}
