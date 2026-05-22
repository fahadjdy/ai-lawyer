import { defineStore } from 'pinia';

/**
 * Global UI state (command palette visibility, etc.). Demonstrates the Pinia
 * store pattern for cross-component client state.
 */
export const useUiStore = defineStore('ui', {
    state: () => ({
        commandPaletteOpen: false,
    }),
    actions: {
        toggleCommandPalette(open?: boolean) {
            this.commandPaletteOpen = open ?? !this.commandPaletteOpen;
        },
    },
});
