import { Form } from '@inertiajs/react';
import type { ComponentProps, ReactNode } from 'react';
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

type ConfirmDialogProps = {
    /** The element that opens the dialog, e.g. a `<Button>`. */
    trigger: ReactNode;
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'default' | 'destructive';
    /** Spread from a Wayfinder controller action, e.g. `Controller.execute.form(id)`. */
    form: ComponentProps<typeof Form>;
};

/**
 * A dialog that requires an explicit confirmation click before submitting a
 * mutation. Use this any time an action is destructive or otherwise hard to
 * undo, so a stray or accidental click can't trigger it directly.
 */
export default function ConfirmDialog({
    trigger,
    title,
    description,
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    variant = 'default',
    form,
}: ConfirmDialogProps) {
    return (
        <Dialog>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                {description && (
                    <DialogDescription>{description}</DialogDescription>
                )}

                <Form {...form} className="contents">
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">
                                    {cancelLabel}
                                </Button>
                            </DialogClose>

                            <Button
                                variant={variant}
                                disabled={processing}
                                asChild
                            >
                                <button type="submit">{confirmLabel}</button>
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
