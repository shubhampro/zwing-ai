import { Head } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { AssistantChatPanel, postAssistantJson } from '@/components/assistant/assistant-chat-panel';
import type {
    AssistantEngineOption,
    AssistantModel,
    ChatSession,
} from '@/components/assistant/assistant-chat-utils';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

export default function AssistantIndex({
    session: initialSession,
    models,
    engineOptions,
}: {
    session: ChatSession;
    models: AssistantModel[];
    engineOptions: AssistantEngineOption[];
}) {
    const [session, setSession] = useState(initialSession);
    const [resetting, setResetting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function handleReset() {
        setError(null);
        setResetting(true);

        try {
            const json = await postAssistantJson('/assistant/reset');
            setSession(json.session);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to reset chat');
        } finally {
            setResetting(false);
        }
    }

    return (
        <>
            <Head title="AI Assistant" />
            <div className="flex h-[calc(100vh-8rem)] flex-col gap-4 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="AI Assistant"
                        description="Store credit predictions, invoice lookup, and reconciliation help."
                    />
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            void handleReset();
                        }}
                        disabled={resetting}
                    >
                        <RotateCcw className="mr-2 size-4" />
                        New chat
                    </Button>
                </div>

                {error && (
                    <div className="text-sm text-destructive">{error}</div>
                )}

                <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border">
                    <AssistantChatPanel
                        session={session}
                        onSessionChange={setSession}
                        models={models}
                        engineOptions={engineOptions}
                    />
                </div>
            </div>
        </>
    );
}
