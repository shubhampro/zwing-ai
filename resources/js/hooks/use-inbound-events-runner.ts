import { useSyncExternalStore } from 'react';
import {
    getSnapshot,
    subscribe,
} from '@/lib/inbound-events-runner-store';

export function useInboundEventsRunner() {
    return useSyncExternalStore(subscribe, getSnapshot, getSnapshot);
}
