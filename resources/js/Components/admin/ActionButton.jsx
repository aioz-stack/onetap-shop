import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button, Checkbox, ConfirmDialog, FormField, Textarea, TextInput } from '../ui';

/**
 * A button that confirms, optionally collects input, and submits to the server.
 *
 * fields: [{ name, label, type: 'textarea' | 'text' | 'checkbox', required?, placeholder? }]
 */
export default function ActionButton({
  url,
  method = 'post',
  label,
  title,
  message,
  confirmLabel,
  variant = 'secondary',
  tone = 'primary',
  size = 'sm',
  fields = [],
  className = '',
}) {
  const [open, setOpen] = useState(false);
  const initial = Object.fromEntries(fields.map((field) => [field.name, field.type === 'checkbox' ? false : '']));
  const form = useForm(initial);

  const close = () => {
    setOpen(false);
    form.reset();
    form.clearErrors();
  };

  const submit = () => {
    form.submit(method, url, {
      preserveScroll: true,
      onSuccess: close,
    });
  };

  return (
    <>
      <Button variant={variant} size={size} className={className} onClick={() => setOpen(true)}>
        {label}
      </Button>
      <ConfirmDialog
        open={open}
        onClose={close}
        onConfirm={submit}
        title={title ?? label}
        message={message}
        confirmLabel={confirmLabel ?? label}
        tone={tone}
        processing={form.processing}
      >
        {fields.length > 0 && (
          <div className="mt-4 space-y-4">
            {fields.map((field) =>
              field.type === 'checkbox' ? (
                <Checkbox
                  key={field.name}
                  label={field.label}
                  checked={form.data[field.name]}
                  onChange={(event) => form.setData(field.name, event.target.checked)}
                />
              ) : (
                <FormField key={field.name} label={field.label} required={field.required} error={form.errors[field.name]}>
                  {(id) =>
                    field.type === 'textarea' ? (
                      <Textarea
                        id={id}
                        rows={3}
                        value={form.data[field.name]}
                        placeholder={field.placeholder}
                        error={form.errors[field.name]}
                        onChange={(event) => form.setData(field.name, event.target.value)}
                      />
                    ) : (
                      <TextInput
                        id={id}
                        value={form.data[field.name]}
                        placeholder={field.placeholder}
                        error={form.errors[field.name]}
                        onChange={(event) => form.setData(field.name, event.target.value)}
                      />
                    )
                  }
                </FormField>
              ),
            )}
          </div>
        )}
        {form.errors.status && <p className="mt-3 text-sm text-error-500">{form.errors.status}</p>}
      </ConfirmDialog>
    </>
  );
}
