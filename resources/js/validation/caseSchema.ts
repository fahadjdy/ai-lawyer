import { z } from 'zod';

/**
 * Optional free-text field: accepts a string (including ''), null or undefined.
 * The edit form is populated straight from the database, where unset columns
 * come back as `null` — so the schema MUST tolerate null. With a plain
 * `.optional()` (which only allows `undefined`) a single null field makes the
 * whole form fail validation, and vee-validate's handleSubmit then silently
 * refuses to submit — i.e. the Save button appears to do nothing on edit.
 */
const optionalText = (max: number) => z.string().max(max).nullish();

/**
 * Centralized, reusable case validation schema. Mirrors the backend Form
 * Request rules so the user gets instant client-side feedback while the server
 * remains the source of truth.
 */
export const caseSchema = z.object({
    title: z.string().min(3, 'Title must be at least 3 characters').max(255),
    case_number: optionalText(100),
    client_id: z.coerce.number().int().positive().nullable().optional(),
    case_type: z.string().min(1, 'Select a case type'),
    status: z.string().min(1, 'Select a status'),
    priority: z.string().min(1, 'Select a priority'),
    favorability: z.coerce.number().int().min(0).max(100).nullable().optional(),
    description: optionalText(10000),
    court_name: optionalText(255),
    court_type: optionalText(120),
    jurisdiction: optionalText(120),
    judge_name: optionalText(255),
    opposing_party: optionalText(255),
    opposing_counsel: optionalText(255),
    filing_date: z.string().nullish(),
    next_hearing_at: z.string().nullish(),
    lead_lawyer_id: z.coerce.number().int().positive().nullable().optional(),
});

export type CaseFormValues = z.infer<typeof caseSchema>;
