import { Head, Link, usePage } from '@inertiajs/react';
import GuestLayout from '../../Layouts/GuestLayout';

const messages = {
  403: ['Access denied', 'Your account does not have permission to open this page.'],
  404: ['Page not found', 'We can’t find the page you are looking for.'],
  429: ['Too many requests', 'Please wait a moment and try again.'],
  500: ['Something went wrong', 'An unexpected error occurred on our side. Please try again.'],
  503: ['Down for maintenance', 'Onetap is being updated. Please check back shortly.'],
};

export default function Error({ status }) {
  const { auth } = usePage().props;
  const [title, description] = messages[status] ?? messages[500];

  return (
    <GuestLayout>
      <Head title={title} />
      <div className="flex min-h-screen flex-col items-center justify-center bg-white p-6 text-center dark:bg-gray-900">
        <img src="/images/logo/logo-icon.svg" alt="Onetap" className="h-12 w-12" />
        <p className="mt-8 text-title-md font-bold text-brand-500">{status}</p>
        <h1 className="mt-2 text-xl font-semibold text-gray-800 dark:text-white/90">{title}</h1>
        <p className="mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">{description}</p>
        <Link
          href={auth?.user?.home ?? '/'}
          className="mt-8 inline-flex items-center rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white hover:bg-brand-600"
        >
          {auth?.user ? 'Back to my dashboard' : 'Back to the marketplace'}
        </Link>
      </div>
    </GuestLayout>
  );
}
