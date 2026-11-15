import { useRef } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Avatar } from '../../../Components/UserMenu';
import { Badge, Button, Card, Checkbox, FormField, Select, TextInput } from '../../../Components/ui';
import AccountLayout from '../../../Layouts/AccountLayout';
import { formatDate } from '../../../lib/format';

export default function Profile({ profile }) {
  const fileRef = useRef(null);
  const form = useForm({
    name: profile.name,
    email: profile.email,
    phone: profile.phone ?? '',
    birthdate: profile.birthdate ?? '',
    gender: profile.gender ?? '',
    profile_picture: null,
    remove_picture: false,
  });
  const preview = form.data.profile_picture ? URL.createObjectURL(form.data.profile_picture) : null;

  const field = (name, label, props = {}, required = false) => (
    <FormField label={label} required={required} error={form.errors[name]} hint={props.hint}>
      {(id) => <TextInput id={id} value={form.data[name] ?? ''} error={form.errors[name]} onChange={(event) => form.setData(name, event.target.value)} {...props} hint={undefined} />}
    </FormField>
  );

  return (
    <AccountLayout title="My profile">
      <Head title="My profile" />
      <form
        onSubmit={(event) => {
          event.preventDefault();
          form.post('/account/profile', { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset('profile_picture') });
        }}
        noValidate
      >
        <Card>
          <div className="grid gap-8 md:grid-cols-[1fr_200px]">
            <div className="grid gap-5 sm:grid-cols-2">
              <FormField label="Username">{(id) => <TextInput id={id} value={profile.username ?? ''} disabled />}</FormField>
              {field('name', 'Full name', { autoComplete: 'name' }, true)}
              {field('email', 'Email', { type: 'email', autoComplete: 'email', hint: profile.email_verified ? undefined : 'Not verified yet' }, true)}
              {field('phone', 'Mobile number', { type: 'tel', autoComplete: 'tel', placeholder: '09171234567' })}
              {field('birthdate', 'Birthday', { type: 'date' })}
              <FormField label="Gender" error={form.errors.gender}>
                {(id) => (
                  <Select
                    id={id}
                    value={form.data.gender}
                    placeholder="Prefer not to say"
                    options={[{ value: 'female', label: 'Female' }, { value: 'male', label: 'Male' }, { value: 'other', label: 'Other' }]}
                    onChange={(event) => form.setData('gender', event.target.value)}
                  />
                )}
              </FormField>
            </div>

            <div className="flex flex-col items-center gap-3 text-center">
              {preview ? <img src={preview} alt="" className="h-28 w-28 rounded-full object-cover" /> : <Avatar user={form.data.remove_picture ? { name: profile.name } : profile} size="h-28 w-28 text-2xl" />}
              <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={(event) => form.setData({ ...form.data, profile_picture: event.target.files[0] ?? null, remove_picture: false })} />
              <Button size="sm" variant="secondary" onClick={() => fileRef.current?.click()}>
                Choose photo
              </Button>
              {profile.profile_picture && !preview && <Checkbox label="Remove photo" checked={form.data.remove_picture} onChange={(event) => form.setData('remove_picture', event.target.checked)} />}
              <p className="text-xs text-gray-500">JPG, PNG or WebP, up to 2 MB.</p>
              {form.errors.profile_picture && <p className="text-xs text-error-500">{form.errors.profile_picture}</p>}
            </div>
          </div>

          <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-5 dark:border-gray-800">
            <p className="text-xs text-gray-500">
              Member since {formatDate(profile.member_since)} · Referral code <Badge tone="brand">{profile.referral_code}</Badge>
            </p>
            <Button type="submit" loading={form.processing} disabled={!form.isDirty}>
              Save profile
            </Button>
          </div>
        </Card>
      </form>
    </AccountLayout>
  );
}
