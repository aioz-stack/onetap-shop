import Button from './Button';

function StateShell({ icon, title, description, action, tone }) {
  return (
    <div className="flex flex-col items-center justify-center px-6 py-14 text-center">
      <div
        className={`mb-4 flex h-14 w-14 items-center justify-center rounded-full text-2xl ${
          tone === 'error'
            ? 'bg-error-50 text-error-500 dark:bg-error-500/15'
            : 'bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500'
        }`}
        aria-hidden="true"
      >
        {icon}
      </div>
      <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">{title}</h3>
      {description && <p className="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">{description}</p>}
      {action && <div className="mt-5">{action}</div>}
    </div>
  );
}

export function EmptyState({ title = 'Nothing here yet', description, action, icon = '○' }) {
  return <StateShell icon={icon} title={title} description={description} action={action} />;
}

export function ErrorState({ title = 'Something went wrong', description = 'Please try again.', onRetry }) {
  return (
    <StateShell
      tone="error"
      icon="!"
      title={title}
      description={description}
      action={
        onRetry && (
          <Button variant="secondary" onClick={onRetry}>
            Try again
          </Button>
        )
      }
    />
  );
}
