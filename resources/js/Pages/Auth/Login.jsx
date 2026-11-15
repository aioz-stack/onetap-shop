import { Head, Link, useForm } from '@inertiajs/react';
import AuthShell from '../../Components/AuthShell';
import { Button, Checkbox, FormField, PasswordInput, TextInput } from '../../Components/ui';

export default function Login() {
  const { data, setData, post, processing, errors, reset } = useForm({
    login: '',
    password: '',
    remember: false,
  });

  const submit = (event) => {
    event.preventDefault();
    post('/login', { onFinish: () => reset('password') });
  };

  return (
    <AuthShell
      title="Welcome back"
      subtitle="Sign in with your email or username."
      footer={
        <>
          New to Onetap?{' '}
          <Link href="/register" className="font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">
            Create an account
          </Link>
        </>
      }
    >
      <Head title="Log in" />

      <form onSubmit={submit} className="space-y-5" noValidate>
        <FormField label="Email or username" error={errors.login} required>
          {(id) => (
            <TextInput
              id={id}
              name="login"
              autoComplete="username"
              autoFocus
              value={data.login}
              onChange={(event) => setData('login', event.target.value)}
              error={errors.login}
              placeholder="you@example.com"
            />
          )}
        </FormField>

        <FormField label="Password" error={errors.password} required>
          {(id) => (
            <PasswordInput
              id={id}
              name="password"
              autoComplete="current-password"
              value={data.password}
              onChange={(event) => setData('password', event.target.value)}
              error={errors.password}
              placeholder="Your password"
            />
          )}
        </FormField>

        <Checkbox label="Keep me signed in" checked={data.remember} onChange={(event) => setData('remember', event.target.checked)} />

        <Button type="submit" loading={processing} className="w-full">
          Log in
        </Button>
      </form>
    </AuthShell>
  );
}
