// "Discard your changes?" for a form in a popup.
//
//   const cancelEdit = useDiscardGuard(showEditModal, editForm, () => { showEditModal.value = false; });
//
// cancelEdit() closes the popup straight away when nothing was changed, and
// asks first when something was. What counts as "unchanged" is the form as it
// stood shortly after the popup opened (forms are filled in just after they
// open, some of them a moment later).
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
