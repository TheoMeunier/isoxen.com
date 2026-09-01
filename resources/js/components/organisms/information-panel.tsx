import { Form, router } from '@inertiajs/react';
import { Check, Copy, Pencil } from 'lucide-react';
import { useState } from 'react';
import DeleteProjectController from '@/actions/App/Watch/Projects/Controllers/DeleteProjectController';
import EditProjectController from '@/actions/App/Watch/Projects/Controllers/EditProjectController';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useClipboard } from '@/hooks/use-clipboard';
import type { Project } from '@/types/project';

/** Kept in sync with Project::RETENTION_CHOICES. */
const RETENTION_CHOICES = [7, 14, 30, 90];

/** Matches the column default, for a project stored before retention existed. */
const DEFAULT_RETENTION = 30;

export function InformationPanel({ project }: { project: Project }) {
    const [copiedText, copy] = useClipboard();
    const [isEditOpen, setIsEditOpen] = useState(false);

    return (
        <div className="flex flex-col gap-4">
            <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <p className="font-medium">General</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            The project's display name.
                        </p>
                    </div>

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

                                            <InputError message={errors.name} />
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

                <div className="mt-4 flex items-center justify-between gap-4 border-t border-sidebar-border/70 pt-4 dark:border-sidebar-border">
                    <div>
                        <Label htmlFor="retention_days">Data retention</Label>

                        <p className="mt-1 text-sm text-muted-foreground">
                            Traces, logs and metrics older than this are deleted
                            every night.
                        </p>
                    </div>

                    <Select
                        value={String(
                            project.retentionDays ?? DEFAULT_RETENTION,
                        )}
                        onValueChange={(days) =>
                            router.put(
                                EditProjectController.execute.url(project.id),
                                { name: project.name, retention_days: days },
                                { preserveScroll: true },
                            )
                        }
                    >
                        <SelectTrigger id="retention_days" className="w-40">
                            <SelectValue
                                placeholder={`${DEFAULT_RETENTION} days`}
                            />
                        </SelectTrigger>
                        <SelectContent>
                            {RETENTION_CHOICES.map((days) => (
                                <SelectItem key={days} value={String(days)}>
                                    {days} days
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p className="font-medium">Ingestion token</p>
                <p className="mt-1 text-sm text-muted-foreground">
                    Configure your app's OpenTelemetry exporter to send data to
                    this project using this token.
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

            <div className="space-y-4 rounded-lg border border-destructive/20 bg-destructive/5 p-4">
                <div>
                    <p className="font-medium text-destructive">Danger zone</p>
                    <p className="text-sm text-destructive/80">
                        Deleting a project permanently removes it and revokes
                        its ingestion token. This cannot be undone.
                    </p>
                </div>

                <ConfirmDialog
                    trigger={
                        <Button variant="destructive">Delete project</Button>
                    }
                    title="Delete this project?"
                    description="This permanently deletes the project and revokes its ingestion token. This cannot be undone."
                    confirmLabel="Delete project"
                    variant="destructive"
                    form={DeleteProjectController.execute.form(project.id)}
                />
            </div>
        </div>
    );
}
