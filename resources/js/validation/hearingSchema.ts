import { z } from 'zod';

/**
 * Hearing form validation. Mirrors Store/UpdateHearingRequest. A hearing always
 * belongs to a case, so `case_id` is required.
 */
export const hearingSchema = z.object({
    case_id: z.coerce.number({ invalid_type_error: 'Select a case' }).int().positive('Select a case'),
    scheduled_at: z.string().min(1, 'Pick a date & time'),
    status: z.string().min(1, 'Select a status'),
    purpose: z.string().max(255).optional().or(z.literal('')),
    court_room: z.string().max(120).optional().or(z.literal('')),
    judge_name: z.string().max(255).optional().or(z.literal('')),
    notes: z.string().max(10000).optional().or(z.literal('')),
    outcome: z.string().max(10000).optional().or(z.literal('')),
    next_hearing_at: z.string().optional().or(z.literal('')),
});

export type HearingFormValues = z.infer<typeof hearingSchema>;
