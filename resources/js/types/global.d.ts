import type { Auth } from '@/types/auth';
import type { QueueStatus } from '@/types/queue';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            queueStatus: QueueStatus | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
