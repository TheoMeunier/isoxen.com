import { index, show } from '@/routes/projects';
import type { ObservabilityCategory } from '@/types/observability';
import type { Project } from '@/types/project';

/**
 * The two-crumb trail every project sub-page shares: back to the project list, then the project
 * itself linking to the given category. Each `projects/*` page passes its own fixed
 * category -- there is no longer a single page that renders every category generically.
 */
export function projectBreadcrumbs(
    project: Project,
    category: ObservabilityCategory | 'overview' | 'information',
) {
    return [
        {
            title: 'Projects',
            href: index(),
        },
        {
            title: project.name,
            href: show({ project: project.id, category }),
        },
    ];
}
