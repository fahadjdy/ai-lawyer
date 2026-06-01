import { z } from 'zod';

/**
 * Task form validation. Mirrors StoreTaskRequest / UpdateTaskRequest so the
 * client gives instant feedback while the server stays the source of truth.
 */
export const taskSchema = z.object({
    title: z.string().min(1, 'Title is required').max(255),
    description: z.string().max(10000).optional().or(z.literal('')),
    case_id: z.coerce.number().int().positive().nullable().optional(),
    status: z.string().min(1, 'Select a status'),
    priority: z.string().min(1, 'Select a priority'),
    due_at: z.string().optional().or(z.literal('')),
    assigned_to: z.coerce.number().int().positive().nullable().optional(),
});

export type TaskFormValues = z.infer<typeof taskSchema>;
