import { Loader2, RotateCcw, Send, Sparkles } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    getXsrfToken,
    renderChatContent,
    type AssistantEngine,
    type AssistantEngineOption,
    type AssistantModel,
    type ChatSession,
} from '@/components/assistant/assistant-chat-utils';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type AssistantChatPanelProps = {
    session: ChatSession;
    onSessionChange: (session: ChatSession) => void;
    models: AssistantModel[];
    engineOptions: AssistantEngineOption[];
    compact?: boolean;
    onReset?: () => void;
    resetting?: boolean;
};

async function postAssistantJson(
    url: string,
    body?: Record<string, unknown>,
): Promise<{ session: ChatSession }> {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getXsrfToken(),
            Accept: 'application/json',
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (!res.ok) {
        const json = await res.json().catch(() => ({}));
        throw new Error(json?.message ?? `Request failed (${res.status})`);
    }

    return res.json();
}

export function AssistantChatPanel({
    session,
    onSessionChange,
    models,
    engineOptions,
    compact = false,
    onReset,
    resetting = false,
}: AssistantChatPanelProps) {
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);
    const [pendingUserMessage, setPendingUserMessage] = useState<string | null>(
        null,
    );
    const [error, setError] = useState<string | null>(null);
    const [engine, setEngine] = useState<AssistantEngine>(
        session.context?.engine ?? 'groq',
    );
    const bottomRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setEngine(session.context?.engine ?? 'groq');
    }, [session.context?.engine, session.id]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [session.messages, sending, pendingUserMessage]);

    async function handleSend(messageText?: string) {
        const text = (messageText ?? input).trim();
        if (!text || sending) {
            return;
        }

        setError(null);
        setSending(true);
        setPendingUserMessage(text);
        setInput('');

        try {
            const json = await postAssistantJson('/assistant/messages', {
                session_id: session.id,
                message: text,
                engine,
            });
            onSessionChange(json.session);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to send message');
            setInput(text);
        } finally {
            setSending(false);
            setPendingUserMessage(null);
        }
    }

    return (
        <div className="flex min-h-0 flex-1 flex-col overflow-hidden">
            <div className="flex items-center gap-2 border-b border-violet-500/20 bg-violet-500/10 px-3 py-2.5">
                <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-violet-500/15 text-violet-700 dark:text-violet-300">
                    <Sparkles className="size-4" />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">Zwing AI</p>
                    <p className="truncate text-xs text-muted-foreground">
                        {models.length > 0
                            ? `${models.length} prediction model(s) · Invoice lookup`
                            : 'Predictions & invoice lookup'}
                    </p>
                </div>
                <Select
                    value={engine}
                    onValueChange={(value) => setEngine(value as AssistantEngine)}
                    disabled={sending}
                >
                    <SelectTrigger size="sm" className="w-[11.5rem] shrink-0">
                        <SelectValue placeholder="Select model" />
                    </SelectTrigger>
                    <SelectContent>
                        {engineOptions.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {onReset && (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8 shrink-0"
                        onClick={onReset}
                        disabled={sending || resetting}
                        title="New chat"
                    >
                        {resetting ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <RotateCcw className="size-4" />
                        )}
                    </Button>
                )}
            </div>

            <div
                className={`flex-1 space-y-3 overflow-y-auto p-3 ${compact ? 'text-sm' : ''}`}
            >
                {session.messages.map((message, index) => {
                    const isUser = message.role === 'user';

                    return (
                        <div
                            key={`${message.sent_at}-${index}`}
                            className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}
                        >
                            <div
                                className={`max-w-[90%] rounded-2xl px-3 py-2 text-sm leading-6 ${
                                    isUser
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-foreground'
                                }`}
                            >
                                {renderChatContent(message.content)}
                            </div>
                        </div>
                    );
                })}
                {sending && pendingUserMessage && (
                    <div className="flex justify-end">
                        <div className="max-w-[90%] rounded-2xl bg-primary px-3 py-2 text-sm leading-6 text-primary-foreground">
                            {renderChatContent(pendingUserMessage)}
                        </div>
                    </div>
                )}
                {sending && (
                    <div className="flex justify-start">
                        <div className="flex max-w-[90%] items-center gap-2 rounded-2xl bg-muted px-3 py-2.5 text-sm text-muted-foreground">
                            <Loader2 className="size-4 shrink-0 animate-spin text-violet-600 dark:text-violet-300" />
                            <span>Thinking...</span>
                        </div>
                    </div>
                )}
                <div ref={bottomRef} />
            </div>

            {error && (
                <div className="px-3 pb-1 text-xs text-destructive">{error}</div>
            )}

            <form
                className="flex items-end gap-2 border-t border-sidebar-border/70 p-3 dark:border-sidebar-border"
                onSubmit={(e) => {
                    e.preventDefault();
                    void handleSend();
                }}
            >
                <textarea
                    value={input}
                    onChange={(e) => setInput(e.target.value)}
                    placeholder={sending ? 'Waiting for response...' : 'Ask Zwing AI...'}
                    rows={compact ? 1 : 2}
                    disabled={sending}
                    className="min-h-[40px] flex-1 resize-none rounded-xl border border-input bg-background px-3 py-2 text-sm outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-60"
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            void handleSend();
                        }
                    }}
                />
                <Button
                    type="submit"
                    size="icon"
                    className="size-9 shrink-0"
                    disabled={sending || !input.trim()}
                >
                    {sending ? (
                        <Loader2 className="size-4 animate-spin" />
                    ) : (
                        <Send className="size-4" />
                    )}
                </Button>
            </form>
        </div>
    );
}

export { postAssistantJson };
