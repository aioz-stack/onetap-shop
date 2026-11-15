const tones = {
  brand: 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
  orange: 'bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-400',
  success: 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500',
  warning: 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-500',
  error: 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
  gray: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
};

/**
 * Dashboard KPI tile.
 */
export default function StatCard({ label, value, hint, icon: Icon, tone = 'brand', compact = false }) {
  return (
    <div className={`rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] ${compact ? 'p-4' : 'p-5'}`}>
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-sm text-gray-500 dark:text-gray-400">{label}</p>
          <p className={`mt-2 truncate font-bold text-gray-800 dark:text-white/90 ${compact ? 'text-xl' : 'text-2xl'}`}>{value}</p>
        </div>
        {Icon && (
          <span className={`h-11 w-11 shrink-0 items-center justify-center rounded-xl ${compact ? 'hidden sm:flex' : 'flex'} ${tones[tone]}`}>
            <Icon className="h-5 w-5" />
          </span>
        )}
      </div>
      {hint && <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">{hint}</p>}
    </div>
  );
}
