import { format } from 'sql-formatter';

export type FormatSqlResult =
    | { success: true; sql: string }
    | { success: false; message: string };

export function formatSql(sql: string): FormatSqlResult {
    const trimmed = sql.trim();

    if (trimmed === '') {
        return { success: false, message: 'Nothing to format.' };
    }

    try {
        const formatted = format(trimmed, {
            language: 'mysql',
            tabWidth: 2,
            keywordCase: 'upper',
            linesBetweenQueries: 1,
        });

        return { success: true, sql: formatted };
    } catch {
        return {
            success: false,
            message: 'Could not format SQL. Check syntax and try again.',
        };
    }
}
