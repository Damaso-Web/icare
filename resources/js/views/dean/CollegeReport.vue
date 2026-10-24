<!--
  College Report (Dean): how the Dean's college compares with the other
  colleges by number of referrals, and a breakdown of their own college's
  referrals by status, service, program and month.
-->
<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:16px;display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div>
        <h1>College Report</h1>
        <p>Referrals of {{ data.college || auth.user?.college }} compared with the other colleges.</p>
      </div>
      <!-- B592: the options come from the Admin's School Calendar (Academic
           Years) instead of a hardcoded list of calendar years. -->
      <select v-model="year" class="fsm" @change="load">
        <option v-for="ay in academicYears" :key="ay.id" :value="ay.start_year">{{ ay.start_year }}-{{ ay.start_year + 1 }}</option>
      </select>
    </div>

    <div v-if="loading" style="text-align:center;padding:44px">
      <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
    </div>
    <div v-else-if="error" class="icard"><div class="empty-state"><h3>Could not load the report</h3><p>{{ error }}</p></div></div>

    <template v-else>
      <!-- Headline numbers -->
      <div class="cr-tiles">
        <div class="icard cr-tile">
          <div class="cr-k">Referrals in {{ data.year }}</div>
          <div class="cr-v">{{ data.total }}</div>
          <div class="cr-s">students of your college</div>
        </div>
        <div class="icard cr-tile">
          <div class="cr-k">Rank among colleges</div>
          <div class="cr-v">{{ data.rank ? '#' + data.rank : '-' }}<span v-if="data.rank" class="cr-of"> of {{ data.colleges }}</span></div>
          <!-- B593 -->
          <div class="cr-s">With Most Referrals</div>
        </div>
        <div class="icard cr-tile">
          <div class="cr-k">Busiest month</div>
          <div class="cr-v">{{ busiestMonth }}</div>
          <div class="cr-s">by number of referrals</div>
        </div>
      </div>

      <!-- Ranking -->
      <div class="icard" style="margin-bottom:16px">
        <div class="icard-header"><span class="icard-title">Ranking of Colleges by Referrals</span></div>
        <div class="icard-body">
          <div v-if="!data.ranking?.length" class="cr-empty">No referrals in {{ data.year }} yet.</div>
          <!-- Other colleges show their placement only; only your own college's count is shown. -->
          <div v-for="r in data.ranking" :key="r.college" class="cr-row rank-only" :class="{ mine: r.mine }" :title="r.mine ? `${r.college}: ${r.total} referral${r.total === 1 ? '' : 's'} (rank ${r.rank})` : `${r.college}: rank ${r.rank}`">
            <div class="cr-rank">#{{ r.rank }}</div>
            <div class="cr-name">{{ r.college }} <span v-if="r.mine" class="cr-you">Your college</span></div>
            <div class="cr-num">{{ r.mine ? r.total + ' referral' + (r.total === 1 ? '' : 's') : '' }}</div>
          </div>
          <div style="font-size:11px;color:var(--fog);margin-top:8px">#1 has the most referrals. Other colleges' counts are not shown.</div>
        </div>
      </div>

      <!-- Month by month -->
      <div class="icard" style="margin-bottom:16px">
        <div class="icard-header"><span class="icard-title">Referrals per Month ({{ data.year }})</span></div>
        <div class="icard-body">
          <div class="cr-months">
            <div v-for="(n, i) in data.by_month" :key="i" class="cr-mcol" :title="`${MONTHS[i]} ${data.year}: ${n} referral${n === 1 ? '' : 's'}`">
              <div class="cr-mval">{{ n || '' }}</div>
              <div class="cr-mbar"><span :style="{ height: pct(n, maxMonth) }"></span></div>
              <div class="cr-mlab">{{ MONTHS[i] }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Breakdowns -->
      <div class="cr-grid">
        <div v-for="b in breakdowns" :key="b.title" class="icard">
          <div class="icard-header"><span class="icard-title">{{ b.title }}</span></div>
          <div class="icard-body">
            <div v-if="!b.rows.length" class="cr-empty">None yet.</div>
            <div v-for="r in b.rows" :key="r.label" class="cr-row small" :title="`${b.nice(r.label)}: ${r.total}`">
              <div class="cr-name">{{ b.nice(r.label) }}</div>
              <div class="cr-bar"><span :style="{ width: pct(r.total, b.max) }"></span></div>
              <div class="cr-num">{{ r.total }}</div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../api/index';
import { useAuthStore } from '../../stores/auth';
import { toTitleCase } from '../../utils/validators';

const auth = useAuthStore();
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
// B592: academic years come from the Admin's School Calendar instead of a
// hardcoded list; the dropdown defaults to whichever one is marked current.
const academicYears = ref([]);
const year = ref(new Date().getFullYear());
const data = ref({});
const loading = ref(true);
const error = ref('');

async function fetchAcademicYears() {
  try {
    const res = await api.get('/management/academic-years');
    academicYears.value = (res.data || []).sort((a, b) => b.start_year - a.start_year);
    const current = academicYears.value.find(ay => ay.is_current);
    year.value = current ? current.start_year : (academicYears.value[0]?.start_year ?? year.value);
  } catch (e) { /* the dropdown falls back to the current calendar year */ }
}

const pct = (n, max) => (max ? Math.max(2, Math.round((n / max) * 100)) : 0) + '%';

const maxMonth = computed(() => Math.max(0, ...(data.value.by_month || [])));
const busiestMonth = computed(() => {
  const m = data.value.by_month || [];
  const top = Math.max(0, ...m);
  return top ? MONTHS[m.indexOf(top)] : '-';
});
const nice = s => toTitleCase(String(s).replace(/_/g, ' '));
const plain = s => s;
const breakdowns = computed(() => [
  { title: 'By Status',               rows: data.value.by_status || [],     nice },
  { title: 'By Service',              rows: data.value.by_type || [],       nice },
  { title: 'By Program',              rows: data.value.by_program || [],    nice: plain },
].map(b => ({ ...b, max: Math.max(0, ...b.rows.map(r => r.total)) })));

async function load() {
  loading.value = true;
  error.value = '';
  try {
    data.value = (await api.get('/college-report', { params: { year: year.value } })).data;
  } catch (e) {
    error.value = e.response?.data?.message || 'Please try again.';
  } finally {
    loading.value = false;
  }
}
onMounted(async () => {
  await fetchAcademicYears();
  load();
});
</script>

<style scoped>
.cr-tiles{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px}
.cr-tile{padding:16px 18px}
.cr-k{font-size:10.5px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog)}
.cr-v{font-size:30px;font-weight:700;color:var(--ink);margin-top:4px;line-height:1.1}
.cr-of{font-size:14px;font-weight:600;color:var(--stone)}
.cr-s{font-size:11.5px;color:var(--stone);margin-top:2px}
.cr-row{display:grid;grid-template-columns:44px minmax(0,1.6fr) minmax(0,2fr) 40px;align-items:center;gap:10px;padding:7px 8px;border-radius:var(--r-sm)}
.cr-row.small{grid-template-columns:minmax(0,1.4fr) minmax(0,1.6fr) 36px}
.cr-row.rank-only{grid-template-columns:44px minmax(0,1fr) auto}
.cr-row:hover{background:var(--snow)}
.cr-row.mine{background:var(--mist)}
.cr-rank{font-size:12px;font-weight:700;color:var(--stone)}
.cr-name{font-size:12.5px;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cr-you{font-size:10px;font-weight:700;color:var(--forest);background:#fff;border:1px solid var(--moss);border-radius:10px;padding:1px 7px;margin-left:6px}
.cr-bar{height:10px;background:var(--cloud);border-radius:4px;overflow:hidden}
.cr-bar span{display:block;height:100%;background:var(--moss);border-radius:0 4px 4px 0}
.cr-num{font-size:12.5px;font-weight:700;color:var(--ink);text-align:right}
.cr-months{display:grid;grid-template-columns:repeat(12,1fr);gap:8px;align-items:end;height:170px}
.cr-mcol{display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end;gap:4px}
.cr-mval{font-size:11px;font-weight:700;color:var(--ink);min-height:14px}
.cr-mbar{width:60%;max-width:28px;flex:1;display:flex;align-items:flex-end}
.cr-mbar span{display:block;width:100%;background:var(--moss);border-radius:4px 4px 0 0;min-height:0}
.cr-mlab{font-size:10.5px;color:var(--stone)}
.cr-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.cr-empty{font-size:12.5px;color:var(--fog)}
@media (max-width:860px){
  .cr-tiles{grid-template-columns:1fr}
  .cr-grid{grid-template-columns:1fr}
  .cr-row{grid-template-columns:34px minmax(0,1fr) 34px}
  .cr-row .cr-bar{grid-column:2 / 4}
  .cr-months{gap:3px}
}
</style>