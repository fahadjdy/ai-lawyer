<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

/**
 * Lightweight confirmation modal reused for destructive actions across modules.
 * Controlled via `open`; emits `confirm` when the user proceeds.
 */
withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        description?: string;
        confirmLabel?: string;
        processing?: boolean;
    }>(),
    {
        title: 'Are you sure?',
        description: 'This action cannot be undone.',
        confirmLabel: 'Delete',
        processing: false,
    },
);

const emit = defineEmits<{ (e: 'update:open', value: boolean): void; (e: 'confirm'): void }>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <div class="flex items-center justify-end gap-3 pt-2">
                <Button variant="outline" type="button" @click="emit('update:open', false)">Cancel</Button>
                <Button variant="destructive" type="button" :disabled="processing" @click="emit('confirm')">
                    {{ confirmLabel }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
