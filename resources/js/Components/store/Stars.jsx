const STAR = 'M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.9l-5.2 2.7 1-5.8L1.5 7.7l5.9-.9L10 1.5z';

/**
 * Read-only star rating (supports fractions).
 */
export default function Stars({ value = 0, size = 'h-4 w-4' }) {
  return (
    <span className="inline-flex items-center" aria-label={`${Number(value).toFixed(1)} out of 5 stars`} role="img">
      {[1, 2, 3, 4, 5].map((star) => {
        const fill = Math.max(0, Math.min(1, value - star + 1));

        return (
          <svg key={star} viewBox="0 0 20 20" className={size} aria-hidden="true">
            <defs>
              <linearGradient id={`star-${star}-${fill}`}>
                <stop offset={`${fill * 100}%`} stopColor="#f79009" />
                <stop offset={`${fill * 100}%`} stopColor="#d0d5dd" />
              </linearGradient>
            </defs>
            <path d={STAR} fill={`url(#star-${star}-${fill})`} />
          </svg>
        );
      })}
    </span>
  );
}

/**
 * Clickable 1–5 star picker for review forms.
 */
export function StarInput({ value, onChange }) {
  return (
    <div className="flex gap-1" role="radiogroup" aria-label="Rating">
      {[1, 2, 3, 4, 5].map((star) => (
        <button
          key={star}
          type="button"
          role="radio"
          aria-checked={value === star}
          aria-label={`${star} star${star > 1 ? 's' : ''}`}
          onClick={() => onChange(star)}
          className="rounded p-0.5 focus:outline-hidden focus:ring-2 focus:ring-brand-500/40"
        >
          <svg viewBox="0 0 20 20" className="h-7 w-7">
            <path d={STAR} fill={star <= value ? '#f79009' : '#d0d5dd'} />
          </svg>
        </button>
      ))}
    </div>
  );
}
