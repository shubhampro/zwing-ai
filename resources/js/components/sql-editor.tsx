import Editor, { type Monaco } from '@monaco-editor/react';
import type { languages, Position, editor } from 'monaco-editor';
import { useCallback, useRef } from 'react';

type SqlEditorProps = {
    value: string;
    onChange: (value: string) => void;
    schemaTables?: string[];
    height?: string;
};

function registerSqlCompletions(
    monaco: Monaco,
    schemaTables: string[],
): { dispose: () => void } {
    const disposable = monaco.languages.registerCompletionItemProvider('sql', {
        triggerCharacters: [' ', '.', ',', '(', '`'],
        provideCompletionItems: (
            model: editor.ITextModel,
            position: Position,
        ): languages.ProviderResult<languages.CompletionList> => {
            const word = model.getWordUntilPosition(position);
            const range = {
                startLineNumber: position.lineNumber,
                endLineNumber: position.lineNumber,
                startColumn: word.startColumn,
                endColumn: word.endColumn,
            };

            const suggestions = schemaTables.map((table) => ({
                label: table,
                kind: monaco.languages.CompletionItemKind.Class,
                insertText: table,
                detail: 'Zwing table',
                range,
            }));

            return { suggestions };
        },
    });

    return disposable;
}

export default function SqlEditor({
    value,
    onChange,
    schemaTables = [],
    height = '420px',
}: SqlEditorProps) {
    const completionDisposable = useRef<{ dispose: () => void } | null>(null);

    const handleMount = useCallback(
        (_editor: editor.IStandaloneCodeEditor, monaco: Monaco) => {
            completionDisposable.current?.dispose();

            if (schemaTables.length > 0) {
                completionDisposable.current = registerSqlCompletions(
                    monaco,
                    schemaTables,
                );
            }
        },
        [schemaTables],
    );

    return (
        <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
            <Editor
                height={height}
                defaultLanguage="sql"
                theme="vs-dark"
                value={value}
                onChange={(nextValue) => onChange(nextValue ?? '')}
                onMount={handleMount}
                options={{
                    minimap: { enabled: false },
                    fontSize: 14,
                    lineNumbers: 'on',
                    scrollBeyondLastLine: false,
                    wordWrap: 'on',
                    tabSize: 2,
                    automaticLayout: true,
                    suggestOnTriggerCharacters: true,
                    quickSuggestions: {
                        other: true,
                        comments: false,
                        strings: false,
                    },
                    padding: { top: 12, bottom: 12 },
                }}
            />
        </div>
    );
}
