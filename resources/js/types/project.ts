export type Project = {
    id: number;
    name: string;
    slug: string;
    token: string | null;
    retentionDays: number | null;
    createdAt: string | null;
};
