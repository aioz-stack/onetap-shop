import { Head, useForm } from '@inertiajs/react';
import DetailList from '../../Components/admin/DetailList';
import { Button, Card, FormField, PasswordInput, StatusBadge, TextInput } from '../../Components/ui';
import RiderLayout from '../../Layouts/RiderLayout';
import { formatDateTime, formatMoney, headline } from '../../lib/format';

function duration(minutes) {
  const hours = Math.floor(minutes / 60);

  return hours ? `${hours}h ${minutes % 60}m` : `${minutes}m`;
}

export default function Profile({ profile, shifts, performance }) {
  const contact = useForm({ phone: profile.phone ?? '' });
  const password = useForm({ current_password: '', password: '', password_confirmation: '' });

  return (
    <RiderLayout title="Profile">
      <Head title="Profile" />

      <Card title={profile.name} description={profile.partner} bodyClassName="p-4">
        <DetailList
          items={[
            { label: 'Status', value: <StatusBadge status={profile.status} /> },
            { label: 'Availability', value: <StatusBadge status={profile.availability} /> },
            { label: 'Vehicle', value: `${headline(profile.vehicle_type ?? '')} ${profile.plate_number ?? ''}`.trim() || null },
            { label: 'License', value: profile.license_number },
            { label: 'Email', value: profile.email },
            { label: 'Pay per delivery', value: formatMoney(profile.rate ?? 0) },
            { label: 'Delivered (all time)', value: performance.delivered },
            { label: 'Declined jobs', value: performance.declined },
          ]}
        />
        <p className="mt-4 text-xs text-gray-500 dark:text-gray-400">Vehicle details are managed by your logistics partner.</p>
      </Card>

      <Card title="Contact number" description="Customers and your dispatcher use this to reach you." className="mt-5" bodyClassName="p-4">
        <form
          className="flex flex-wrap items-end gap-3"
          onSubmit={(event) => {
            event.preventDefault();
            contact.put('/rider/profile', { preserveScroll: true });
          }}
        >
          <FormField label="Mobile number" error={contact.errors.phone} className="min-w-0 flex-1">
            {(id) => <TextInput id={id} type="tel" inputMode="tel" value={contact.data.phone} error={contact.errors.phone} onChange={(event) => contact.setData('phone', event.target.value)} />}
          </FormField>
          <Button type="submit" loading={contact.processing}>Save</Button>
        </form>
      </Card>

      <Card title="Change password" className="mt-5" bodyClassName="p-4">
        <form
          className="space-y-4"
          onSubmit={(event) => {
            event.preventDefault();
            password.put('/rider/profile/password', { preserveScroll: true, onSuccess: () => password.reset() });
          }}
        >
          {[
            ['current_password', 'Current password', 'current-password'],
            ['password', 'New password', 'new-password'],
            ['password_confirmation', 'Confirm new password', 'new-password'],
          ].map(([name, label, autoComplete]) => (
            <FormField key={name} label={label} required error={password.errors[name]}>
              {(id) => <PasswordInput id={id} autoComplete={autoComplete} value={password.data[name]} error={password.errors[name]} onChange={(event) => password.setData(name, event.target.value)} />}
            </FormField>
          ))}
          <Button type="submit" loading={password.processing}>Update password</Button>
        </form>
      </Card>

      <Card title="Recent shifts" className="mt-5" bodyClassName="p-4">
        {shifts.length ? (
          <ul className="divide-y divide-gray-100 text-sm dark:divide-gray-800">
            {shifts.map((shift) => (
              <li key={shift.id} className="flex justify-between gap-3 py-2">
                <span className="text-gray-700 dark:text-gray-300">{formatDateTime(shift.started_at)}<span className="block text-xs text-gray-500">{shift.ended_at ? `to ${formatDateTime(shift.ended_at)}` : 'On shift now'}</span></span>
                <span className="font-medium text-gray-800 dark:text-white/90">{duration(shift.minutes)}</span>
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-sm text-gray-500">No shifts yet. Sign in from the Home tab to start one.</p>
        )}
      </Card>
    </RiderLayout>
  );
}
