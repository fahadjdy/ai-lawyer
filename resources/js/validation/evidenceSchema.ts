import { z } from 'zod';

/**
 * Evidence form validation. Mirrors Store/UpdateEvidenceRequest. Evidence always
 * belongs to a case; an optional document may back it for chain-of-custody.
 */
export const evidenceSchema = z.object({
    case_id: z.coerce.number({ invalid_type_error: 'Select a case' }).int().positive('Select a case'),
    document_id: z.coerce.number().int().positive().nullable().optional(),
    reference_number: z.string().max(100).optional().or(z.literal('')),
    title: z.string().min(1, 'Title is required').max(255),
    description: z.string().max(10000).optional().or(z.literal('')),
    type: z.string().min(1, 'Select a type'),
    status: z.string().min(1, 'Select a status'),
    collected_at: z.string().optional().or(z.literal('')),
    collected_by: z.string().max(255).optional().or(z.literal('')),
});

export type EvidenceFormValues = z.infer<typeof evidenceSchema>;
