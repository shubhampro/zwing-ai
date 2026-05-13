import type {
    ActiveDatabaseContext,
    DatabaseConnectionOption,
} from '@/types/database-context';
import type { Auth } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            activeDatabaseContext: ActiveDatabaseContext | null;
            databaseConnectionsForSelector: DatabaseConnectionOption[];
            [key: string]: unknown;
        };
    }
}
