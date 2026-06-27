<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToastStore } from '@/stores/toasts';
import { clientSchema, type ClientFormValues } from '@/validation/clientSchema';
import { Link, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { ref } from 'vue';
import type { EnumOption } from '@/types';

const props = defineProps<{
    options: { types: EnumOption[] };
    initial?: Partial<ClientFormValues>;
    submitUrl: string;
    method?: 'post' | 'put';
    submitLabel?: string;
}>();

const toasts = useToastStore();
const processing = ref(false);

const { defineField, handleSubmit, errors, setErrors } = useForm<ClientFormValues>({
    validationSchema: toTypedSchema(clientSchema),
    initialValues: {
        type: 'individual',
        name: '',
        company: '',
        email: '',
        phone: '',
        alternate_phone: '',
        address: '',
        city: '',
        state: '',
        country: 'India',
        postal_code: '',
        pan: '',
        gstin: '',
        notes: '',
        ...props.initial,
    },
});

const [type] = defineField('type');
const [name] = defineField('name');
const [company] = defineField('company');
const [email] = defineField('email');
const [phone] = defineField('phone');
const [alternatePhone] = defineField('alternate_phone');
const [address] = defineField('address');
const [city] = defineField('city');
const [state] = defineField('state');
const [country] = defineField('country');
const [postalCode] = defineField('postal_code');
const [pan] = defineField('pan');
const [gstin] = defineField('gstin');
const [notes] = defineField('notes');

const submit = handleSubmit((values) => {
    processing.value = true;
    const verb = props.method ?? 'post';
    router[verb](props.submitUrl, values as Record<string, unknown>, {
        onError: (serverErrors) => {
            setErrors(serverErrors as Record<string, string>);
            toasts.error('Please correct the highlighted fields.');
        },
        onFinish: () => (processing.value = false),
    });
});

// ---- Possible-duplicate detection (create only) ----
interface DupMatch {
    id: string;
    name: string;
    company: string | null;
    email: string | null;
    phone: string | null;
}
const isCreate = (props.method ?? 'post') === 'post';
const duplicates = ref<DupMatch[]>([]);
let dupTimer: ReturnType<typeof setTimeout> | null = null;

function checkDuplicates() {
    if (!isCreate) return;
    if (dupTimer) clearTimeout(dupTimer);
    dupTimer = setTimeout(async () => {
        const params = new URLSearchParams();
        if (name.value) params.set('name', String(name.value));
        if (email.value) params.set('email', String(email.value));
        if (phone.value) params.set('phone', String(phone.value));
        if ([...params].length === 0) {
            duplicates.value = [];
            return;
        }
        try {
            const res = await fetch(`/clients/duplicates?${params.toString()}`, { headers: { Accept: 'application/json' } });
            if (res.ok) duplicates.value = (await res.json()).matches ?? [];
        } catch {
            /* non-blocking */
        }
    }, 400);
}

const inputClass =
    'h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400';
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div v-if="duplicates.length" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            <p class="font-medium">Possible duplicate{{ duplicates.length > 1 ? 's' : '' }} already in your records:</p>
            <ul class="mt-1 space-y-0.5">
                <li v-for="d in duplicates" :key="d.id">
                    <Link :href="`/clients/${d.id}`" target="_blank" class="font-medium underline hover:no-underline">{{ d.name }}</Link>
                    <span class="text-amber-600">{{ d.company ? ' · ' + d.company : '' }}{{ d.email ? ' · ' + d.email : '' }}{{ d.phone ? ' · ' + d.phone : '' }}</span>
                </li>
            </ul>
            <p class="mt-1 text-xs text-amber-700">You can still create this client if it's a different person.</p>
        </div>

        <!-- Identity -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">Client details</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <Label for="type">Type</Label>
                    <select id="type" v-model="type" :class="inputClass">
                        <option v-for="t in options.types" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                    <InputError :message="errors.type" />
                </div>
                <div>
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="name" placeholder="e.g. Rajesh Sharma" @blur="checkDuplicates" />
                    <InputError :message="errors.name" />
                </div>
                <div class="md:col-span-2">
                    <Label for="company">Company / Organization</Label>
                    <Input id="company" v-model="company" />
                    <InputError :message="errors.company" />
                </div>
            </div>
        </section>

        <!-- Contact -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">Contact</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="email" type="email" @blur="checkDuplicates" />
                    <InputError :message="errors.email" />
                </div>
                <div>
                    <Label for="phone">Phone</Label>
                    <Input id="phone" v-model="phone" @blur="checkDuplicates" />
                    <InputError :message="errors.phone" />
                </div>
                <div>
                    <Label for="alternate_phone">Alternate phone</Label>
                    <Input id="alternate_phone" v-model="alternatePhone" />
                    <InputError :message="errors.alternate_phone" />
                </div>
                <div class="md:col-span-2">
                    <Label for="address">Address</Label>
                    <textarea id="address" v-model="address" rows="2" :class="inputClass" class="!h-auto py-2" />
                    <InputError :message="errors.address" />
                </div>
                <div><Label for="city">City</Label><Input id="city" v-model="city" /></div>
                <div><Label for="state">State</Label><Input id="state" v-model="state" /></div>
                <div><Label for="country">Country</Label><Input id="country" v-model="country" /></div>
                <div><Label for="postal_code">Postal code</Label><Input id="postal_code" v-model="postalCode" /></div>
            </div>
        </section>

        <!-- Statutory & notes -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">Statutory &amp; notes</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div><Label for="pan">PAN</Label><Input id="pan" v-model="pan" /></div>
                <div><Label for="gstin">GSTIN</Label><Input id="gstin" v-model="gstin" /></div>
                <div class="md:col-span-2">
                    <Label for="notes">Notes</Label>
                    <textarea id="notes" v-model="notes" rows="3" :class="inputClass" class="!h-auto py-2" />
                    <InputError :message="errors.notes" />
                </div>
            </div>
        </section>

        <div class="flex items-center justify-end gap-3">
            <Button variant="outline" as-child type="button"><Link href="/clients">Cancel</Link></Button>
            <Button type="submit" :disabled="processing">{{ submitLabel ?? 'Save client' }}</Button>
        </div>
    </form>
</template>
