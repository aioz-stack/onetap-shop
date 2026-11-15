/**
 * − [n] + stepper. Calls onChange with the clamped value.
 */
export default function QuantityInput({ value, onChange, min = 1, max = 99, disabled = false }) {
  const set = (next) => onChange(Math.max(min, Math.min(max, Number.isFinite(next) ? next : min)));
  const button = 'flex h-9 w-9 items-center justify-center text-lg text-gray-600 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-300 dark:hover:bg-white/5';

  return (
    <div className="inline-flex items-center overflow-hidden rounded-lg border border-gray-300 dark:border-gray-700">
      <button type="button" className={button} aria-label="Decrease quantity" disabled={disabled || value <= min} onClick={() => set(value - 1)}>
        −
      </button>
      <input
        type="number"
        inputMode="numeric"
        aria-label="Quantity"
        className="h-9 w-12 border-x border-gray-300 bg-transparent text-center text-sm text-gray-800 [appearance:textfield] focus:outline-hidden dark:border-gray-700 dark:text-white/90 [&::-webkit-inner-spin-button]:appearance-none"
        value={value}
        min={min}
        max={max}
        disabled={disabled}
        onChange={(event) => set(parseInt(event.target.value, 10))}
      />
      <button type="button" className={button} aria-label="Increase quantity" disabled={disabled || value >= max} onClick={() => set(value + 1)}>
        +
      </button>
    </div>
  );
}
