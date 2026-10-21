<template>
  <div class="lp">
    <!-- Top bar -->
    <header class="lp-nav">
      <div class="lp-wrap lp-nav-in">
        <a class="lp-brand" href="#top" @click.prevent="go('top')">
          <img :src="'/bsu-logo.jpg'" alt="Benguet State University" />
          <img :src="'/oss-logo.jpg'" alt="Office of Student Services" />
          <span>iCARE</span>
        </a>
        <nav class="lp-links" aria-label="Sections">
          <a href="#services" @click.prevent="go('services')">Services</a>
          <a href="#how" @click.prevent="go('how')">How it works</a>
          <a href="#contact" @click.prevent="go('contact')">Contact</a>
        </nav>
        <router-link :to="{ name: signedIn ? 'dashboard' : 'login-choice' }" class="lp-btn lp-btn-solid">
          {{ signedIn ? 'Go to dashboard' : 'Log in' }}
        </router-link>
      </div>
    </header>

    <!-- Hero -->
    <section id="top" class="lp-hero">
      <div class="lp-wrap lp-hero-in">
        <div class="lp-hero-text">
          <div class="lp-eyebrow">Benguet State University · Office of Student Services</div>
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
            <span class="lp-dot lp-dot-a"></span>
            <div><strong>Referral sent</strong><small>Faculty, dean or the student</small></div>
          </div>
          <div class="lp-flow-line"></div>
          <div class="lp-flow-card">
            <span class="lp-dot lp-dot-b"></span>
            <div><strong>Appointment scheduled</strong><small>The student picks a date and time</small></div>
          </div>
          <div class="lp-flow-line"></div>
          <div class="lp-flow-card">
            <span class="lp-dot lp-dot-c"></span>
            <div><strong>Case followed through</strong><small>Sessions, notes and feedback</small></div>
          </div>
        </div>
      </div>
    </section>

    <!-- Services -->
    <section id="services" class="lp-sec">
      <div class="lp-wrap">
        <div class="lp-kicker">What iCARE supports</div>
        <h2>Three units, one system</h2>
        <div class="lp-grid3">
          <article class="lp-card">
            <div class="lp-ico lp-ico-g">
              <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </div>
            <h3>Guidance and Counseling Unit</h3>
            <p>Counseling and other student services, from the first referral to the final session notes.</p>
          </article>
          <article class="lp-card">
            <div class="lp-ico lp-ico-b">
              <svg viewBox="0 0 24 24"><path d="M9 2v6l-6 10a2 2 0 0 0 1.7 3h14.6a2 2 0 0 0 1.7-3L15 8V2"/><path d="M9 2h6"/><path d="M7 15h10"/></svg>
            </div>
            <h3>Testing and Materials Development Unit</h3>
            <p>Psychological testing schedules and records, kept together with the student's referral.</p>
          </article>
          <article class="lp-card">
            <div class="lp-ico lp-ico-a">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/></svg>
            </div>
            <h3>Student Discipline Unit</h3>
            <p>Incident reports and complaints, filed and tracked with a clear status at every step.</p>
          </article>
        </div>
      </div>
    </section>

    <!-- How it works -->
    <section id="how" class="lp-sec lp-sec-tint">
      <div class="lp-wrap">
        <div class="lp-kicker">How it works</div>
        <h2>From request to follow-up in three steps</h2>
        <ol class="lp-steps">
          <li>
            <span class="lp-num">1</span>
            <h3>Sign in</h3>
            <p>Students sign in with their Student ID. BSU personnel sign in with their employee account.</p>
          </li>
          <li>
            <span class="lp-num">2</span>
            <h3>Refer or request</h3>
            <p>Send a referral, file a report, or pick the schedule offered for your appointment.</p>
          </li>
          <li>
            <span class="lp-num">3</span>
            <h3>Track progress</h3>
            <p>See the status of each referral and appointment, and get notified when something changes.</p>
          </li>
        </ol>

        <div class="lp-who">
          <div><strong>Students</strong><span>Refer yourself or a classmate, choose appointment times, follow your requests.</span></div>
          <div><strong>Faculty and deans</strong><span>Refer students and see what happened with your referrals.</span></div>
          <div><strong>OSS staff</strong><span>Manage referrals, appointments, cases and records in one place.</span></div>
        </div>
      </div>
    </section>

    <!-- Contact -->
    <section id="contact" class="lp-sec">
      <div class="lp-wrap">
        <div class="lp-kicker">Contact us</div>
        <h2>Office of Student Services</h2>
        <div class="lp-grid2">
          <article v-for="c in contacts" :key="c.name" class="lp-contact">
            <h3>{{ c.name }}</h3>
            <ul>
              <li v-if="c.email"><span>Email</span><a :href="'mailto:' + c.email">{{ c.email }}</a></li>
              <li v-if="c.phone"><span>{{ c.phoneLabel }}</span><a :href="'tel:' + c.phone.replace(/[^0-9+]/g, '')">{{ c.phone }}</a></li>
              <li v-if="c.facebook"><span>Facebook</span><a :href="c.facebook" target="_blank" rel="noopener noreferrer">{{ c.facebookLabel }}</a></li>
            </ul>
          </article>
        </div>
        <p class="lp-web">
          More about the office on the
          <a href="https://bsu.edu.ph/office-of-student-services/" target="_blank" rel="noopener noreferrer">BSU Office of Student Services website</a>.
        </p>
      </div>
    </section>

    <!-- Footer -->
    <footer class="lp-foot">
      <div class="lp-wrap lp-foot-in">
        <div>
          <strong>iCARE</strong>
          <span>An Integrated Case Management System for Benguet State University - Office of Student Services</span>
        </div>
        <div class="lp-foot-r">
          Personal information is handled under the Data Privacy Act of 2012 (RA 10173).
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const signedIn = computed(() => !!localStorage.getItem('token'));

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
    facebook: 'https://www.facebook.com/tmdu.bsuoss', facebookLabel: 'TMDU BSU OSS',
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
.lp { min-height: 100vh; background: var(--snow); color: var(--ink); font-family: var(--font); font-size: 15px; line-height: 1.6; overflow-x: hidden; }
.lp * { box-sizing: border-box; }
.lp-wrap { width: 100%; max-width: 1120px; margin: 0 auto; padding: 0 20px; }
.lp a { color: inherit; }
.lp h1, .lp h2, .lp h3, .lp p { margin: 0; }

/* Nav */
.lp-nav { position: sticky; top: 0; z-index: 20; background: rgba(248,250,249,.92); backdrop-filter: blur(8px); border-bottom: 1px solid var(--cloud); }
.lp-nav-in { display: flex; align-items: center; gap: 20px; height: 62px; }
.lp-brand { display: flex; align-items: center; gap: 8px; text-decoration: none; margin-right: auto; }
.lp-brand img { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; background: #fff; }
.lp-brand span { font-weight: 700; font-size: 18px; letter-spacing: .3px; color: var(--forest); margin-left: 4px; }
.lp-links { display: flex; gap: 22px; }
.lp-links a { text-decoration: none; font-size: 14px; color: var(--slate); }
.lp-links a:hover { color: var(--moss); }

/* Buttons */
.lp-btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px; border-radius: 999px; font-size: 14px; font-weight: 600; text-decoration: none; border: 1.5px solid transparent; cursor: pointer; transition: transform .15s var(--ease), background .15s, box-shadow .15s; }
.lp-btn:hover { transform: translateY(-1px); }
.lp-btn-solid { background: var(--moss); color: #fff !important; }
.lp-btn-solid:hover { background: var(--pine); }
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
.lp-flow-card { display: flex; align-items: center; gap: 14px; padding: 16px 18px; border-radius: var(--r-lg); background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.16); backdrop-filter: blur(6px); }
.lp-flow-card strong { display: block; font-size: 15px; }
.lp-flow-card small { display: block; font-size: 12.5px; color: rgba(255,255,255,.65); }
.lp-flow-line { width: 2px; height: 22px; margin-left: 27px; background: linear-gradient(var(--mint), transparent); }
.lp-dot { width: 20px; height: 20px; border-radius: 50%; flex-shrink: 0; }
.lp-dot-a { background: var(--gold); }
.lp-dot-b { background: var(--sage); }
.lp-dot-c { background: var(--fern); box-shadow: 0 0 0 4px rgba(143,224,176,.25); }

/* Sections */
.lp-sec { padding: 84px 0; }
.lp-sec-tint { background: var(--foam); border-top: 1px solid var(--cloud); border-bottom: 1px solid var(--cloud); }
.lp-kicker { font-size: 12px; letter-spacing: 1.2px; text-transform: uppercase; color: var(--moss); font-weight: 700; margin-bottom: 8px; }
.lp-sec h2 { font-family: var(--serif); font-weight: 400; font-size: clamp(28px, 4vw, 40px); line-height: 1.15; color: var(--forest); margin-bottom: 36px; }

.lp-grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.lp-card { background: #fff; border: 1px solid var(--cloud); border-radius: var(--r-lg); padding: 26px; box-shadow: var(--sh-card); transition: transform .2s var(--ease), box-shadow .2s; }
.lp-card:hover { transform: translateY(-3px); box-shadow: var(--sh); }
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
.lp-num { width: 34px; height: 34px; border-radius: 50%; background: var(--grad-moss); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }

.lp-who { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 28px; }
.lp-who div { padding: 16px 4px 0; border-top: 2px solid var(--mint); }
.lp-who strong { display: block; color: var(--forest); margin-bottom: 4px; }
.lp-who span { color: var(--stone); font-size: 14px; }

/* Contact */
.lp-grid2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.lp-contact { background: #fff; border: 1px solid var(--cloud); border-radius: var(--r-lg); padding: 22px 24px; }
.lp-contact h3 { font-size: 16px; color: var(--forest); margin-bottom: 10px; }
.lp-contact ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.lp-contact li { display: flex; gap: 12px; font-size: 14px; }
.lp-contact li span { width: 64px; flex-shrink: 0; color: var(--fog); }
.lp-contact a { color: var(--moss); text-decoration: none; word-break: break-word; }
.lp-contact a:hover { text-decoration: underline; }
.lp-web { margin-top: 22px; color: var(--stone); font-size: 14px; }
.lp-web a { color: var(--moss); font-weight: 600; }

/* Footer */
.lp-foot { background: var(--forest); color: rgba(255,255,255,.75); padding: 28px 0; font-size: 13px; }
.lp-foot-in { display: flex; justify-content: space-between; gap: 20px; flex-wrap: wrap; }
.lp-foot strong { display: block; color: #fff; font-size: 15px; margin-bottom: 2px; }
.lp-foot-r { max-width: 340px; }

/* Phones and small tablets */
@media (max-width: 860px) {
  .lp-hero-in { grid-template-columns: 1fr; padding-top: 56px; padding-bottom: 60px; gap: 36px; }
  .lp-grid3, .lp-steps, .lp-who, .lp-grid2 { grid-template-columns: 1fr; }
  .lp-links { display: none; }
  .lp-sec { padding: 56px 0; }
}
@media (prefers-reduced-motion: reduce) {
  .lp-btn, .lp-card { transition: none; }
  .lp-btn:hover, .lp-card:hover { transform: none; }
}
</style>