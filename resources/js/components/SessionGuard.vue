<!--
  Two things every signed-in page needs:

  1. "Are you sure you want to log out?" before logging out (askLogout()).
  2. Logging out by itself after a period with no activity, so an account
     left open on a shared computer does not stay open. A warning appears
     WARN_MINUTES before that, with a "Stay Logged In" button.

  The page that uses it does the actual logging out, on the `logout` event.
-->
<template>
  <div>
    <!-- Log out? -->
    <div v-if="asking" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:300;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="asking = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:380px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:18px 22px;border-bottom:1px solid var(--cloud);font-size:15px;font-weight:600;color:var(--ink)">Log out?</div>
        <div style="padding:20px 22px;display:flex;flex-direction:column;gap:16px">
          <div style="font-size:13px;color:var(--ink);line-height:1.55">Are you sure you want to log out?</div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" style="flex:1;justify-content:center" @click="confirmLogout">Log Out</button>
            <button class="ibtn ibtn-o" style="flex:1;justify-content:center" @click="asking = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- About to be logged out for inactivity -->
    <div v-if="warning" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:310;display:flex;align-items:center;justify-content:center;padding:20px">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:18px 22px;border-bottom:1px solid var(--cloud);font-size:15px;font-weight:600;color:var(--ink)">Are you still there?</div>
        <div style="padding:20px 22px;display:flex;flex-direction:column;gap:16px">
          <div style="font-size:13px;color:var(--ink);line-height:1.55">
            You have not used iCARE for a while. For your security you will be logged out in
            <strong>{{ countdown }}</strong>.
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" style="flex:1;justify-content:center" @click="stay">Stay Logged In</button>
            <button class="ibtn ibtn-o" style="flex:1;justify-content:center" @click="confirmLogout">Log Out Now</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { clearConsent } from '../utils/consentSkip';

const emit = defineEmits(['logout']);

// Logged out after IDLE_MINUTES without activity; warned WARN_MINUTES before.
const IDLE_MINUTES = 30;
const WARN_MINUTES = 10;
// Shared by every open tab, so being active in one keeps the others signed in.
const STAMP_KEY = 'icare_last_activity';

const asking  = ref(false);
const warning = ref(false);
const secondsLeft = ref(0);

const countdown = computed(() => {
  const m = Math.floor(secondsLeft.value / 60), s = secondsLeft.value % 60;
  return m ? `${m} min ${String(s).padStart(2, '0')} sec` : `${s} seconds`;
});

function lastActivity() {
  try { return Number(localStorage.getItem(STAMP_KEY)) || Date.now(); } catch (e) { return Date.now(); }
}
function touch() {
  try { localStorage.setItem(STAMP_KEY, String(Date.now())); } catch (e) { /* this tab only */ }
}

// Moving the mouse or typing counts as activity - but not while the warning
// is up: from then on only the "Stay Logged In" button does.
let lastTouch = 0;
function onActivity() {
  if (warning.value) return;
  const now = Date.now();
  if (now - lastTouch < 5000) return;   // no need to write on every mouse move
  lastTouch = now;
  touch();
}

function tick() {
  const idleSeconds = Math.floor((Date.now() - lastActivity()) / 1000);
  const limit = IDLE_MINUTES * 60;
  if (idleSeconds >= limit) {
    warning.value = false;
    clearConsent();
    emit('logout', { reason: 'idle' });
    return;
  }
  warning.value = idleSeconds >= limit - WARN_MINUTES * 60;
  secondsLeft.value = limit - idleSeconds;
}

function stay() {
  touch();
  warning.value = false;
}

function confirmLogout() {
  asking.value = false;
  warning.value = false;
  clearConsent();
  emit('logout', { reason: 'user' });
}

function askLogout() {
  asking.value = true;
}
defineExpose({ askLogout });

const EVENTS = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'];
let timer = null;
onMounted(() => {
  touch();
  EVENTS.forEach(ev => window.addEventListener(ev, onActivity, { passive: true, capture: true }));
  timer = setInterval(tick, 1000);
});
onBeforeUnmount(() => {
  EVENTS.forEach(ev => window.removeEventListener(ev, onActivity, { capture: true }));
  clearInterval(timer);
});
</script>