import { Link, usePage } from '@inertiajs/react';
import {
    ExternalLink,
    Loader2,
    MessageSquare,
    Sparkles,
    X,
} from 'lucide-react';
import { useState } from 'react';
import {
    AssistantChatPanel,
    postAssistantJson,
} from '@/components/assistant/assistant-chat-panel';
import type {
    AssistantEngineOption,
    AssistantModel,
    ChatSession,
} from '@/components/assistant/assistant-chat-utils';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index as assistantIndex } from '@/routes/assistant';

type BootstrapPayload = {
    session: ChatSession;
    models: AssistantModel[];
    engineOptions: AssistantEngineOption[];
};

export function AssistantFloatingWidget() {
    const page = usePage<{
        auth: { user: { id: number; name: string } | null };
    }>();
    const { auth } = page.props;
    const currentUrl = page.url;

    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [resetting, setResetting] = useState(false);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [session, setSession] = useState<ChatSession | null>(null);
    const [models, setModels] = useState<AssistantModel[]>([]);
    const [engineOptions, setEngineOptions] = useState<AssistantEngineOption[]>(
        [],
    );
    const [bootstrapped, setBootstrapped] = useState(false);

    if (!auth.user || currentUrl.startsWith('/assistant')) {
        return null;
    }

    async function loadBootstrap(): Promise<void> {
        if (bootstrapped) {
            return;
        }

        setLoading(true);
        setLoadError(null);

        try {
            const res = await fetch('/assistant/bootstrap', {
                headers: { Accept: 'application/json' },
            });

            if (!res.ok) {
                const json = await res.json().catch(() => ({}));
                throw new Error(json?.message ?? `Request failed (${res.status})`);
            }

            const json = (await res.json()) as BootstrapPayload;
            setSession(json.session);
            setModels(json.models);
            setEngineOptions(json.engineOptions);
            setBootstrapped(true);
        } catch (err) {
            setLoadError(
                err instanceof Error ? err.message : 'Failed to load assistant',
            );
        } finally {
            setLoading(false);
        }
    }

    async function handleOpen(): Promise<void> {
        setOpen(true);
        await loadBootstrap();
    }

    async function handleReset(): Promise<void> {
        setResetting(true);
        setLoadError(null);

        try {
            const json = await postAssistantJson('/assistant/reset');
            setSession(json.session);
        } catch (err) {
            setLoadError(
                err instanceof Error ? err.message : 'Failed to reset chat',
            );
        } finally {
            setResetting(false);
        }
    }

    return (
        <>
            {open && (
                <div
                    className={cn(
                        'fixed right-4 bottom-24 z-50 flex w-[min(100vw-2rem,24rem)] flex-col overflow-hidden rounded-2xl border border-sidebar-border/70 bg-background shadow-2xl dark:border-sidebar-border',
                        'h-[min(70vh,32rem)] animate-in fade-in-0 slide-in-from-bottom-4 duration-200',
                    )}
                >
                    <div className="flex items-center justify-between border-b border-sidebar-border/70 bg-violet-500/5 px-3 py-2 dark:border-sidebar-border">
                        <div className="flex items-center gap-2 text-sm font-medium">
                            <Sparkles className="size-4 text-violet-600 dark:text-violet-300" />
                            Zwing AI Assistant
                        </div>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                asChild
                                title="Open full page"
                            >
                                <Link href={assistantIndex.url()}>
                                    <ExternalLink className="size-4" />
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                onClick={() => setOpen(false)}
                                title="Close"
                            >
                                <X className="size-4" />
                            </Button>
                        </div>
                    </div>

                    {loading && (
                        <div className="flex flex-1 items-center justify-center gap-2 text-sm text-muted-foreground">
                            <Loader2 className="size-4 animate-spin" />
                            Loading assistant...
                        </div>
                    )}

                    {!loading && loadError && (
                        <div className="flex flex-1 flex-col items-center justify-center gap-3 p-4 text-center">
                            <p className="text-sm text-destructive">{loadError}</p>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                    void loadBootstrap();
                                }}
                            >
                                Retry
                            </Button>
                        </div>
                    )}

                    {!loading && !loadError && session && (
                        <AssistantChatPanel
                            session={session}
                            onSessionChange={setSession}
                            models={models}
                            engineOptions={engineOptions}
                            compact
                            onReset={() => {
                                void handleReset();
                            }}
                            resetting={resetting}
                        />
                    )}
                </div>
            )}

            <Button
                type="button"
                onClick={() => {
                    if (open) {
                        setOpen(false);
                        return;
                    }

                    void handleOpen();
                }}
                className={cn(
                    'fixed right-4 bottom-4 z-50 size-14 rounded-full shadow-lg',
                    'bg-gradient-to-br from-violet-600 to-violet-500 text-white hover:from-violet-500 hover:to-violet-400',
                )}
                title="Zwing AI Assistant"
            >
                {open ? (
                    <X className="size-6" />
                ) : (
                    <MessageSquare className="size-6" />
                )}
            </Button>
        </>
    );
}
