import { onMounted, onBeforeUnmount } from 'vue';

/**
 * Quietly re-runs fn() on an interval so lists stay current without the user
 * reloading the page (e.g. a new referral appears in the queue on its own).
 * It only fires while the browser tab is visible, and also runs once as soon
 * as the tab is brought back to the foreground. Cleans itself up on unmount.
 *
 * Pass a fetch function that refreshes in place without flashing a spinner
 * (typically your loader called with a "silent" flag).
 */
export function useAutoRefresh(fn, intervalMs = 25000) {
  let timer = null;
  const tick = () => {
    if (document.visibilityState === 'visible') {
      try { fn(); } catch (e) { /* ignore a single failed refresh */ }
    }
  };
  const onVisible = () => { if (document.visibilityState === 'visible') tick(); };

  onMounted(() => {
    timer = setInterval(tick, intervalMs);
    document.addEventListener('visibilitychange', onVisible);
  });
  onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisible);
  });

  return () => clearInterval(timer);
}
