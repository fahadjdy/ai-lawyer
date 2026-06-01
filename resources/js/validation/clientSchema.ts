import { z } from 'zod';

/**
 * Client form validation. Mirrors Store/UpdateClientRequest so the user gets
 * instant feedback; the server remains the source of truth.
 */
export const clientSchema = z.object({
    type: z.string().min(1, 'Select a client type'),
    name: z.string().min(1, 'Name is required').max(255),
    company: z.string().max(255).optional().or(z.literal('')),
    email: z.string().email('Enter a valid email').max(255).optional().or(z.literal('')),
    phone: z.string().max(30).optional().or(z.literal('')),
    alternate_phone: z.string().max(30).optional().or(z.literal('')),
    address: z.string().max(500).optional().or(z.literal('')),
    city: z.string().max(120).optional().or(z.literal('')),
    state: z.string().max(120).optional().or(z.literal('')),
    country: z.string().max(120).optional().or(z.literal('')),
    postal_code: z.string().max(20).optional().or(z.literal('')),
    pan: z.string().max(20).optional().or(z.literal('')),
    gstin: z.string().max(30).optional().or(z.literal('')),
    notes: z.string().max(10000).optional().or(z.literal('')),
});

export type ClientFormValues = z.infer<typeof clientSchema>;
