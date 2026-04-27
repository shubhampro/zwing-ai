export type DatabaseConnectionOption = {
    id: number;
    slug: string;
    label: string | null;
    driver: string;
    connection_group: string;
    access_mode: string;
};

export type ActiveDatabaseContext = {
    connection_slug: string;
    database: string | null;
    connection_label: string;
    driver: string;
};
