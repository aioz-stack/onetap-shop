import { Head, useForm } from '@inertiajs/react';
import AuthShell from '../../Components/AuthShell';
import { Button, FormField, PasswordInput } from '../../Components/ui';

export default function ConfirmPassword() {
  const { data, setData, post, processing, errors } = useForm({ password: '' });

  return (
    <AuthShell title="Confirm your password" subtitle="This area handles money and account details, so please enter your password again.">
      <Head title="Confirm password" />
      <form
        className="space-y-5"
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          post('/confirm-password');
        }}
      >
        <FormField label="Password" required error={errors.password}>
          {(id) => <PasswordInput id={id} autoFocus autoComplete="current-password" value={data.password} error={errors.password} onChange={(event) => setData('password', event.target.value)} />}
        </FormField>
        <Button type="submit" loading={processing} className="w-full">
          Continue
        </Button>
      </form>
    </AuthShell>
  );
}
