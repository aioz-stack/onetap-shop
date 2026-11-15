import { useEffect, useRef } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, EmptyState } from '../../../Components/ui';
import AccountLayout from '../../../Layouts/AccountLayout';
import { formatDateTime } from '../../../lib/format';

const POLL_MS = 5000;

function Thread({ active }) {
  const form = useForm({ body: '' });
  const bottom = useRef(null);

  // Poll for new messages while the thread is open.
  useEffect(() => {
    const timer = setInterval(() => router.reload({ only: ['active', 'conversations', 'counts'] }), POLL_MS);

    return () => clearInterval(timer);
  }, [active.id]);

  // Braces matter: newer browsers return a Promise from scrollIntoView(), which must not become the effect cleanup.
  useEffect(() => {
    bottom.current?.scrollIntoView({ block: 'end' });
  }, [active.messages.length]);

  const send = (event) => {
    event.preventDefault();

    if (!form.data.body.trim()) {
      return;
    }

    form.post(`/account/chat/${active.id}`, { preserveScroll: true, onSuccess: () => form.reset('body') });
  };

  return (
    <div className="flex h-[65vh] min-h-[420px] flex-col">
      <header className="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
        <Link href={`/shops/${active.shop?.slug}`} className="font-semibold text-gray-800 hover:text-brand-600 dark:text-white/90">
          {active.shop?.name}
        </Link>
        <Link href="/account/chat" className="text-sm text-gray-500 lg:hidden">All chats</Link>
      </header>
      <ol className="flex-1 space-y-3 overflow-y-auto px-4 py-4" aria-live="polite">
        {active.messages.length === 0 && <li className="py-10 text-center text-sm text-gray-500">Say hello to {active.shop?.name}!</li>}
        {active.messages.map((message) => (
          <li key={message.id} className={`flex ${message.mine ? 'justify-end' : 'justify-start'}`}>
            <div className={`max-w-[80%] rounded-2xl px-4 py-2 text-sm ${message.mine ? 'rounded-br-sm bg-brand-500 text-white' : 'rounded-bl-sm bg-gray-100 text-gray-800 dark:bg-white/10 dark:text-white/90'}`}>
              <p className="whitespace-pre-line break-words">{message.body}</p>
              <p className={`mt-1 text-[10px] ${message.mine ? 'text-brand-100' : 'text-gray-500'}`}>
                {message.auto && 'Auto-reply · '}
                {formatDateTime(message.created_at)}
              </p>
            </div>
          </li>
        ))}
        <li ref={bottom} />
      </ol>
      <form onSubmit={send} className="flex gap-2 border-t border-gray-100 p-3 dark:border-gray-800">
        <input
          value={form.data.body}
          onChange={(event) => form.setData('body', event.target.value)}
          placeholder="Type a message"
          aria-label="Message"
          maxLength={2000}
          className="h-11 flex-1 rounded-full border border-gray-300 bg-transparent px-4 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90"
        />
        <Button type="submit" loading={form.processing} disabled={!form.data.body.trim()}>
          Send
        </Button>
      </form>
      {form.errors.body && <p className="px-4 pb-2 text-xs text-error-500">{form.errors.body}</p>}
    </div>
  );
}

export default function Chat({ conversations, active }) {
  return (
    <AccountLayout title="Chat">
      <Head title="Chat" />
      <div className="grid overflow-hidden rounded-2xl border border-gray-200 bg-white lg:grid-cols-[280px_1fr] dark:border-gray-800 dark:bg-white/[0.03]">
        <ul className={`divide-y divide-gray-100 border-gray-100 lg:border-r dark:divide-gray-800 dark:border-gray-800 ${active ? 'hidden lg:block' : ''}`}>
          {conversations.length ? (
            conversations.map((conversation) => (
              <li key={conversation.id}>
                <Link href={`/account/chat/${conversation.id}`} className={`block px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/[0.02] ${active?.id === conversation.id ? 'bg-brand-50/60 dark:bg-brand-500/10' : ''}`}>
                  <span className="flex items-center justify-between gap-2">
                    <span className={`truncate text-sm ${conversation.unread ? 'font-semibold text-gray-800 dark:text-white/90' : 'text-gray-700 dark:text-gray-300'}`}>{conversation.shop?.name}</span>
                    {conversation.unread && <span className="h-2 w-2 shrink-0 rounded-full bg-orange-500" aria-label="Unread" />}
                  </span>
                  <span className="block truncate text-xs text-gray-500">{conversation.last_message ?? 'No messages yet'}</span>
                </Link>
              </li>
            ))
          ) : (
            <li>
              <EmptyState title="No chats yet" description="Open a product or shop and tap Chat to message the seller." />
            </li>
          )}
        </ul>
        <div className={`min-w-0 ${active ? '' : 'hidden lg:block'}`}>
          {active ? <Thread active={active} /> : <EmptyState title="Select a conversation" />}
        </div>
      </div>
    </AccountLayout>
  );
}
