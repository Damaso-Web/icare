import { defineStore } from 'pinia';

export const usePageSubtitleStore = defineStore('pageSubtitle', {
    state: () => ({ subtitle: '' }),
    actions: {
        set(value) { this.subtitle = value || ''; },
        clear() { this.subtitle = ''; },
    },
});