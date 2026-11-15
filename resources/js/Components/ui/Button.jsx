import { Link } from '@inertiajs/react';
import Spinner from './Spinner';

const variants = {
  primary: 'bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 disabled:bg-brand-300',
  secondary:
    'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700 dark:hover:bg-white/[0.03]',
  danger: 'bg-error-500 text-white shadow-theme-xs hover:bg-error-600 disabled:opacity-60',
  ghost: 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5',
  accent: 'bg-orange-500 text-white shadow-theme-xs hover:bg-orange-600',
};

const sizes = {
  sm: 'h-9 px-3 text-sm gap-1.5',
  md: 'h-11 px-4 text-sm gap-2',
  lg: 'h-12 px-6 text-base gap-2',
};

/**
 * One button for every panel. Pass `href` to render an Inertia link instead of a <button>.
 */
export default function Button({
  variant = 'primary',
  size = 'md',
  href,
  loading = false,
  disabled = false,
  className = '',
  children,
  type = 'button',
  ...props
}) {
  const classes = `inline-flex items-center justify-center rounded-lg font-medium transition disabled:cursor-not-allowed ${variants[variant]} ${sizes[size]} ${className}`;

  if (href) {
    return (
      <Link href={href} className={classes} {...props}>
        {children}
      </Link>
    );
  }

  return (
    <button type={type} disabled={disabled || loading} className={classes} {...props}>
      {loading && <Spinner className="h-4 w-4" />}
      {children}
    </button>
  );
}
