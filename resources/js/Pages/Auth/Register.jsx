import { Head, Link, useForm } from '@inertiajs/react';
import AuthShell from '../../Components/AuthShell';
import { Button, Checkbox, FormField, PasswordInput, TextInput } from '../../Components/ui';

export default function Register({ referralCode }) {
  const { data, setData, post, processing, errors, reset } = useForm({
    name: '',
    username: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    referral_code: referralCode ?? '',
    terms: false,
  });

  const submit = (event) => {
    event.preventDefault();
    post('/register', { onFinish: () => reset('password', 'password_confirmation') });
  };

  const field = (name, label, props = {}, required = true) => (
    <FormField label={label} error={errors[name]} required={required} hint={props.hint}>
      {(id) => (
        <TextInput
          id={id}
          name={name}
          value={data[name]}
          onChange={(event) => setData(name, event.target.value)}
          error={errors[name]}
          {...props}
          hint={undefined}
        />
      )}
    </FormField>
  );

  return (
    <AuthShell
      title="Create your account"
      subtitle="Shop from local businesses and pay cash on delivery."
      footer={
        <>
          Already have an account?{' '}
          <Link href="/login" className="font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">
            Log in
          </Link>
        </>
      }
    >
      <Head title="Sign up" />

      <form onSubmit={submit} className="space-y-4" noValidate>
        {field('name', 'Full name', { autoComplete: 'name', placeholder: 'Juan Dela Cruz', autoFocus: true })}

        <div className="grid gap-4 sm:grid-cols-2">
          {field('username', 'Username', { autoComplete: 'username', placeholder: 'juandc' })}
          {field('phone', 'Mobile number', { type: 'tel', autoComplete: 'tel', placeholder: '09171234567' }, false)}
        </div>

        {field('email', 'Email', { type: 'email', autoComplete: 'email', placeholder: 'you@example.com' })}

        <div className="grid gap-4 sm:grid-cols-2">
          <FormField label="Password" error={errors.password} required hint="At least 8 characters with letters and numbers.">
            {(id) => (
              <PasswordInput
                id={id}
                autoComplete="new-password"
                value={data.password}
                onChange={(event) => setData('password', event.target.value)}
                error={errors.password}
              />
            )}
          </FormField>
          <FormField label="Confirm password" error={errors.password_confirmation} required>
            {(id) => (
              <PasswordInput
                id={id}
                autoComplete="new-password"
                value={data.password_confirmation}
                onChange={(event) => setData('password_confirmation', event.target.value)}
                error={errors.password_confirmation}
              />
            )}
          </FormField>
        </div>

        {field('referral_code', 'Referral code', { placeholder: 'Optional' }, false)}

        <div>
          <Checkbox
            checked={data.terms}
            onChange={(event) => setData('terms', event.target.checked)}
            label="I agree to the Onetap Terms of Service and Privacy Policy."
          />
          {errors.terms && <p className="mt-1.5 text-xs text-error-500">{errors.terms}</p>}
        </div>

        <Button type="submit" loading={processing} className="w-full">
          Create account
        </Button>
      </form>
    </AuthShell>
  );
}
