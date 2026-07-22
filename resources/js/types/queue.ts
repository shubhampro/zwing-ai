export type QueueDepth = {
    pending: number;
    processing: number;
};

export type QueueStatus = {
    available: boolean;
    connection: string;
    queues: {
        default: QueueDepth;
        'external-query': QueueDepth;
    };
    waiting: number;
    processing: number;
};
