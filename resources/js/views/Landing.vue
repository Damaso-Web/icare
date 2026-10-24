<template>
  <div class="lp">
    <!-- Top bar -->
    <header class="lp-nav" :class="{ 'is-scrolled': scrolled }">
      <div class="lp-wrap lp-nav-in">
        <a class="lp-brand" href="#top" @click.prevent="go('top')">
          <img :src="'/bsu-logo.jpg'" alt="Benguet State University" width="288" height="284" />
          <img :src="'/oss-logo.jpg'" alt="Office of Student Services" width="250" height="333" />
          <span class="lp-brand-text">
            <b>iCARE</b>
            <small>An Integrated Case Management System for Benguet State University &ndash; Office of Student Services</small>
          </span>
        </a>
        <nav class="lp-links" aria-label="Sections">
          <a href="#services" @click.prevent="go('services')">Services</a>
          <a href="#how" @click.prevent="go('how')">How it works</a>
          <a href="#contact" @click.prevent="go('contact')">Contact</a>
        </nav>
        <router-link :to="{ name: signedIn ? 'dashboard' : 'login-choice' }" class="lp-btn lp-btn-solid lp-nav-cta">
          {{ signedIn ? 'Go to dashboard' : 'Log in' }}
        </router-link>
        <button class="lp-burger" :aria-expanded="menuOpen" aria-label="Menu" @click="menuOpen = !menuOpen">
          <svg v-if="!menuOpen" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
          <svg v-else viewBox="0 0 24 24"><line x1="6" y1="6" x2="18" y2="18"/><line x1="6" y1="18" x2="18" y2="6"/></svg>
        </button>
      </div>
      <!-- Mobile section menu -->
      <div class="lp-mobile" :class="{ 'is-open': menuOpen }">
        <a href="#services" @click.prevent="goM('services')">Services</a>
        <a href="#how" @click.prevent="goM('how')">How it works</a>
        <a href="#contact" @click.prevent="goM('contact')">Contact</a>
        <router-link :to="{ name: signedIn ? 'dashboard' : 'login-choice' }" class="lp-btn lp-btn-solid lp-mobile-cta" @click="menuOpen = false">
          {{ signedIn ? 'Go to dashboard' : 'Log in' }}
        </router-link>
      </div>
    </header>

    <main>
    <!-- Hero -->
    <section id="top" class="lp-hero">
      <div class="lp-wrap lp-hero-in">
        <div class="lp-hero-text">
          <!-- B578: abbreviation added after the full name so it's introduced
               right where the unit first appears on the page (not in the
               nav bar or footer, per the ticket). -->
          <div class="lp-eyebrow">Benguet State University · Office of Student Services (OSS)</div>
          <h1>Student care, <em>in one connected place.</em></h1>
          <p>
            iCARE brings referrals, appointments and case records of the Office of Student Services
            together, so every student request is seen, scheduled and followed through.
          </p>
          <div class="lp-cta">
            <router-link :to="{ name: signedIn ? 'dashboard' : 'login-choice' }" class="lp-btn lp-btn-light">
              {{ signedIn ? 'Go to dashboard' : 'Log in to iCARE' }}
            </router-link>
            <a href="#services" class="lp-btn lp-btn-ghost" @click.prevent="go('services')">See our services</a>
          </div>
        </div>

        <!-- A small picture of the flow, no made-up numbers -->
        <div class="lp-flow" aria-hidden="true">
          <div class="lp-flow-card">
            <span class="lp-dot lp-dot-a">
              <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </span>
            <div class="lp-flow-text"><strong>Referral sent</strong><small>Faculty, dean or the student</small></div>
          </div>
          <div class="lp-flow-line"></div>
          <div class="lp-flow-card">
            <span class="lp-dot lp-dot-b">
              <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </span>
            <div class="lp-flow-text"><strong>Appointment scheduled</strong><small>The student picks a date and time</small></div>
          </div>
          <div class="lp-flow-line"></div>
          <div class="lp-flow-card">
            <span class="lp-dot lp-dot-c">
              <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </span>
            <div class="lp-flow-text"><strong>Case followed through</strong><small>Sessions, notes and feedback</small></div>
          </div>
        </div>
      </div>
      <div class="lp-hero-wave" aria-hidden="true">
        <svg viewBox="0 0 1440 48" preserveAspectRatio="none"><path d="M0,24 C360,52 1080,-4 1440,24 L1440,48 L0,48 Z" fill="var(--snow)"/></svg>
      </div>
    </section>

    <!-- Services -->
    <section id="services" class="lp-sec">
      <div class="lp-wrap">
        <div class="lp-kicker reveal">What <span class="lp-keepcase">iCARE</span> supports</div>
        <h2 class="reveal">Three units, one system</h2>
        <div class="lp-grid3">
          <article class="lp-card reveal">
            <div class="lp-ico lp-ico-g">
              <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </div>
            <h3>Guidance and Counseling Unit (GCU)</h3>
            <p>Counseling and other student services, from the first referral to the final session notes.</p>
          </article>
          <article class="lp-card reveal">
            <div class="lp-ico lp-ico-b">
              <svg viewBox="0 0 24 24"><path d="M9 2v6l-6 10a2 2 0 0 0 1.7 3h14.6a2 2 0 0 0 1.7-3L15 8V2"/><path d="M9 2h6"/><path d="M7 15h10"/></svg>
            </div>
            <h3>Testing and Materials Development Unit (TMDU)</h3>
            <p>Psychological testing schedules and records, kept together with the student's referral.</p>
          </article>
          <article class="lp-card reveal">
            <div class="lp-ico lp-ico-a">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/></svg>
            </div>
            <h3>Student Discipline Unit (SDU)</h3>
            <p>Incident reports and complaints, filed and tracked with a clear status at every step.</p>
          </article>
        </div>
      </div>
    </section>

    <!-- How it works -->
    <section id="how" class="lp-sec lp-sec-tint">
      <div class="lp-wrap">
        <div class="lp-kicker reveal">How it works</div>
        <h2 class="reveal">From request to follow-up in three steps</h2>
        <ol class="lp-steps">
          <li class="reveal">
            <span class="lp-num">1</span>
            <h3>Sign in</h3>
            <p>Students sign in with their Student ID. BSU personnel sign in with their employee account.</p>
          </li>
          <li class="reveal">
            <span class="lp-num">2</span>
            <h3>Refer or request</h3>
            <p>Send a referral, file a report, or pick the schedule offered for your appointment.</p>
          </li>
          <li class="reveal">
            <span class="lp-num">3</span>
            <h3>Track progress</h3>
            <p>See the status of each referral and appointment, and get notified when something changes.</p>
          </li>
        </ol>

        <div class="lp-who">
          <div class="reveal"><strong>Students</strong><span>Refer yourself or a classmate, choose appointment times, follow your requests.</span></div>
          <div class="reveal"><strong>Faculty and deans</strong><span>Refer students and see what happened with your referrals.</span></div>
          <div class="reveal"><strong>OSS staff</strong><span>Refer students, and manage referrals, appointments, cases and records in one place.</span></div>
        </div>
      </div>
    </section>

    <!-- Contact -->
    <section id="contact" class="lp-sec">
      <div class="lp-wrap">
        <div class="lp-kicker reveal">Contact us</div>
        <h2 class="reveal">Office of Student Services (OSS)</h2>
        <div class="lp-grid2">
          <article v-for="c in contacts" :key="c.name" class="lp-contact reveal">
            <h3>{{ c.name }}</h3>
            <ul>
              <li v-if="c.email"><span>Email</span><a :href="'mailto:' + c.email">{{ c.email }}</a></li>
              <li v-if="c.phone"><span>{{ c.phoneLabel }}</span><a :href="'tel:' + c.phone.replace(/[^0-9+]/g, '')">{{ c.phone }}</a></li>
              <li v-if="c.facebook"><span>Facebook</span><a :href="c.facebook" target="_blank" rel="noopener noreferrer">{{ c.facebookLabel }}</a></li>
            </ul>
            <!-- B539: confirm this reflects the Office's actual hours/response time before publishing. -->
            <p class="lp-contact-hours">Office hours are Monday&ndash;Friday. Standard response time for email inquiries is within 2 business days.</p>
          </article>
        </div>
        <div class="lp-more reveal">
          <h3>More About the Office</h3>
          <p>
            Learn more about our programs and services on the
            <a href="https://bsu.edu.ph/office-of-student-services/" target="_blank" rel="noopener noreferrer">BSU Office of Student Services website</a>.
          </p>
        </div>
      </div>
    </section>
    </main>

    <!-- Footer (B540): branding / Quick Links / Legal, then a divider and the privacy + copyright lines. -->
    <footer class="lp-foot">
      <div class="lp-wrap lp-foot-in">
        <div class="lp-foot-col lp-foot-brand">
          <strong>iCARE</strong>
          <span>Benguet State University</span>
          <span>Office of Student Services</span>
          <span>Integrated Case Management System</span>
        </div>
        <div class="lp-foot-col">
          <h4>Quick Links</h4>
          <a href="#services" @click.prevent="go('services')">Services</a>
          <a href="#how" @click.prevent="go('how')">How it works</a>
          <a href="#contact" @click.prevent="go('contact')">Contacts</a>
          <button type="button" class="lp-linkbtn" @click="showFaq = true">FAQs</button>
        </div>
        <div class="lp-foot-col">
          <h4>Legal</h4>
          <button type="button" class="lp-linkbtn" @click="showPrivacy = true">Privacy Policy</button>
          <button type="button" class="lp-linkbtn" @click="showTerms = true">Terms of Use</button>
        </div>
      </div>
      <div class="lp-wrap">
        <div class="lp-foot-divider"></div>
        <div class="lp-foot-bottom">
          <p>Personal information is handled in compliance with the Data Privacy Act of 2012 (RA 10173).</p>
          <p>&copy; 2026 Benguet State University - Office of Student Services. All Rights Reserved.</p>
        </div>
      </div>
    </footer>

    <!-- What the Data Privacy Act means -->
    <div v-if="showPrivacy" class="lp-modal" @click.self="showPrivacy = false" @keydown.esc="showPrivacy = false">
      <div ref="dialog" class="lp-dialog" role="dialog" aria-modal="true" aria-labelledby="lp-priv-title" tabindex="-1">
        <!-- B537: a single close control (the "Close" button below) instead of two. -->
        <div class="lp-dialog-head">
          <h3 id="lp-priv-title">Data Privacy Act of 2012 (RA 10173)</h3>
        </div>
        <div class="lp-dialog-body">
          <h4>Purpose</h4>
          <p>
            The law protects the fundamental human right to privacy of communication while ensuring the free flow of information.
            It covers every organization in the Philippines, public or private, that collects or handles personal information.
          </p>
          <h4>How your information is protected</h4>
          <p>
            Your personal information may only be collected for a clear and lawful purpose, with your knowledge and consent, and used only for that purpose.
            It must be kept accurate, stored securely and kept no longer than needed. At the Office of Student Services, what you share is seen only by the personnel handling your request.
          </p>
          <h4>Your rights as a data subject</h4>
          <ul>
            <li>To be informed how your data is collected and used</li>
            <li>To access the personal information held about you</li>
            <li>To object to the processing of your data</li>
            <li>To correct inaccurate or outdated information</li>
            <li>To have your data erased or blocked when it is no longer needed or was unlawfully obtained</li>
            <li>To data portability, a copy of your data in a usable format</li>
            <li>To claim compensation for damages caused by misuse of your data</li>
            <li>To file a complaint with the National Privacy Commission</li>
          </ul>
          <h4>Read the law</h4>
          <p class="lp-dialog-links">
            <a href="https://privacy.gov.ph/data-privacy-act/" target="_blank" rel="noopener noreferrer">National Privacy Commission: Data Privacy Act</a>
            <a href="https://www.officialgazette.gov.ph/2012/08/15/republic-act-no-10173/" target="_blank" rel="noopener noreferrer">Official Gazette: full text of RA 10173</a>
          </p>
        </div>
        <div class="lp-dialog-foot">
          <button type="button" class="lp-btn lp-btn-solid" @click="showPrivacy = false">Close</button>
        </div>
      </div>
    </div>

    <!-- B541: FAQs, opened from the footer instead of being a landing-page section. -->
    <div v-if="showFaq" class="lp-modal" @click.self="showFaq = false">
      <div ref="faqDialog" class="lp-dialog" role="dialog" aria-modal="true" aria-labelledby="lp-faq-title" tabindex="-1">
        <div class="lp-dialog-head">
          <h3 id="lp-faq-title">Frequently Asked Questions</h3>
        </div>
        <div class="lp-dialog-body">
          <div v-for="(f, i) in faqs" :key="i" class="lp-faq-item">
            <h4>{{ f.q }}</h4>
            <p>{{ f.a }}</p>
          </div>
        </div>
        <div class="lp-dialog-foot">
          <button type="button" class="lp-btn lp-btn-solid" @click="showFaq = false">Close</button>
        </div>
      </div>
    </div>

    <!-- B540 (Legal): a short Terms of Use, kept as a modal to match Privacy and FAQs. -->
    <div v-if="showTerms" class="lp-modal" @click.self="showTerms = false">
      <div ref="termsDialog" class="lp-dialog" role="dialog" aria-modal="true" aria-labelledby="lp-terms-title" tabindex="-1">
        <div class="lp-dialog-head">
          <h3 id="lp-terms-title">Terms of Use</h3>
        </div>
        <div class="lp-dialog-body">
          <h4>Using iCARE</h4>
          <p>iCARE is for Benguet State University students, faculty, deans and Office of Student Services (OSS) staff, to send and track referrals, appointments and case records with the OSS.</p>
          <h4>Your account</h4>
          <p>Sign in with your own Student ID or employee account. You are responsible for what happens under your account, so keep your password to yourself and sign out on shared devices.</p>
          <h4>Acceptable use</h4>
          <p>Use iCARE only for its intended purpose - your own referrals, appointments and case records, or those you are authorized to handle. Do not attempt to access another person's records or disrupt the system.</p>
          <h4>Your information</h4>
          <p>How your personal information is collected and used is covered in our Privacy Policy, consistent with the Data Privacy Act of 2012 (RA 10173).</p>
          <h4>Changes</h4>
          <p>These terms may be updated from time to time as the system and the Office's policies evolve.</p>
        </div>
        <div class="lp-dialog-foot">
          <button type="button" class="lp-btn lp-btn-solid" @click="showTerms = false">Close</button>
        </div>
      </div>
    </div>

    <!-- B538: floating back-to-top button - hidden at the top, shown only once
         the hero has scrolled out of view. -->
    <button v-show="showBackToTop" type="button" class="lp-totop-float" aria-label="Back to top" title="Back to top" @click="toTop">
      <svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>
    </button>
  </div>
</template>

<script setup>
import { computed, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';

const signedIn = computed(() => !!localStorage.getItem('token'));

// The header gains a shadow once the page is scrolled past the top.
const scrolled = ref(false);
// B538: the floating back-to-top button only appears once the hero is fully
// scrolled past (its bottom edge above the viewport), not just "scrolled a bit".
const showBackToTop = ref(false);
const onScroll = () => {
  scrolled.value = window.scrollY > 8;
  const hero = document.getElementById('top');
  showBackToTop.value = hero ? hero.getBoundingClientRect().bottom <= 0 : window.scrollY > 400;
};
const toTop = () => window.scrollTo({ top: 0, behavior: 'smooth' });

// Mobile section menu (hamburger).
const menuOpen = ref(false);
const goM = (id) => { menuOpen.value = false; go(id); };
const onResize = () => { if (window.innerWidth > 860) menuOpen.value = false; };

// Gentle fade-up as each block scrolls into view (skipped for reduced motion).
let io = null;
onMounted(() => {
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onResize, { passive: true });

  const els = document.querySelectorAll('.lp .reveal');
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduce || !('IntersectionObserver' in window)) {
    els.forEach(el => el.classList.add('is-in'));
    return;
  }
  io = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
    });
  }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
  els.forEach(el => io.observe(el));
});

// Privacy, FAQs and Terms are all simple modals - closable by button, outside
// click (see @click.self on each) or Escape, with the body locked while any is open.
const showPrivacy = ref(false);
const showFaq = ref(false);
const showTerms = ref(false);
const dialog = ref(null);
const faqDialog = ref(null);
const termsDialog = ref(null);
const anyModalOpen = computed(() => showPrivacy.value || showFaq.value || showTerms.value);
const onKey = e => {
  if (e.key !== 'Escape') return;
  showPrivacy.value = false; showFaq.value = false; showTerms.value = false;
};
async function watchModal(open, dialogRef) {
  if (open) {
    window.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';
    await nextTick();
    dialogRef.value?.focus();
  } else if (!anyModalOpen.value) {
    window.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
  }
}
watch(showPrivacy, open => watchModal(open, dialog));
watch(showFaq, open => watchModal(open, faqDialog));
watch(showTerms, open => watchModal(open, termsDialog));

// B541: verify the response time, login steps and emergency number with OSS before publishing.
const faqs = [
  { q: 'What is the response time for email inquiries?', a: 'Standard response time for email inquiries is within 2 business days.' },
  { q: 'Who can use iCARE?', a: 'Students, faculty and deans, and Office of Student Services (OSS) staff can all use iCARE, each with access suited to their role.' },
  { q: 'How do I request an appointment or referral?', a: "Students sign in with their Student ID and can refer themselves or pick an offered appointment schedule. Faculty and deans sign in with their employee account to refer a student; the OSS unit then schedules it and follows up." },
  { q: 'Is my information kept confidential?', a: 'Yes. Personal information is handled in compliance with the Data Privacy Act of 2012 (RA 10173), and is seen only by the personnel handling your request.' },
  { q: 'Who do I contact in an emergency?', a: 'For urgent concerns, call the Guidance and Counseling Unit at 0907-905-0644.' },
];
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKey);
  window.removeEventListener('scroll', onScroll);
  window.removeEventListener('resize', onResize);
  io?.disconnect();
  document.body.style.overflow = '';
});

const contacts = [
  {
    name: "Director's Office",
    email: 'oss.director@bsu.edu.ph',
    phoneLabel: 'TeleFax', phone: '(074) 422-2043',
    facebook: 'https://www.facebook.com/BSU.OSS.LTB', facebookLabel: 'BSU OSS',
  },
  {
    name: 'Guidance and Counseling Unit',
    email: 'oss.cgu@bsu.edu.ph',
    phoneLabel: 'Mobile', phone: '0907-905-0644',
    facebook: 'https://www.facebook.com/bsuossgcu', facebookLabel: 'BSU OSS GCU',
  },
  {
    name: 'Testing and Materials Development Unit',
    email: 'oss.tmdu@bsu.edu.ph',
  },
  {
    name: 'Student Discipline Unit',
    email: 'oss.sdu@bsu.edu.ph',
  },
];

function go(id) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<style scoped>
/* overflow-x: clip (not hidden) stops sideways scroll without turning .lp into a
   scroll container, which would break the sticky header. */
.lp { min-height: 100vh; background: var(--snow); color: var(--ink); font-family: var(--font); font-size: 15px; line-height: 1.6; overflow-x: clip; }
.lp * { box-sizing: border-box; }
.lp-wrap { width: 100%; max-width: 1120px; margin: 0 auto; padding: 0 20px; }
.lp a { color: inherit; }
.lp h1, .lp h2, .lp h3, .lp p { margin: 0; }

/* Nav */
/* B573: position:sticky + backdrop-filter is a known Chrome-for-Android
   rendering bug - the element's compositing layer can end up tied to the
   page's own scroll instead of the viewport, so it scrolls away instead of
   sticking. Forcing its own compositing layer (will-change/transform)
   works around it without changing how it looks or behaves anywhere else. */
.lp-nav { position: sticky; top: 0; z-index: 20; background: rgba(255,255,255,.92); -webkit-backdrop-filter: saturate(1.4) blur(10px); backdrop-filter: saturate(1.4) blur(10px); border-bottom: 1px solid var(--cloud); transition: box-shadow .25s var(--ease), border-color .25s; will-change: transform; transform: translateZ(0); }
.lp-nav.is-scrolled { box-shadow: 0 6px 24px rgba(20,48,30,.08); border-bottom-color: transparent; }
.lp-hero, .lp-sec { scroll-margin-top: 72px; }
.lp-nav-in { display: flex; align-items: center; gap: 20px; min-height: 66px; padding-top: 8px; padding-bottom: 8px; }
.lp-brand { display: flex; align-items: center; gap: 8px; text-decoration: none; margin-right: auto; min-width: 0; }
.lp-brand img { flex-shrink: 0; width: 34px; height: 34px; border-radius: 50%; object-fit: cover; background: #fff; }
.lp-brand-text { display: flex; flex-direction: column; margin-left: 4px; min-width: 0; }
.lp-brand-text b { font-weight: 700; font-size: 18px; line-height: 1.15; letter-spacing: .3px; color: var(--forest); }
.lp-brand-text small { font-size: 11px; line-height: 1.3; color: var(--stone); max-width: 340px; }
.lp-links { display: flex; gap: 6px; }
.lp-links a { position: relative; text-decoration: none; font-size: 14px; font-weight: 500; color: var(--slate); padding: 7px 13px; border-radius: 999px; transition: background .2s ease, color .2s ease; }
.lp-links a::after { content: ''; position: absolute; left: 13px; right: 13px; bottom: 3px; height: 2px; border-radius: 2px; background: var(--moss); transform: scaleX(0); transform-origin: left; transition: transform .22s ease; }
.lp-links a:hover, .lp-links a:focus-visible { background: var(--mist); color: var(--forest); outline: none; }
.lp-links a:hover::after, .lp-links a:focus-visible::after { transform: scaleX(1); }

/* Hamburger + mobile menu (shown only on small screens) */
.lp-burger { display: none; width: 40px; height: 40px; flex-shrink: 0; align-items: center; justify-content: center; border: 1px solid var(--cloud); border-radius: 10px; background: #fff; color: var(--slate); cursor: pointer; transition: background .15s, color .15s, border-color .15s; }
.lp-burger:hover { background: var(--mist); color: var(--forest); border-color: var(--mint); }
.lp-burger svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; }
.lp-mobile { display: none; }

/* B538: floating back-to-top button, fixed to the viewport. */
.lp-totop-float { position: fixed; bottom: 32px; right: 32px; z-index: 40; display: inline-flex; align-items: center; justify-content: center; width: 46px; height: 46px; border-radius: 50%; border: none; background: var(--moss); color: #fff; box-shadow: 0 8px 22px rgba(46,109,68,.3); cursor: pointer; transition: transform .2s var(--ease), background .2s, opacity .2s; }
.lp-totop-float:hover { background: var(--pine); transform: translateY(-2px); }
.lp-totop-float svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }

/* Buttons */
.lp-btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px; border-radius: 999px; font-size: 14px; font-weight: 600; text-decoration: none; border: 1.5px solid transparent; cursor: pointer; transition: transform .15s var(--ease), background .15s, box-shadow .15s; }
.lp-btn:hover { transform: translateY(-1px); }
.lp-btn-solid { background: var(--moss); color: #fff !important; box-shadow: 0 4px 14px rgba(46,109,68,.22); }
.lp-btn-solid:hover { background: var(--pine); box-shadow: 0 8px 22px rgba(46,109,68,.28); }
.lp-btn-light { background: #fff; color: var(--forest) !important; box-shadow: 0 6px 20px rgba(0,0,0,.18); }
.lp-btn-ghost { border-color: rgba(255,255,255,.45); color: #fff !important; }
.lp-btn-ghost:hover { background: rgba(255,255,255,.1); }

/* Hero */
.lp-hero { background: var(--grad-forest); color: #fff; position: relative; overflow: hidden; }
.lp-hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse 60% 70% at 85% 20%, rgba(143,224,176,.18), transparent 60%), radial-gradient(ellipse 50% 60% at 0% 100%, rgba(46,148,88,.25), transparent 60%); pointer-events: none; }
.lp-hero-in { position: relative; display: grid; grid-template-columns: 1.15fr .85fr; gap: 48px; align-items: center; padding-top: 84px; padding-bottom: 92px; }
.lp-eyebrow { font-size: 12px; letter-spacing: 1.2px; text-transform: uppercase; color: var(--mint); margin-bottom: 18px; }
.lp-hero h1 { font-family: var(--serif); font-weight: 400; font-size: clamp(38px, 6vw, 62px); line-height: 1.05; }
.lp-hero h1 em { color: var(--gold); }
.lp-hero p { margin-top: 20px; max-width: 520px; font-size: 17px; color: rgba(255,255,255,.82); }
.lp-cta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 30px; }

.lp-flow { display: flex; flex-direction: column; align-items: stretch; }
.lp-flow-card { display: flex; align-items: center; gap: 14px; padding: 16px 18px; border-radius: var(--r-lg); background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.16); backdrop-filter: blur(6px); transition: background .2s var(--ease), transform .2s var(--ease); }
.lp-flow-card:hover { background: rgba(255,255,255,.14); transform: translateX(3px); }
/* B571: this text block had no min-width, so flexbox's default
   min-width:auto let it push past the card's edge on a narrow phone
   instead of wrapping - the hero section's own overflow:hidden then
   silently clipped whatever stuck out (the end of "...and feedback"). */
.lp-flow-text { flex: 1; min-width: 0; }
.lp-flow-card strong { display: block; font-size: 15px; }
.lp-flow-card small { display: block; font-size: 12.5px; color: rgba(255,255,255,.65); overflow-wrap: break-word; }
.lp-flow-line { width: 2px; height: 22px; margin-left: 27px; background: linear-gradient(var(--mint), transparent); }
.lp-dot { width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.lp-dot svg { width: 16px; height: 16px; stroke: #16331f; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.lp-dot-a { background: var(--gold); }
.lp-dot-b { background: var(--sage); }
.lp-dot-c { background: var(--fern); box-shadow: 0 0 0 4px rgba(143,224,176,.25); }

.lp-hero-wave { position: absolute; left: 0; right: 0; bottom: -1px; line-height: 0; pointer-events: none; z-index: 2; }
.lp-hero-wave svg { display: block; width: 100%; height: 44px; }

/* Sections */
.lp-sec { padding: 84px 0; }
.lp-sec-tint { background: var(--foam); border-top: 1px solid var(--cloud); border-bottom: 1px solid var(--cloud); }
.lp-kicker { font-size: 12px; letter-spacing: 1.2px; text-transform: uppercase; color: var(--moss); font-weight: 700; margin-bottom: 8px; }
.lp-keepcase { text-transform: none; }
.lp-sec h2 { font-family: var(--serif); font-weight: 400; font-size: clamp(28px, 4vw, 40px); line-height: 1.15; color: var(--forest); margin-bottom: 36px; }

.lp-grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.lp-card { position: relative; overflow: hidden; background: #fff; border: 1px solid var(--cloud); border-radius: var(--r-lg); padding: 26px; box-shadow: var(--sh-card); transition: transform .2s var(--ease), box-shadow .2s; }
.lp-card::after { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; transform: scaleX(0); transform-origin: left; transition: transform .3s var(--ease); }
.lp-card:nth-child(1)::after { background: var(--moss); }
.lp-card:nth-child(2)::after { background: var(--blue); }
.lp-card:nth-child(3)::after { background: var(--amber); }
.lp-card:hover { transform: translateY(-3px); box-shadow: var(--sh); }
.lp-card:hover::after { transform: scaleX(1); }
.lp-card h3 { font-size: 17px; margin: 16px 0 8px; color: var(--forest); }
.lp-card p { color: var(--stone); font-size: 14.5px; }
.lp-ico { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
.lp-ico svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.lp-ico-g { background: var(--mist); color: var(--moss); }
.lp-ico-b { background: var(--blue-lt); color: var(--blue); }
.lp-ico-a { background: var(--amber-lt); color: var(--amber); }

.lp-steps { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.lp-steps li { position: relative; background: #fff; border: 1px solid var(--cloud); border-radius: var(--r-lg); padding: 26px; }
.lp-steps h3 { font-size: 17px; margin: 14px 0 6px; color: var(--forest); }
.lp-steps p { color: var(--stone); font-size: 14.5px; }
.lp-num { width: 34px; height: 34px; border-radius: 50%; background: var(--grad-moss); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; box-shadow: 0 4px 12px rgba(46,109,68,.3); }

.lp-who { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 32px; }
.lp-who div { background: #fff; border: 1px solid var(--cloud); border-top: 3px solid var(--mint); border-radius: var(--r-lg); padding: 18px 20px; }
.lp-who strong { display: block; color: var(--forest); margin-bottom: 4px; }
.lp-who span { color: var(--stone); font-size: 14px; }

/* Contact */
.lp-grid2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.lp-contact { background: #fff; border: 1px solid var(--cloud); border-radius: var(--r-lg); padding: 22px 24px; transition: transform .2s var(--ease), box-shadow .2s; }
.lp-contact:hover { transform: translateY(-2px); box-shadow: var(--sh-card); }
.lp-contact h3 { font-size: 16px; color: var(--forest); margin-bottom: 10px; }
.lp-contact ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.lp-contact li { display: flex; gap: 12px; font-size: 14px; }
.lp-contact li span { width: 64px; flex-shrink: 0; color: var(--stone); }
.lp-contact a { color: var(--moss); text-decoration: none; word-break: break-word; }
.lp-contact a:hover { text-decoration: underline; }
/* B539 */
.lp-contact-hours { margin: 10px 0 0; padding-top: 10px; border-top: 1px solid var(--cloud); font-size: 13px; color: var(--stone); }
.lp-more { margin: 44px auto 0; max-width: 560px; text-align: center; }
.lp-more h3 { font-size: 18px; color: var(--forest); margin-bottom: 6px; }
.lp-more p { color: var(--stone); font-size: 14.5px; }
.lp-more a { color: var(--moss); font-weight: 600; }

/* Footer (B540): branding / Quick Links / Legal columns, then a divider and the privacy + copyright lines. */
.lp-foot { background: var(--forest); color: rgba(255,255,255,.75); padding: 40px 0 24px; font-size: 13px; }
.lp-foot-in { display: grid; grid-template-columns: 1.3fr 1fr 1fr; gap: 28px; padding-bottom: 24px; }
.lp-foot-col { display: flex; flex-direction: column; gap: 9px; }
.lp-foot-col h4 { font-size: 12px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; color: #fff; margin: 0 0 3px; }
.lp-foot-brand strong { display: block; color: #fff; font-size: 16px; margin-bottom: 2px; }
.lp-foot-brand span { line-height: 1.5; }
.lp-foot-col a { color: rgba(255,255,255,.75); text-decoration: none; }
.lp-foot-col a:hover { color: #fff; text-decoration: underline; }
.lp-foot-divider { border-top: 1px solid rgba(255,255,255,.18); }
.lp-foot-bottom { padding-top: 16px; display: flex; flex-direction: column; gap: 4px; }
.lp-foot-bottom p { margin: 0; font-size: 12px; color: rgba(255,255,255,.65); }
.lp-linkbtn { background: none; border: 0; padding: 0; text-align: left; font: inherit; color: rgba(255,255,255,.75); cursor: pointer; }
.lp-linkbtn:hover { color: #fff; text-decoration: underline; }
.lp-linkbtn:focus-visible { outline: 2px solid var(--mint); outline-offset: 2px; border-radius: 3px; }

/* Data Privacy Act dialog */
.lp-modal { position: fixed; inset: 0; z-index: 100; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; padding: 16px; }
.lp-dialog { background: #fff; color: var(--ink); width: 100%; max-width: 560px; max-height: 88vh; display: flex; flex-direction: column; border-radius: var(--r-lg); box-shadow: var(--sh-lg); outline: none; font-size: 14px; }
.lp-dialog-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 22px; border-bottom: 1px solid var(--cloud); }
.lp-dialog-head h3 { font-size: 16px; color: var(--forest); }
.lp-dialog-body { padding: 18px 22px; overflow-y: auto; line-height: 1.6; }
.lp-dialog-body h4 { margin: 14px 0 4px; font-size: 13px; color: var(--moss); text-transform: uppercase; letter-spacing: .6px; }
.lp-dialog-body h4:first-child { margin-top: 0; }
.lp-dialog-body p, .lp-dialog-body li { color: var(--slate); }
.lp-dialog-body ul { margin: 6px 0 0; padding-left: 20px; }
.lp-dialog-links { display: flex; flex-direction: column; gap: 6px; }
.lp-dialog-links a { color: var(--moss); font-weight: 600; word-break: break-word; }
.lp-dialog-foot { padding: 14px 22px; border-top: 1px solid var(--cloud); display: flex; justify-content: flex-end; }

/* FAQs (B541) */
.lp-faq-item + .lp-faq-item { margin-top: 16px; }
.lp-faq-item h4 { margin: 0 0 4px; font-size: 14px; color: var(--forest); text-transform: none; letter-spacing: normal; }
.lp-faq-item p { margin: 0; color: var(--slate); }

/* Scroll-reveal */
.lp .reveal { opacity: 0; transform: translateY(18px); transition: opacity .6s var(--ease), transform .6s var(--ease); will-change: opacity, transform; }
.lp .reveal.is-in { opacity: 1; transform: none; }
.lp-grid3 > .reveal:nth-child(2), .lp-steps > .reveal:nth-child(2), .lp-who > .reveal:nth-child(2), .lp-grid2 > .reveal:nth-child(2) { transition-delay: .09s; }
.lp-grid3 > .reveal:nth-child(3), .lp-steps > .reveal:nth-child(3), .lp-who > .reveal:nth-child(3), .lp-grid2 > .reveal:nth-child(3) { transition-delay: .18s; }
.lp-grid2 > .reveal:nth-child(4) { transition-delay: .27s; }

/* Phones and small tablets */
@media (max-width: 860px) {
  /* The bottom wave (.lp-hero-wave, 44px tall, absolutely positioned at the
     hero's bottom edge) was covering "Case followed through"'s second line
     on phones - 56px of padding-bottom left barely any clearance once the
     flow cards stack into a single column and grow taller. */
  .lp-hero-in { grid-template-columns: 1fr; padding-top: 48px; padding-bottom: 88px; gap: 32px; }
  .lp-grid3, .lp-steps, .lp-who, .lp-grid2, .lp-foot-in { grid-template-columns: 1fr; }
  .lp-links, .lp-nav-cta { display: none; }
  .lp-totop-float { bottom: 20px; right: 20px; }
  /* B572: the subtitle was hidden outright on mobile instead of just
     shrinking to fit - shown here at a smaller size, wrapping onto its
     own line(s) below "iCARE" rather than pushing the nav bar wider. */
  .lp-brand-text small { display: block; font-size: 9px; line-height: 1.25; max-width: 190px; white-space: normal; }
  .lp-nav-in { gap: 10px; min-height: 58px; }
  .lp-burger { display: inline-flex; }
  .lp-hero, .lp-sec { scroll-margin-top: 62px; }
  .lp-sec { padding: 56px 0; }
  .lp-sec h2 { margin-bottom: 26px; }

  /* Slide-down section menu */
  .lp-mobile { display: grid; max-height: 0; overflow: hidden; background: #fff; border-top: 1px solid transparent; transition: max-height .28s var(--ease), border-color .28s; }
  .lp-mobile.is-open { max-height: 320px; border-top-color: var(--cloud); box-shadow: 0 10px 24px rgba(20,48,30,.08); }
  .lp-mobile a:not(.lp-btn) { padding: 13px 20px; font-size: 15px; font-weight: 500; color: var(--slate); text-decoration: none; border-bottom: 1px solid var(--snow); }
  .lp-mobile a:not(.lp-btn):hover { background: var(--mist); color: var(--forest); }
  .lp-mobile-cta { margin: 12px 20px 16px; }
}
@media (max-width: 420px) {
  .lp-hero h1 { font-size: 34px; }
  .lp-hero p { font-size: 15.5px; }
  .lp-wrap { padding: 0 16px; }
  /* B572: on the narrowest phones, the logos + "iCARE" + burger button
     alone are already tight, so the subtitle gets a bit less room still. */
  .lp-brand-text small { max-width: 140px; }
}
@media (prefers-reduced-motion: reduce) {
  .lp-btn, .lp-card, .lp-contact, .lp-flow-card { transition: none; }
  .lp-btn:hover, .lp-card:hover, .lp-contact:hover, .lp-flow-card:hover { transform: none; }
  .lp .reveal { opacity: 1; transform: none; transition: none; }
}
</style>