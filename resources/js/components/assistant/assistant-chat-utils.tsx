export type ChatMessage = {
    role: 'user' | 'assistant';
    content: string;
    sent_at: string;
};

export type ChatSession = {
    id: number;
    title: string;
    messages: ChatMessage[];
    context?: {
        engine?: AssistantEngine;
        intent?: 'predict' | 'help' | 'unknown' | 'invoice_lookup' | null;
        provider?: string | null;
        needs_input?: boolean;
        fields_used?: Record<string, unknown>;
    };
};

export type AssistantEngine = 'groq' | 'gemini' | 'gemini-embedding' | 'zwing';

export type AssistantEngineOption = {
    value: AssistantEngine;
    label: string;
};

export type AssistantModel = {
    model_name: string;
    target_column: string;
    schema_csv: string;
    problem_type: string;
};

export function getXsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

export function renderChatContent(content: string) {
    return content.split('\n').map((line, index) => {
        const parts = line.split(/(\*\*[^*]+\*\*)/g);

        return (
            <span key={index}>
                {parts.map((part, partIndex) =>
                    part.startsWith('**') && part.endsWith('**') ? (
                        <strong key={partIndex}>{part.slice(2, -2)}</strong>
                    ) : (
                        <span key={partIndex}>{part}</span>
                    ),
                )}
                {index < content.split('\n').length - 1 ? <br /> : null}
            </span>
        );
    });
}
