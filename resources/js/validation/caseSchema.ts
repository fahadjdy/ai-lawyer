import { z } from 'zod';

/**
 * Centralized, reusable case validation schema. Mirrors the backend Form
 * Request rules so the user gets instant client-side feedback while the server
 * remains the source of truth.
 */
export const caseSchema = z.object({
    title: z.string().min(3, 'Title must be at least 3 characters').max(255),
    case_number: z.string().max(100).optional().or(z.literal('')),
    client_id: z.coerce.number().int().positive().nullable().optional(),
    case_type: z.string().min(1, 'Select a case type'),
    status: z.string().min(1, 'Select a status'),
    priority: z.string().min(1, 'Select a priority'),
    description: z.string().max(10000).optional().or(z.literal('')),
    court_name: z.string().max(255).optional().or(z.literal('')),
    court_type: z.string().max(120).optional().or(z.literal('')),
    jurisdiction: z.string().max(120).optional().or(z.literal('')),
    judge_name: z.string().max(255).optional().or(z.literal('')),
    opposing_party: z.string().max(255).optional().or(z.literal('')),
    opposing_counsel: z.string().max(255).optional().or(z.literal('')),
    filing_date: z.string().optional().or(z.literal('')),
    next_hearing_at: z.string().optional().or(z.literal('')),
    lead_lawyer_id: z.coerce.number().int().positive().nullable().optional(),
});

export type CaseFormValues = z.infer<typeof caseSchema>;
