export type Project = {
    id: number;
    name: string;
    slug: string;
    token: string | null;
    alertErrorRateThreshold: number | null;
    createdAt: string | null;
};
