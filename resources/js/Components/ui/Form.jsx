import { forwardRef, useId, useState } from 'react';
import { IconEye, IconEyeOff } from '../Icons';

const inputBase =
  'h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 disabled:cursor-not-allowed disabled:bg-gray-50 dark:disabled:bg-gray-800';

function borderFor(error) {
  return error
    ? 'border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700'
    : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800';
}

/**
 * Label + control + hint/error wrapper. The child receives the generated id.
 */
export function FormField({ label, error, hint, required, children, className = '' }) {
  const id = useId();

  return (
    <div className={className}>
      {label && (
        <label htmlFor={id} className="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
          {label}
          {required && <span className="text-error-500"> *</span>}
        </label>
      )}
      {typeof children === 'function' ? children(id) : children}
      {error ? (
        <p className="mt-1.5 text-xs text-error-500">{error}</p>
      ) : (
        hint && <p className="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{hint}</p>
      )}
    </div>
  );
}

export const TextInput = forwardRef(function TextInput({ error, className = '', ...props }, ref) {
  return <input ref={ref} className={`${inputBase} ${borderFor(error)} ${className}`} {...props} />;
});

export function PasswordInput({ error, className = '', ...props }) {
  const [visible, setVisible] = useState(false);

  return (
    <div className="relative">
      <input
        type={visible ? 'text' : 'password'}
        className={`${inputBase} ${borderFor(error)} pr-11 ${className}`}
        {...props}
      />
      <button
        type="button"
        onClick={() => setVisible((value) => !value)}
        aria-label={visible ? 'Hide password' : 'Show password'}
        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"
      >
        {visible ? <IconEye className="fill-current" /> : <IconEyeOff className="fill-current" />}
      </button>
    </div>
  );
}

export function Select({ error, className = '', options = [], placeholder, ...props }) {
  return (
    <select className={`${inputBase} ${borderFor(error)} pr-10 ${className}`} {...props}>
      {placeholder && <option value="">{placeholder}</option>}
      {options.map((option) => (
        <option key={option.value} value={option.value}>
          {option.label}
        </option>
      ))}
    </select>
  );
}

export function Textarea({ error, className = '', rows = 4, ...props }) {
  return (
    <textarea
      rows={rows}
      className={`${inputBase} ${borderFor(error)} h-auto ${className}`}
      {...props}
    />
  );
}

export function Checkbox({ label, className = '', ...props }) {
  return (
    <label className={`flex cursor-pointer items-start gap-3 text-sm text-gray-700 dark:text-gray-400 ${className}`}>
      <input
        type="checkbox"
        className="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-500 accent-brand-500 focus:ring-brand-500"
        {...props}
      />
      <span>{label}</span>
    </label>
  );
}
