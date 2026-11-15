import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';

/**
 * Shows the server's flash success/error message as a dismissible toast.
 */
export default function Toast() {
  const { flash } = usePage().props;
  const [toast, setToast] = useState(null);

  useEffect(() => {
    if (flash?.success) {
      setToast({ tone: 'success', message: flash.success, id: Date.now() });
    } else if (flash?.error) {
      setToast({ tone: 'error', message: flash.error, id: Date.now() });
    }
  }, [flash?.success, flash?.error]);

  useEffect(() => {
    if (!toast) {
      return undefined;
    }

    const timer = setTimeout(() => setToast(null), 5000);

    return () => clearTimeout(timer);
  }, [toast]);

  if (!toast) {
    return null;
  }

  const success = toast.tone === 'success';

  return (
    <div className="pointer-events-none fixed inset-x-4 top-4 z-999999 flex justify-center sm:inset-x-auto sm:right-6 sm:top-6">
      <div
        role="status"
        className={`pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-theme-lg ${
          success
            ? 'border-success-200 bg-success-50 text-success-800 dark:border-success-500/30 dark:bg-gray-900 dark:text-success-500'
            : 'border-error-200 bg-error-50 text-error-800 dark:border-error-500/30 dark:bg-gray-900 dark:text-error-500'
        }`}
      >
        <span aria-hidden="true" className="font-bold">
          {success ? '✓' : '!'}
        </span>
        <p className="flex-1">{toast.message}</p>
        <button type="button" aria-label="Dismiss" onClick={() => setToast(null)} className="opacity-60 hover:opacity-100">
          ✕
        </button>
      </div>
    </div>
  );
}
