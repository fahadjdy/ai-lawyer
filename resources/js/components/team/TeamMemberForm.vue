<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { EnumOption } from '@/types';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface Member {
    id: string;
    name: string;
    email: string;
    designation: string | null;
    phone: string | null;
    role: string | null;
    is_active: boolean;
}

const props = defineProps<{
    open: boolean;
    roles: EnumOption[];
    member?: Member | null;
}>();

const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const name = ref('');
const email = ref('');
const designation = ref('');
const phone = ref('');
const role = ref<string | null>('associate');
const password = ref('');
const isActive = ref(true);
const errors = ref<Record<string, string>>({});
const processing = ref(false);

const roleOptions = () => props.roles.map((r) => ({ value: r.value, label: r.label }));

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;
        errors.value = {};
        password.value = '';
        const m = props.member;
        name.value = m?.name ?? '';
        email.value = m?.email ?? '';
        designation.value = m?.designation ?? '';
        phone.value = m?.phone ?? '';
        role.value = m?.role ?? 'associate';
        isActive.value = m?.is_active ?? true;
    },
    { immediate: true },
);

const close = () => emit('update:open', false);

function submit() {
    processing.value = true;
    errors.value = {};
    const opts = {
        preserveScroll: true,
        onError: (e: Record<string, string>) => (errors.value = e),
        onSuccess: close,
        onFinish: () => (processing.value = false),
    };
    if (props.member) {
        router.put(
            `/team/${props.member.id}`,
            { name: name.value, designation: designation.value, phone: phone.value, role: role.value, is_active: isActive.value },
            opts,
        );
    } else {
        router.post(
            '/team',
            {
                name: name.value,
                email: email.value,
                designation: designation.value,
                phone: phone.value,
                role: role.value,
                password: password.value,
                is_active: isActive.value,
            },
            opts,
        );
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ member ? 'Edit member' : 'Add team member' }}</DialogTitle>
                <DialogDescription>{{ member ? 'Update this member\'s role and details.' : 'Create a login for a member of your firm.' }}</DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <Label for="m_name">Name</Label>
                        <Input id="m_name" v-model="name" placeholder="e.g. Priya Mehta" />
                        <InputError :message="errors.name" />
                    </div>
                    <div>
                        <Label for="m_email">Email</Label>
                        <Input id="m_email" v-model="email" type="email" :disabled="!!member" :class="member ? 'opacity-60' : ''" />
                        <InputError :message="errors.email" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <Label for="m_role">Role</Label>
                        <SearchableSelect id="m_role" v-model="role" :options="roleOptions()" placeholder="Select a role" />
                        <InputError :message="errors.role" />
                    </div>
                    <div>
                        <Label for="m_designation">Designation</Label>
                        <Input id="m_designation" v-model="designation" placeholder="e.g. Senior Associate" />
                        <InputError :message="errors.designation" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <Label for="m_phone">Phone</Label>
                        <Input id="m_phone" v-model="phone" />
                        <InputError :message="errors.phone" />
                    </div>
                    <div v-if="!member">
                        <Label for="m_password">Initial password</Label>
                        <Input id="m_password" v-model="password" type="text" placeholder="Min. 8 characters" />
                        <InputError :message="errors.password" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="isActive" type="checkbox" class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-400" />
                    Active (can sign in)
                </label>

                <p v-if="!member" class="rounded-lg bg-slate-50 p-2.5 text-xs text-slate-500">
                    Share the email and initial password with the member — they can change it after signing in.
                </p>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Button variant="outline" type="button" @click="close">Cancel</Button>
                    <Button type="submit" :disabled="processing">{{ member ? 'Save changes' : 'Add member' }}</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
