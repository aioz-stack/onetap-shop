export default function Spinner({ className = 'h-5 w-5' }) {
  return (
    <span
      role="status"
      aria-label="Loading"
      className={`inline-block animate-spin rounded-full border-2 border-current border-t-transparent ${className}`}
    />
  );
}

export function LoadingState({ label = 'Loading…' }) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 py-16 text-gray-500 dark:text-gray-400">
      <Spinner className="h-8 w-8 text-brand-500" />
      <p className="text-sm">{label}</p>
    </div>
  );
}
