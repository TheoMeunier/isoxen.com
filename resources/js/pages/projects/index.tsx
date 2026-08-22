import { Form, Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import CreateProjectController from '@/actions/App/Watch/Projects/Controllers/CreateProjectController';
import Heading from '@/components/heading';
import InputError from '@/components/molecules/forms/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/projects';
import type { Project } from '@/types/project';

export default function ProjectsIndex({ projects }: { projects: Project[] }) {
    return (
        <>
            <Head title="Projects" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Projects"
                        description="Create a project for each site or app you want to monitor."
                    />

                    <Dialog>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus />
                                New project
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>New project</DialogTitle>
                            <DialogDescription>
                                Give your project a name. You'll get an
                                ingestion token to configure in your app once
                                it's created.
                            </DialogDescription>

                            <Form
                                {...CreateProjectController.execute.form()}
                                resetOnSuccess
                                className="space-y-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">Name</Label>

                                            <Input
                                                id="name"
                                                name="name"
                                                required
                                                autoFocus
                                                placeholder="My website"
                                            />

                                            <InputError
                                                message={errors.name}
                                            />
                                        </div>

                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button variant="secondary">
                                                    Cancel
                                                </Button>
                                            </DialogClose>

                                            <Button
                                                disabled={processing}
                                                asChild
                                            >
                                                <button type="submit">
                                                    Create project
                                                </button>
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                {projects.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-sidebar-border/70 py-16 text-center dark:border-sidebar-border">
                        <p className="text-sm font-medium">
                            No projects yet
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Create your first project to get an ingestion
                            token for your app.
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {projects.map((project) => (
                            <Link
                                key={project.id}
                                href={show({
                                    project: project.id,
                                    category: 'requests',
                                })}
                                className="rounded-xl border border-sidebar-border/70 p-4 transition-colors hover:bg-sidebar-accent dark:border-sidebar-border"
                            >
                                <p className="font-medium">{project.name}</p>
                                <p className="text-sm text-muted-foreground">
                                    {project.slug}
                                </p>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

ProjectsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Projects',
            href: index(),
        },
    ],
};
