export type SavedQuerySummary = {
    id: number;
    name: string;
    sql: string;
    bindings: Record<string, unknown>;
    updated_at: string | null;
};
