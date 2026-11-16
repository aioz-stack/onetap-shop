/**
 * On/off switch. `label` names what it controls for screen readers; the visible On/Off text
 * makes the state clear without relying on colour.
 */
export default function Switch({ checked, onChange, label, disabled = false, busy = false }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      aria-label={label}
      aria-busy={busy || undefined}
      disabled={disabled || busy}
      onClick={() => onChange(!checked)}
      className="inline-flex shrink-0 items-center gap-2 rounded-full text-sm font-medium focus-visible:outline-hidden focus-visible:ring-3 focus-visible:ring-brand-500/20 disabled:cursor-not-allowed disabled:opacity-60"
    >
      <span className={`w-7 text-right ${checked ? 'text-brand-700 dark:text-brand-400' : 'text-gray-500 dark:text-gray-400'}`}>{checked ? 'On' : 'Off'}</span>
      <span className={`relative h-6 w-11 rounded-full transition-colors ${checked ? 'bg-brand-500' : 'bg-gray-300 dark:bg-gray-700'}`}>
        <span className={`absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow-theme-xs transition-transform ${checked ? 'translate-x-5' : 'translate-x-0'}`} />
      </span>
    </button>
  );
}
