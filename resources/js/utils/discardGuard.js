// "Discard your changes?" for a form in a popup.

import { inject, watch } from 'vue';

export function useDiscardGuard(isOpen, form, close) {
  const confirmDiscard = inject('confirmDiscard', null);
  let snapshot = null;

  watch(isOpen, (open) => {
    snapshot = null;
    if (open) setTimeout(() => { snapshot = JSON.stringify(form.value); }, 400);
  });

  return async function cancel() {
    const dirty = snapshot !== null && JSON.stringify(form.value) !== snapshot;
    if (dirty && confirmDiscard && !(await confirmDiscard())) return;
    close();
  };
}
