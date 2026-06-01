<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToastStore } from '@/stores/toasts';
import { taskSchema, type TaskFormValues } from '@/validation/taskSchema';
import { router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { ref, watch } from 'vue';
import type { EnumOption } from '@/types';

interface TaskRow {
    id: string;
    title: string;
    description: string | null;
    case_id: number | null;
    assigned_to: number | null;
    status: EnumOption | null;
    priority: EnumOption | null;
    due_at: string | null;
}

const props = defineProps<{
    open: boolean;
    task?: TaskRow | null;
    options: {
        statuses: EnumOption[];
        priorities: EnumOption[];
        cases: { id: number; name: string }[];
        users: { id: number; name: string }[];
    };
    defaultStatus?: string;
}>();

const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const toasts = useToastStore();
const processing = ref(false);

const { defineField, handleSubmit, errors, setErrors, resetForm } = useForm<TaskFormValues>({
    validationSchema: toTypedSchema(taskSchema),
    initialValues: { title: '', status: 'todo', priority: 'medium' },
});

const [title] = defineField('title');
const [description] = defineField('description');
const [caseId] = defineField('case_id');
const [status] = defineField('status');
const [priority] = defineField('priority');
const [dueAt] = defineField('due_at');
const [assignedTo] = defineField('assigned_to');

// Re-seed the form each time the modal opens — for a new task or an edit.
watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;
        const t = props.task;
        resetForm({
            values: {
                title: t?.title ?? '',
                description: t?.description ?? '',
                case_id: t?.case_id ?? null,
                status: t?.status?.value ?? props.defaultStatus ?? 'todo',
                priority: t?.priority?.value ?? 'medium',
                // ISO → datetime-local (`YYYY-MM-DDTHH:mm`).
                due_at: t?.due_at ? t.due_at.slice(0, 16) : '',
                assigned_to: t?.assigned_to ?? null,
            },
        });
    },
    { immediate: true },
);

const close = () => emit('update:open', false);

const submit = handleSubmit((values) => {
    processing.value = true;
    const onError = (e: Record<string, string>) => {
        setErrors(e);
        toasts.error('Please correct the highlighted fields.');
    };
    const opts = { preserveScroll: true, onError, onSuccess: close, onFinish: () => (processing.value = false) };

    if (props.task) {
        router.put(`/tasks/${props.task.id}`, values as Record<string, unknown>, opts);
    } else {
        router.post('/tasks', values as Record<string, unknown>, opts);
    }
});

const inputClass =
    'h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400';
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ task ? 'Edit task' : 'New task' }}</DialogTitle>
                <DialogDescription>{{ task ? 'Update the task details.' : 'Add a task to your board.' }}</DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <Label for="t_title">Title</Label>
                    <Input id="t_title" v-model="title" placeholder="e.g. Draft reply affidavit" />
                    <InputError :message="errors.title" />
                </div>

                <div>
                    <Label for="t_desc">Description</Label>
                    <textarea id="t_desc" v-model="description" rows="3" :class="inputClass" class="!h-auto py-2" />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <Label for="t_status">Status</Label>
                        <select id="t_status" v-model="status" :class="inputClass">
                            <option v-for="s in options.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>
                    <div>
                        <Label for="t_priority">Priority</Label>
                        <select id="t_priority" v-model="priority" :class="inputClass">
                            <option v-for="p in options.priorities" :key="p.value" :value="p.value">{{ p.label }}</option>
                        </select>
                        <InputError :message="errors.priority" />
                    </div>
                </div>

                <div>
                    <Label for="t_case">Case <span class="text-slate-400">(optional)</span></Label>
                    <select id="t_case" v-model="caseId" :class="inputClass">
                        <option :value="null">— General (no case) —</option>
                        <option v-for="c in options.cases" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <InputError :message="errors.case_id" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <Label for="t_assignee">Assignee</Label>
                        <select id="t_assignee" v-model="assignedTo" :class="inputClass">
                            <option :value="null">— Unassigned —</option>
                            <option v-for="u in options.users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                        <InputError :message="errors.assigned_to" />
                    </div>
                    <div>
                        <Label for="t_due">Due date</Label>
                        <input id="t_due" v-model="dueAt" type="datetime-local" :class="inputClass" />
                        <InputError :message="errors.due_at" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Button variant="outline" type="button" @click="close">Cancel</Button>
                    <Button type="submit" :disabled="processing">{{ task ? 'Save changes' : 'Create task' }}</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
