import { Form, Head } from '@inertiajs/react';
import { Check, Copy, Pencil } from 'lucide-react';
import { useState } from 'react';
import DeleteProjectController from '@/actions/App/Watch/Projects/Controllers/DeleteProjectController';
import EditProjectController from '@/actions/App/Watch/Projects/Controllers/EditProjectController';
import Heading from '@/components/heading';
import ConfirmDialog from '@/components/molecules/confirm-dialog';
import InputError from '@/components/molecules/forms/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import { index } from '@/routes/projects';
import type { Project } from '@/types/project';

export default function ProjectsShow({ project }: { project: Project }) {
    const [copiedText, copy] = useClipboard();
    const [isEditOpen, setIsEditOpen] = useState(false);

    return (
        <>
            <Head title={project.name} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading title={project.name} description={project.slug} />

                    <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
                        <DialogTrigger asChild>
                            <Button variant="outline">
                                <Pencil />
                                Edit
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>Edit project</DialogTitle>

                            <Form
                                {...EditProjectController.execute.form(
                                    project.id,
                                )}
                                onSuccess={() => setIsEditOpen(false)}
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
                                                defaultValue={project.name}
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
                                                    Save
                                                </button>
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <p className="font-medium">Ingestion token</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Configure your app's OpenTelemetry exporter to send
                        data to this project using this token.
                    </p>

                    {project.token && (
                        <div className="mt-3 flex items-center gap-2">
                            <code className="rounded-md bg-muted px-3 py-2 text-sm">
                                {project.token}
                            </code>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => copy(project.token as string)}
                            >
                                {copiedText === project.token ? (
                                    <Check />
                                ) : (
                                    <Copy />
                                )}
                            </Button>
                        </div>
                    )}
                </div>

                <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-sidebar-border/70 py-16 text-center dark:border-sidebar-border">
                    <p className="text-sm font-medium">
                        No data received yet
                    </p>
                    <p className="max-w-sm text-sm text-muted-foreground">
                        Once your app starts exporting OpenTelemetry data
                        with this token, requests, exceptions, jobs and more
                        will show up here.
                    </p>
                </div>

                <ConfirmDialog
                    trigger={
                        <Button variant="destructive" className="self-start">
                            Delete project
                        </Button>
                    }
                    title="Delete this project?"
                    description="This permanently deletes the project and revokes its ingestion token. This cannot be undone."
                    confirmLabel="Delete project"
                    variant="destructive"
                    form={DeleteProjectController.execute.form(project.id)}
                />
            </div>
        </>
    );
}

ProjectsShow.layout = {
    breadcrumbs: [
        {
            title: 'Projects',
            href: index(),
        },
    ],
};
