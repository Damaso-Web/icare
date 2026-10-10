<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Reports & Analytics</h1>
      <p>{{ UNIT_NAMES[unit] }}: {{ currentReport.blurb }}</p>
    </div>

    <!-- Each unit has its own report. Admin can open any of them; unit staff only see their own. -->
    <div v-if="canPickUnit" style="display:flex;gap:8px;margin-bottom:16px">
      <button
        v-for="u in UNITS"
        :key="u"
        class="ibtn ibtn-sm"
        :class="unit === u ? 'ibtn-p' : 'ibtn-o'"
        @click="selectUnit(u)"
      >
        {{ u }} Report
      </button>
    </div>

    <!-- Which report of that unit: the full year-end report, or one part of it on its own -->
    <div class="rt-tabs">
      <button
        v-for="t in REPORT_TYPES"
        :key="t.value"
        type="button"
        class="rt-tab"
        :class="{ active: reportType === t.value }"
        @click="reportType = t.value"
      >
        {{ t.label }}
      </button>
    </div>

    <!-- Period filter: one line on desktop (items shrink instead of wrapping); stacks on phones -->
    <div class="filter-bar rp-bar" style="margin-bottom:20px">
      <label class="rp-field">
        <span>Academic Year</span>
        <select v-model="academicYear" class="fsm" :disabled="period === 'custom'" @change="applyPeriod">
          <option v-for="y in academicYears" :key="y" :value="y">{{ y }}–{{ y + 1 }}</option>
        </select>
      </label>
      <label class="rp-field">
        <span>Period</span>
        <select v-model="period" class="fsm" @change="applyPeriod">
          <option v-for="p in PERIODS" :key="p.value" :value="p.value">{{ p.label }}</option>
          <option value="custom">Custom Range</option>
        </select>
      </label>
      <!-- From - To in one box; editable only for Custom Range -->
      <div class="rp-range" :class="{ locked: period !== 'custom' }" :title="period !== 'custom' ? 'Choose Custom Range to type your own dates' : 'Date range'">
        <input v-model="dateFrom" type="date" title="From date" :max="dateTo || undefined" :disabled="period !== 'custom'" />
        <span>–</span>
        <input v-model="dateTo" type="date" title="To date" :min="dateFrom || undefined" :disabled="period !== 'custom'" />
      </div>
      <button class="ibtn ibtn-p ibtn-sm rp-btn" title="Reload the report figures for the selected date range" @click="fetchAll">
        Generate
      </button>
      <!-- One "Export as" button; picking a format downloads it right away -->
      <div ref="exportMenuEl" class="rp-export">
        <button class="ibtn ibtn-o ibtn-sm rp-btn" :disabled="!!exporting" @click="exportMenuOpen = !exportMenuOpen">
          <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          {{ exporting ? `Exporting ${exporting === 'pdf' ? 'PDF' : 'Excel'}...` : 'Export as' }}
          <svg v-if="!exporting" viewBox="0 0 24 24" style="width:12px;height:12px"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div v-if="exportMenuOpen" class="export-menu">
          <button type="button" @click="exportAs('pdf')"><strong>PDF</strong><small>Ready to print</small></button>
          <button type="button" @click="exportAs('excel')"><strong>Excel</strong><small>Editable spreadsheet</small></button>
        </div>
      </div>
    </div>

    <!-- What the figures below cover -->
    <div style="font-size:12.5px;color:var(--stone);margin:-8px 0 16px">
      Showing: <strong style="color:var(--ink)">{{ shownLabel }}</strong>
    </div>

    <!-- Loading -->
    <div v-if="loading" style="text-align:center;padding:44px">
      <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
    </div>

    <!-- Load failure -->
    <div v-else-if="loadError" class="icard">
      <div class="empty-state">
        <h3>Couldn't load the report</h3>
        <p v-if="loadError === 'forbidden'">Your account's current role does not have access to Reports. Only Admin / GCU Head and GCU Staff can view them.</p>
        <p v-else>The server or the database did not respond. Check your internet connection, then try again.</p>
        <button class="ibtn ibtn-p ibtn-sm" style="margin-top:12px" @click="fetchAll">Try Again</button>
      </div>
    </div>

    <!-- SDU report: complaints received, by misconduct, college and department -->
    <template v-else-if="unit === 'SDU' && reportType === 'year_end'">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:20px">
        <div class="stat-card" v-for="stat in sduStats" :key="stat.label">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px">
            <div class="stat-icon" :style="{ background: stat.iconBg }">
              <svg viewBox="0 0 24 24" :style="{ color: stat.iconColor }" v-html="stat.icon"></svg>
            </div>
          </div>
          <div class="stat-num">{{ stat.value }}</div>
          <div class="stat-label">{{ stat.label }}</div>
        </div>
      </div>

      <div class="icard" style="margin-bottom:16px">
        <div class="icard-header">
          <span class="icard-title" style="white-space:nowrap">Complaints by Misconduct</span>
          <span v-if="topOf(sduData.by_misconduct)" class="ibadge" :title="topOf(sduData.by_misconduct).label" style="background:var(--mist);color:var(--moss);display:block;min-width:0;max-width:65%;overflow:hidden;text-overflow:ellipsis">Most common: {{ topOf(sduData.by_misconduct).label }} ({{ topOf(sduData.by_misconduct).count }})</span>
        </div>
        <div class="icard-body">
          <div v-if="!sduData.by_misconduct?.length" style="text-align:center;color:var(--fog);font-size:13px">No data</div>
          <div v-for="item in sduData.by_misconduct" :key="item.label" style="margin-bottom:11px">
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--slate);margin-bottom:5px">
              <span>{{ item.label }}</span>
              <span style="color:var(--stone)">{{ item.count }}</span>
            </div>
            <div style="background:var(--cloud);border-radius:4px;height:7px;overflow:hidden">
              <div :style="{ width: pct(item.count, sduData.total) + '%', background: 'var(--moss)', height: '100%', borderRadius: '4px' }"></div>
            </div>
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px;align-items:start">
        <div class="icard" style="min-width:0">
          <div class="icard-header">
            <span class="icard-title" style="white-space:nowrap">Complaints by College</span>
            <span v-if="topOf(sduData.by_college)" class="ibadge" style="background:var(--mist);color:var(--moss)">Highest: {{ collegeShort(topOf(sduData.by_college).label) }} ({{ topOf(sduData.by_college).count }})</span>
          </div>
          <div class="icard-body">
            <div v-if="!sduData.by_college?.length" style="text-align:center;color:var(--fog);font-size:13px">No data</div>
            <div v-for="item in sduData.by_college" :key="item.label" style="margin-bottom:11px">
              <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--slate);margin-bottom:5px">
                <span>{{ item.label }}</span>
                <span style="color:var(--stone)">{{ item.count }}</span>
              </div>
              <div style="background:var(--cloud);border-radius:4px;height:7px;overflow:hidden">
                <div :style="{ width: pct(item.count, sduData.total) + '%', background: 'var(--moss)', height: '100%', borderRadius: '4px' }"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="icard" style="min-width:0">
          <div class="icard-header"><span class="icard-title">Complaints by Department</span></div>
          <div class="ts">
            <table class="itable">
              <thead>
                <tr><th>Department</th><th>College</th><th style="width:110px">Complaints</th></tr>
              </thead>
              <tbody>
                <tr v-for="row in sduData.by_department" :key="row.label + row.college">
                  <td>{{ row.label }}</td>
                  <td>{{ collegeShort(row.college) }}</td>
                  <td style="font-weight:600">{{ row.count }}</td>
                </tr>
                <tr v-if="!sduData.by_department?.length">
                  <td colspan="3" style="text-align:center;color:var(--fog)">No data</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>

    <template v-else>
      <!-- Summary Stats -->
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:20px">
        <div class="stat-card" v-for="stat in summaryStats" :key="stat.label">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px">
            <div class="stat-icon" :style="{ background: stat.iconBg }">
              <svg viewBox="0 0 24 24" :style="{ color: stat.iconColor }" v-html="stat.icon"></svg>
            </div>
          </div>
          <div class="stat-num">{{ stat.value }}</div>
          <div class="stat-label">{{ stat.label }}</div>
        </div>
      </div>

      <!-- GCU: "Guidance and Counseling Unit by the Numbers", laid out like the printed GCU Accomplishment Report -->
      <template v-if="unit === 'GCU' && shows('year_end')">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin:4px 0 10px">
          <div style="font-size:13px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:var(--forest)">Guidance and Counseling Unit by the Numbers</div>
          <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--stone);cursor:pointer">
            <input type="checkbox" v-model="showAllCourses" /> Show courses with no students
          </label>
        </div>
        <div class="icard" style="margin-bottom:16px">
          <div class="icard-header"><span class="icard-title">Individual Inventory <span style="font-weight:400;color:var(--stone)">(updating of students' records in the SIAS and in the Anecdotal Record)</span></span></div>
          <div class="ts">
            <table class="itable gcu-num">
              <thead>
                <tr>
                  <th rowspan="2" style="width:130px">COLLEGE</th>
                  <th rowspan="2">COURSE</th>
                  <th colspan="3" class="num">INDIVIDUAL INVENTORY</th>
                </tr>
                <tr><th class="num">MALE</th><th class="num">FEMALE</th><th class="num">TOTAL</th></tr>
              </thead>
              <tbody>
                <template v-for="g in visibleGroups(gcuData.inventory?.undergraduate)" :key="g.college">
                  <tr v-for="(r, i) in g.rows" :key="g.college + r.course">
                    <td v-if="i === 0" :rowspan="g.rows.length" class="col">{{ g.college }}</td>
                    <td>{{ r.course }}</td>
                    <td class="num cnt">{{ r.male }}</td>
                    <td class="num cnt">{{ r.female }}</td>
                    <td class="num tot">{{ r.total }}</td>
                  </tr>
                </template>
                <tr v-if="!sumOf(flatRows(gcuData.inventory?.undergraduate), 'total')"><td colspan="5" style="text-align:center;color:var(--fog)">No records for this period</td></tr>
                <tr v-else class="sum">
                  <td></td>
                  <td>TOTAL</td>
                  <td class="num">{{ sumOf(flatRows(gcuData.inventory?.undergraduate), 'male') }}</td>
                  <td class="num">{{ sumOf(flatRows(gcuData.inventory?.undergraduate), 'female') }}</td>
                  <td class="num">{{ sumOf(flatRows(gcuData.inventory?.undergraduate), 'total') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="icard" style="margin-bottom:16px">
          <div class="icard-header"><span class="icard-title">Individual Inventory: Graduate School</span></div>
          <div class="ts">
            <table class="itable gcu-num">
              <thead>
                <tr>
                  <th rowspan="2" style="width:130px">GRADUATE SCHOOL</th>
                  <th rowspan="2">COURSE</th>
                  <th colspan="3" class="num">INDIVIDUAL INVENTORY</th>
                </tr>
                <tr><th class="num">MALE</th><th class="num">FEMALE</th><th class="num">TOTAL</th></tr>
              </thead>
              <tbody>
                <template v-for="g in visibleGroups(gcuData.inventory?.graduate)" :key="g.college">
                  <tr v-for="(r, i) in g.rows" :key="g.college + r.course">
                    <td v-if="i === 0" :rowspan="g.rows.length" class="col">{{ g.college }}</td>
                    <td>{{ r.course }}</td>
                    <td class="num cnt">{{ r.male }}</td>
                    <td class="num cnt">{{ r.female }}</td>
                    <td class="num tot">{{ r.total }}</td>
                  </tr>
                </template>
                <tr v-if="!sumOf(flatRows(gcuData.inventory?.graduate), 'total')"><td colspan="5" style="text-align:center;color:var(--fog)">No records for this period</td></tr>
                <tr v-else class="sum">
                  <td></td>
                  <td>TOTAL</td>
                  <td class="num">{{ sumOf(flatRows(gcuData.inventory?.graduate), 'male') }}</td>
                  <td class="num">{{ sumOf(flatRows(gcuData.inventory?.graduate), 'female') }}</td>
                  <td class="num">{{ sumOf(flatRows(gcuData.inventory?.graduate), 'total') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="icard" style="margin-bottom:16px">
          <div class="icard-header"><span class="icard-title">Individual Guidance <span style="font-weight:400;color:var(--stone)">(Summary of Counseling/Life Coaching Services Conducted)</span></span></div>
          <div class="svc-wrap">
            <div class="svc-grid">
              <div v-for="(half, h) in serviceHalves" :key="h" class="svc-col" :class="{ 'svc-col-right': h === 1 }">
                <div class="svc-head"><span>Service</span><span>Transactions</span></div>
                <div v-for="r in half" :key="r.service" class="svc-row">
                  <span>{{ r.service }}</span>
                  <span class="svc-count" :class="{ zero: !r.count }">{{ r.count }}</span>
                </div>
              </div>
            </div>
            <div class="svc-total"><span>Total transactions</span><strong>{{ sumOf(gcuData.services, 'count') }}</strong></div>
          </div>
        </div>
        <div class="icard" style="margin-bottom:16px">
          <div class="icard-header"><span class="icard-title">Counseling</span></div>
          <div class="ts">
            <table class="itable gcu-num">
              <thead>
                <tr>
                  <th rowspan="2" style="width:130px">UNDERGRADUATE</th>
                  <th rowspan="2">COURSE</th>
                  <th colspan="3" class="num">COUNSELING</th>
                </tr>
                <tr><th class="num">MALE</th><th class="num">FEMALE</th><th class="num">TOTAL</th></tr>
              </thead>
              <tbody>
                <template v-for="g in visibleGroups(gcuData.counseling?.undergraduate)" :key="g.college">
                  <tr v-for="(r, i) in g.rows" :key="g.college + r.course">
                    <td v-if="i === 0" :rowspan="g.rows.length" class="col">{{ g.college }}</td>
                    <td>{{ r.course }}</td>
                    <td class="num cnt">{{ r.male }}</td>
                    <td class="num cnt">{{ r.female }}</td>
                    <td class="num tot">{{ r.total }}</td>
                  </tr>
                </template>
                <tr v-if="!sumOf(flatRows(gcuData.counseling?.undergraduate), 'total')"><td colspan="5" style="text-align:center;color:var(--fog)">No records for this period</td></tr>
                <tr v-else class="sum">
                  <td></td>
                  <td>TOTAL</td>
                  <td class="num">{{ sumOf(flatRows(gcuData.counseling?.undergraduate), 'male') }}</td>
                  <td class="num">{{ sumOf(flatRows(gcuData.counseling?.undergraduate), 'female') }}</td>
                  <td class="num">{{ sumOf(flatRows(gcuData.counseling?.undergraduate), 'total') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="icard" style="margin-bottom:16px">
          <div class="icard-header"><span class="icard-title">Counseling: Graduate School</span></div>
          <div class="ts">
            <table class="itable gcu-num">
              <thead>
                <tr>
                  <th rowspan="2" style="width:130px">GRADUATE SCHOOL</th>
                  <th rowspan="2">COURSE</th>
                  <th colspan="3" class="num">COUNSELING</th>
                </tr>
                <tr><th class="num">MALE</th><th class="num">FEMALE</th><th class="num">TOTAL</th></tr>
              </thead>
              <tbody>
                <template v-for="g in visibleGroups(gcuData.counseling?.graduate)" :key="g.college">
                  <tr v-for="(r, i) in g.rows" :key="g.college + r.course">
                    <td v-if="i === 0" :rowspan="g.rows.length" class="col">{{ g.college }}</td>
                    <td>{{ r.course }}</td>
                    <td class="num cnt">{{ r.male }}</td>
                    <td class="num cnt">{{ r.female }}</td>
                    <td class="num tot">{{ r.total }}</td>
                  </tr>
                </template>
                <tr v-if="!sumOf(flatRows(gcuData.counseling?.graduate), 'total')"><td colspan="5" style="text-align:center;color:var(--fog)">No records for this period</td></tr>
                <tr v-else class="sum">
                  <td></td>
                  <td>TOTAL</td>
                  <td class="num">{{ sumOf(flatRows(gcuData.counseling?.graduate), 'male') }}</td>
                  <td class="num">{{ sumOf(flatRows(gcuData.counseling?.graduate), 'female') }}</td>
                  <td class="num">{{ sumOf(flatRows(gcuData.counseling?.graduate), 'total') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>

      <!-- Charts Row -->
      <div v-if="shows('referrals')" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;align-items:start">

        <!-- Referrals by College (college of the referred student) -->
        <div class="icard">
          <div class="icard-header">
            <span class="icard-title">Referrals by College</span>
            <span v-if="topCollege" class="ibadge" style="background:var(--mist);color:var(--moss)">Highest: {{ collegeShort(topCollege.college) }} ({{ topCollege.count }})</span>
          </div>
          <div class="icard-body">
            <div v-if="!collegeRows.length" style="text-align:center;color:var(--fog);font-size:13px">No data</div>
            <div v-for="item in collegeRows" :key="item.college" style="margin-bottom:11px">
              <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--slate);margin-bottom:5px">
                <span>{{ item.college }}</span>
                <span style="color:var(--stone)">{{ item.count }}</span>
              </div>
              <div style="background:var(--cloud);border-radius:4px;height:7px;overflow:hidden">
                <div :style="{ width: pct(item.count, referralData.total) + '%', background: 'var(--moss)', height: '100%', borderRadius: '4px' }"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Referrals by Type -->
        <div class="icard">
          <div class="icard-header"><span class="icard-title">Referrals by Type</span></div>
          <div class="icard-body">
            <div v-if="!referralData.by_type?.length" style="text-align:center;color:var(--fog);font-size:13px">No data</div>
            <div v-for="item in referralData.by_type" :key="item.referral_type" style="margin-bottom:11px">
              <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--slate);margin-bottom:5px">
                <span>{{ toTitleCase(item.referral_type) }}</span>
                <span style="color:var(--stone)">{{ item.count }}</span>
              </div>
              <div style="background:var(--cloud);border-radius:4px;height:7px;overflow:hidden">
                <div :style="{ width: pct(item.count, referralData.total) + '%', background: 'var(--moss)', height: '100%', borderRadius: '4px' }"></div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Services Rendered - what the office delivered, as opposed to referrals received -->
      <div v-if="shows('year_end')" class="icard" style="margin-bottom:16px">
        <div class="icard-header"><span class="icard-title">Services Rendered</span></div>
        <div class="svc-wrap">
          <div v-if="!servicesData.length" style="text-align:center;color:var(--fog);font-size:13px">No data</div>
          <div v-else class="svc-grid">
            <div v-for="(half, h) in halvesOf(servicesData)" :key="h" class="svc-col" :class="{ 'svc-col-right': h === 1 }">
              <div class="svc-head"><span>Service</span><span>Count</span></div>
              <div v-for="r in half" :key="r.service" class="svc-row">
                <span>{{ r.service }}</span>
                <span class="svc-count" :class="{ zero: !r.count }">{{ r.count }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Referral patterns: who refers, and how many per month -->
      <div v-if="shows('referrals')" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;align-items:start">

        <!-- Referrals by Source -->
        <div class="icard">
          <div class="icard-header">
            <span class="icard-title">Referrals by Source</span>
            <span v-if="sourceRows.length" class="ibadge" style="background:var(--mist);color:var(--moss)">Most from: {{ sourceRows[0].source }}</span>
          </div>
          <div class="icard-body">
            <div v-if="!sourceRows.length" style="text-align:center;color:var(--fog);font-size:13px">No data</div>
            <div v-for="item in sourceRows" :key="item.source" style="margin-bottom:11px">
              <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--slate);margin-bottom:5px">
                <span>{{ item.source }}</span>
                <span style="color:var(--stone)">{{ item.count }} · {{ pct(item.count, referralData.total) }}%</span>
              </div>
              <div style="background:var(--cloud);border-radius:4px;height:7px;overflow:hidden">
                <div :style="{ width: pct(item.count, referralData.total) + '%', background: 'var(--moss)', height: '100%', borderRadius: '4px' }"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Monthly Trend -->
        <div class="icard">
          <div class="icard-header"><span class="icard-title">Monthly Referral Trend</span></div>
          <div class="icard-body">
            <div v-if="!referralData.monthly_trend?.length" style="text-align:center;color:var(--fog);font-size:13px">No data</div>
            <div v-else style="display:flex;align-items:flex-end;gap:8px;height:150px;padding:0 8px">
              <div
                v-for="item in referralData.monthly_trend"
                :key="item.month + '-' + item.year"
                style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;max-width:90px"
              >
                <div style="font-size:11px;color:var(--stone);font-weight:500">{{ item.count }}</div>
                <div
                  :style="{
                    width: '100%',
                    height: (item.count / maxMonthlyCount * 100) + 'px',
                    background: 'var(--moss)',
                    borderRadius: '4px 4px 0 0',
                    minHeight: '4px',
                    transition: 'height .5s',
                  }"
                ></div>
                <div style="font-size:10px;color:var(--fog)">{{ monthLabel(item.month) }}</div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Cases: completion and backlog beside the status breakdown -->
      <div v-if="shows('cases')" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;align-items:start">

        <!-- Case Completion -->
        <div class="icard">
          <div class="icard-header">
            <span class="icard-title">Case Completion</span>
            <span class="ibadge" style="background:var(--mist);color:var(--moss)">{{ caseData.completed ?? 0 }} of {{ caseData.total ?? 0 }} cases</span>
          </div>
          <div class="icard-body">
            <div v-if="!caseData.total" style="text-align:center;color:var(--fog);font-size:13px">No cases in this period</div>
            <template v-else>
              <div class="cc-top">
                <div class="cc-rate">{{ caseData.completion_rate ?? 0 }}<span>%</span></div>
                <div class="cc-note">of the cases opened in this period are <strong>resolved or closed</strong>.</div>
              </div>
              <div class="cc-bar" :title="`${caseData.completed} resolved or closed, ${caseData.pending} open`">
                <div class="cc-done" :style="{ width: (caseData.completion_rate ?? 0) + '%' }"></div>
              </div>
              <div class="cc-legend">
                <span><i style="background:var(--moss)"></i> Resolved / closed <strong>{{ caseData.completed }}</strong></span>
                <span><i style="background:var(--amber)"></i> Open / pending <strong>{{ caseData.pending }}</strong></span>
              </div>

              <div class="cc-sub">Pending cases, by how long they have been open</div>
              <div class="cc-aging">
                <div v-for="(b, i) in caseData.pending_aging || []" :key="b.label" class="cc-age" :class="{ warn: i >= 2 && b.count > 0 }">
                  <div class="cc-age-num">{{ b.count }}</div>
                  <div class="cc-age-label">{{ b.label }}</div>
                </div>
              </div>
            </template>
          </div>
        </div>

        <!-- Cases by Status -->
        <div class="icard">
          <div class="icard-header"><span class="icard-title">Cases by Status</span></div>
          <div class="icard-body">
            <div v-if="!caseData.by_status?.length" style="text-align:center;color:var(--fog);font-size:13px">No data</div>
            <div v-for="item in caseData.by_status" :key="item.status" style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--cloud)">
              <span class="ibadge" :class="'ibadge-' + item.status">{{ toTitleCase(item.status) }}</span>
              <span style="font-size:13px;font-weight:600;color:var(--ink)">{{ item.count }} <span style="font-weight:400;color:var(--stone);font-size:11.5px">· {{ pct(item.count, caseData.total) }}%</span></span>
            </div>
          </div>
        </div>

      </div>

      <!-- Case Resolutions: the cases that were resolved or closed -->
      <div v-if="shows('cases')" class="icard" style="margin-bottom:16px">
        <div class="icard-header">
          <span class="icard-title">Case Resolutions</span>
          <span class="ibadge" style="background:var(--mist);color:var(--moss)">Avg. {{ caseData.avg_days_to_close ?? 0 }} days to close</span>
        </div>
        <div class="ts">
          <table class="itable gcu-num cr-table">
            <thead>
              <tr>
                <th>Case No.</th>
                <th>Concern</th>
                <th>College</th>
                <th>Opened</th>
                <th>Closed</th>
                <th class="num">Days to Close</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in caseData.resolutions || []" :key="row.id">
                <td style="font-family:var(--mono)">{{ row.case_number }}</td>
                <td>{{ toTitleCase(row.case_type) }}</td>
                <td>{{ collegeShort(row.college) }}</td>
                <td>{{ prettyDate(row.opened_date) || '-' }}</td>
                <td>{{ prettyDate(row.closed_date) || '-' }}</td>
                <td class="num">{{ row.days_to_close ?? '-' }}</td>
                <td><span class="ibadge" :class="'ibadge-' + row.status">{{ toTitleCase(row.status) }}</span></td>
                <td style="text-align:right"><button class="ibtn ibtn-o ibtn-sm" @click="$router.push({ name: 'student-show', params: { id: row.student_id }, query: { ctx: 'reports' } })">View</button></td>
              </tr>
              <tr v-if="!caseData.resolutions?.length">
                <td colspan="8" style="text-align:center;color:var(--fog)">No cases were resolved or closed in this period</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Recurring Concerns -->
      <div v-if="shows('referrals')" class="icard" style="margin-bottom:16px">
        <div class="icard-header">
          <span class="icard-title">Recurring Concerns</span>
          <span class="ibadge" style="background:var(--blue-lt);color:var(--blue)">{{ recurringData.total_recurring_students ?? 0 }} students with recurring referrals</span>
        </div>
        <div class="ts">
          <table class="itable gcu-num">
            <thead>
              <tr>
                <th>Referral Type</th>
                <th>Total Referrals</th>
                <th>Distinct Students</th>
                <th>Recurring Students</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in recurringData.by_type" :key="row.referral_type">
                <td>{{ toTitleCase(row.referral_type) }}</td>
                <td>{{ row.total_referrals }}</td>
                <td>{{ row.distinct_students }}</td>
                <td>
                  <span class="ibadge" :style="row.recurring_students > 0 ? 'background:var(--blue-lt);color:var(--blue)' : 'background:var(--cloud);color:var(--stone)'">
                    {{ row.recurring_students }}
                  </span>
                </td>
              </tr>
              <tr v-if="!recurringData.by_type?.length">
                <td colspan="4" style="text-align:center;color:var(--fog)">No data</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="recurringData.top_recurring_students?.length" class="narrow" style="padding:14px 0;border-top:1px solid var(--cloud)">
          <div style="font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:8px">Most Frequently Referred Students</div>
          <div style="display:flex;flex-direction:column;gap:6px">
            <div
              v-for="s in recurringData.top_recurring_students"
              :key="s.id"
              style="display:flex;justify-content:space-between;align-items:center;gap:10px;font-size:12.5px;color:var(--ink)"
            >
              <span style="display:flex;align-items:center;gap:10px">
                <button class="ibtn ibtn-o ibtn-sm" @click="$router.push({ name: 'student-show', params: { id: s.id }, query: { ctx: 'reports' } })">View</button>
                <span>{{ s.last_name }}, {{ s.first_name }} <span style="color:var(--fog);font-family:var(--mono)">({{ s.student_id }})</span></span>
              </span>
              <span style="color:var(--stone)">{{ s.referrals_count }} referrals</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Appointments by Type (Appointment Reports only - the year-end report keeps to the summary) -->
      <div v-if="reportType === 'appointments'" class="icard" style="margin-bottom:16px">
        <div class="icard-header"><span class="icard-title">Appointments by Type</span></div>
        <div class="icard-body"><div class="narrow">
          <div v-if="!apptData.by_type?.length" style="text-align:center;color:var(--fog);font-size:13px">No data</div>
          <div v-for="item in apptData.by_type" :key="item.appointment_type" style="margin-bottom:11px">
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--slate);margin-bottom:5px">
              <span>{{ toTitleCase(item.appointment_type) }}</span>
              <span style="color:var(--stone)">{{ item.count }} · {{ pct(item.count, apptData.total) }}%</span>
            </div>
            <div style="background:var(--cloud);border-radius:4px;height:7px;overflow:hidden">
              <div :style="{ width: pct(item.count, apptData.total) + '%', background: 'var(--moss)', height: '100%', borderRadius: '4px' }"></div>
            </div>
          </div>
        </div></div>
      </div>

      <!-- Appointments Summary -->
      <div v-if="shows('appointments')" class="icard">
        <div class="icard-header"><span class="icard-title">Appointment Summary</span></div>
        <div class="ts">
          <table class="itable gcu-num">
            <thead>
              <tr>
                <th>Unit</th>
                <th>Total</th>
                <th>Pending</th>
                <th>Confirmed</th>
                <th>Completed</th>
                <th>Cancelled</th>
                <th>No Show</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in apptSummary" :key="row.unit">
                <td><span class="ibadge" :class="'unit-' + row.unit.toLowerCase()">{{ row.unit }}</span></td>
                <td style="font-weight:600">{{ row.total }}</td>
                <td>{{ row.pending }}</td>
                <td>{{ row.confirmed }}</td>
                <td>{{ row.completed }}</td>
                <td>{{ row.cancelled }}</td>
                <td>{{ row.no_show }}</td>
              </tr>
              <tr v-if="!apptSummary.length">
                <td colspan="7" style="text-align:center;color:var(--fog)">No data</td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Attendance: attended against no-show, for appointments that reached their day -->
        <div v-if="attendance && (attendance.attended + attendance.no_show + attendance.cancelled) > 0" class="narrow att-wrap">
          <div class="att-rate">
            <div class="att-num">{{ attendance.rate ?? '-' }}<span v-if="attendance.rate !== null">%</span></div>
            <div class="att-label">Attendance rate</div>
          </div>
          <div class="att-detail">
            <div class="cc-bar"><div class="cc-done" :style="{ width: (attendance.rate ?? 0) + '%' }"></div></div>
            <div class="cc-legend">
              <span><i style="background:var(--moss)"></i> Attended <strong>{{ attendance.attended }}</strong></span>
              <span><i style="background:var(--amber)"></i> No-show <strong>{{ attendance.no_show }}</strong></span>
              <span><i style="background:var(--silver)"></i> Cancelled <strong>{{ attendance.cancelled }}</strong></span>
              <span><i style="background:var(--blue)"></i> Upcoming <strong>{{ attendance.upcoming }}</strong></span>
            </div>
            <div class="att-note">The rate compares attended with no-show. Cancelled and upcoming appointments are not counted in it.</div>
          </div>
        </div>
      </div>

    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, inject } from 'vue';
import axios from 'axios';
import { reportAPI } from '../../api/index';
import { useAuthStore } from '../../stores/auth';
import { toTitleCase, localDateStr } from '../../utils/validators';

const toast = inject('toast');
const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } };
}

const loading  = ref(true);
const loadError = ref(false);
const dateFrom = ref('');
const dateTo   = ref('');

// ---- Unit ----
// GCU, TMDU and SDU each have their own report. Admin picks; unit staff are
// fixed to their unit (the server enforces this too).
const UNITS = ['GCU', 'TMDU', 'SDU'];
const UNIT_NAMES = {
  GCU:  'Guidance and Counseling Unit',
  TMDU: 'Testing and Measurement Development Unit',
  SDU:  'Student Discipline Unit',
};
const UNIT_BLURB = {
  GCU:  'report on referrals received, services rendered, appointments, and case outcomes.',
  TMDU: 'report on referrals received, services rendered, appointments, and case outcomes.',
  SDU:  'report on complaints received, by misconduct, college and department.',
};
// ---- Kind of report ----
// "Year-end" is the unit's full accomplishment report. The other three are
// its referral, case and appointment parts on their own - on the page and in
// the PDF / Excel export.
const REPORT_TYPES = [
  { value: 'year_end',     label: 'Year-end Reports',    file: 'Report',             blurb: 'year-end report on referrals received, services rendered, appointments, and case outcomes.' },
  { value: 'referrals',    label: 'Referrals Reports',   file: 'Referrals-Report',   blurb: 'referrals received, by type, college, source and month, and recurring concerns.' },
  { value: 'cases',        label: 'Case Reports',        file: 'Case-Report',        blurb: 'cases by status, case completion, pending cases, and resolved cases.' },
  { value: 'appointments', label: 'Appointment Reports', file: 'Appointment-Report', blurb: 'appointments by status and type, and attendance.' },
];
const reportType = ref('year_end');
const currentReport = computed(() => {
  const t = REPORT_TYPES.find(x => x.value === reportType.value);
  // SDU's year-end report is its complaints.
  return unit.value === 'SDU' && t.value === 'year_end' ? { ...t, blurb: UNIT_BLURB.SDU } : t;
});
// A section shows in the year-end report and in the report it belongs to.
function shows(section) {
  return reportType.value === 'year_end' || reportType.value === section;
}

const auth = useAuthStore();
const ownUnit = { gcu_staff: 'GCU', tmdu_staff: 'TMDU', sdu_head: 'SDU' }[auth.user?.role];
const canPickUnit = !ownUnit;
const unit = ref(ownUnit || 'GCU');

function selectUnit(u) {
  if (unit.value === u) return;
  unit.value = u;
  fetchAll();
}

// ---- Academic year / term picker ----
// The academic years and their term dates come from Management > School
// Calendar. If none are set yet, fall back to Aug 1 - Jul 31 split into
// 1st Sem (Aug-Dec), 2nd Sem (Jan-May) and Midyear (Jun-Jul).
const AY_START_MONTH = 8;
const PERIODS = [
  { value: 'year',    label: 'Whole Academic Year', keys: ['year_start', 'year_end'],             from: [8, 1],         to: [7, 31, 'next'] },
  { value: 'first',   label: '1st Semester',        keys: ['first_sem_start', 'first_sem_end'],   from: [8, 1],         to: [12, 31],        code: 1 },
  { value: 'second',  label: '2nd Semester',        keys: ['second_sem_start', 'second_sem_end'], from: [1, 1, 'next'], to: [5, 31, 'next'], code: 2 },
  { value: 'midyear', label: 'Midyear',             keys: ['midyear_start', 'midyear_end'],       from: [6, 1, 'next'], to: [7, 31, 'next'], code: 3 },
];

const ayRows = ref([]);
const today = new Date();
const todayStr = localDateStr(today);

const fallbackAY = today.getMonth() + 1 >= AY_START_MONTH ? today.getFullYear() : today.getFullYear() - 1;

// The A.Y. whose dates contain today; else the latest one that has started; else the fallback.
const currentAY = computed(() => {
  if (!ayRows.value.length) return fallbackAY;
  const within = ayRows.value.find(r => todayStr >= r.year_start && todayStr <= r.year_end);
  if (within) return within.start_year;
  const started = ayRows.value.filter(r => r.year_start <= todayStr).map(r => r.start_year);
  return started.length ? Math.max(...started) : Math.max(...ayRows.value.map(r => r.start_year));
});

// Newest first.
const academicYears = computed(() =>
  ayRows.value.length
    ? ayRows.value.map(r => r.start_year).sort((x, y) => y - x)
    : Array.from({ length: 5 }, (_, i) => fallbackAY - i)
);

function periodDates(ay, p) {
  const row = ayRows.value.find(r => r.start_year === ay);
  if (row) return [String(row[p.keys[0]]).slice(0, 10), String(row[p.keys[1]]).slice(0, 10)];
  const iso = ([m, d, next]) => `${next ? ay + 1 : ay}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
  return [iso(p.from), iso(p.to)];
}

// Open on the term that today falls in.
function currentTerm() {
  return PERIODS.slice(1).find(p => {
    const [from, to] = periodDates(currentAY.value, p);
    return todayStr >= from && todayStr <= to;
  }) || PERIODS[0];
}

const academicYear = ref(fallbackAY);
const period       = ref(currentTerm().value);

const periodLabel = computed(() => {
  const p = PERIODS.find(x => x.value === period.value);
  if (!p) return '';
  // Short term code, e.g. "26-1" = A.Y. 2026-2027, 1st Semester.
  const code = p.code ? `${String(academicYear.value).slice(-2)}-${p.code} · ` : '';
  return `${code}${p.label}, A.Y. ${academicYear.value}–${academicYear.value + 1}`;
});

function prettyDate(str) {
  if (!str) return '';
  const [y, m, d] = str.split('-').map(Number);
  return new Date(y, m - 1, d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

const shownLabel = computed(() => {
  const range = dateFrom.value || dateTo.value
    ? `${prettyDate(dateFrom.value) || 'the beginning'} – ${prettyDate(dateTo.value) || 'present'}`
    : 'All records';
  return periodLabel.value ? `${periodLabel.value} (${range})` : range;
});

// Fill the date boxes from the chosen term and reload. Custom Range leaves
// the dates as they are for the user to edit, then Generate.
function applyPeriod() {
  const p = PERIODS.find(x => x.value === period.value);
  if (!p) return;
  [dateFrom.value, dateTo.value] = periodDates(academicYear.value, p);
  fetchAll();
}
const exporting = ref('');
const exportMenuOpen = ref(false);
const exportMenuEl   = ref(null);

function exportAs(format) {
  exportMenuOpen.value = false;
  exportReport(format);
}

// Clicking anywhere outside the menu closes it.
function closeExportMenu(e) {
  if (exportMenuOpen.value && exportMenuEl.value && !exportMenuEl.value.contains(e.target)) {
    exportMenuOpen.value = false;
  }
}
onMounted(() => document.addEventListener('click', closeExportMenu));
onBeforeUnmount(() => document.removeEventListener('click', closeExportMenu));

async function exportReport(format) {
  exporting.value = format;
  try {
    const res = await axios.get(`${API_BASE}/reports/export/${format}`, {
      ...authHeaders(),
      params: { unit: unit.value, report: reportType.value, date_from: dateFrom.value, date_to: dateTo.value, period_label: periodLabel.value },
      responseType: 'blob',
    });
    const ext = format === 'pdf' ? 'pdf' : 'xlsx';
    const blobUrl = window.URL.createObjectURL(new Blob([res.data]));
    const link = document.createElement('a');
    link.href = blobUrl;
    link.download = `iCARE-${unit.value}-${currentReport.value.file}-${localDateStr()}.${ext}`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(blobUrl);
  } catch (e) {
    toast?.error('Failed to export report.');
  } finally {
    exporting.value = '';
  }
}

const referralData  = ref({});
const caseData      = ref({});
const apptData      = ref({});
const dashData      = ref({});
const recurringData = ref({});
const servicesData  = ref([]);
const gcuData       = ref({});

// The page lists only courses with students unless asked; the Excel always lists every course.
const showAllCourses = ref(false);
function visibleGroups(groups) {
  if (showAllCourses.value) return groups || [];
  return (groups || [])
    .map(g => ({ ...g, rows: (g.rows || []).filter(r => r.total > 0) }))
    .filter(g => g.rows.length);
}

// Any list split in two for the two-column layout.
function halvesOf(list) {
  const mid = Math.ceil((list || []).length / 2);
  return [(list || []).slice(0, mid), (list || []).slice(mid)];
}

// Services list split in two columns on the page.
const serviceHalves = computed(() => {
  const list = gcuData.value.services || [];
  const mid = Math.ceil(list.length / 2);
  return [list.slice(0, mid), list.slice(mid)];
});

// College groups -> their course rows, for totals.
function flatRows(groups) {
  return (groups || []).flatMap(g => g.rows || []);
}

function sumOf(rows, key) {
  return (rows || []).reduce((n, r) => n + (Number(r[key]) || 0), 0);
}
const sduData       = ref({});

const sduStats = computed(() => {
  const look = {
    pending:      { iconBg: 'var(--amber-lt)', iconColor: 'var(--amber)', icon: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>' },
    under_review: { iconBg: 'var(--blue-lt)',  iconColor: 'var(--blue)',  icon: '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>' },
    resolved:     { iconBg: 'var(--mist)',     iconColor: 'var(--moss)',  icon: '<polyline points="20 6 9 17 4 12"/>' },
  };
  const other = { iconBg: 'var(--cloud)', iconColor: 'var(--stone)', icon: '<circle cx="12" cy="12" r="10"/>' };
  return [
    { label: 'Total Complaints', value: sduData.value.total ?? 0, iconBg: 'var(--mist)', iconColor: 'var(--moss)', icon: '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>' },
    ...(sduData.value.by_status || []).map(row => ({ label: row.label, value: row.count, ...(look[row.status] || other) })),
  ];
});

// First row with a count - lists arrive ranked highest first.
function topOf(rows) {
  return (rows || []).find(r => r.count > 0) || null;
}

const collegeRows = computed(() => referralData.value.by_student_college || []);
const sourceRows  = computed(() => referralData.value.by_source || []);
const attendance  = computed(() => apptData.value.attendance || null);
const topCollege  = computed(() => collegeRows.value.find(c => c.count > 0) || null);
// "College of Nursing (CN)" -> "CN"
function collegeShort(name) {
  return name.match(/\(([^)]+)\)\s*$/)?.[1] || name;
}

const summaryStats = computed(() => allStats.value.filter(st => (!st.only || st.only === unit.value) && shows(st.group)));

const allStats = computed(() => [
  { label: 'Total Referrals', group: 'referrals', value: referralData.value.total    ?? 0, iconBg: 'var(--mist)',      iconColor: 'var(--moss)',   icon: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>' },
  { label: 'Total Cases', group: 'cases', value: caseData.value.total        ?? 0, iconBg: 'var(--blue-lt)',   iconColor: 'var(--blue)',   icon: '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>' },
  { label: 'Pending Cases', group: 'cases', value: caseData.value.pending      ?? 0, iconBg: 'var(--amber-lt)',  iconColor: 'var(--amber)',  icon: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>' },
  { label: 'Resolved / Closed', group: 'cases', value: caseData.value.completed ?? 0, iconBg: 'var(--mist)', iconColor: 'var(--moss)', icon: '<polyline points="20 6 9 17 4 12"/>' },
  { label: 'Case Completion', group: 'cases', value: (caseData.value.completion_rate ?? 0) + '%', iconBg: 'var(--mist)', iconColor: 'var(--moss)', icon: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>' },
  { label: 'Avg. Days to Close', group: 'cases', value: caseData.value.avg_days_to_close ?? 0, iconBg: 'var(--mist)', iconColor: 'var(--moss)', icon: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>' },
  { label: 'Total Appointments', group: 'appointments', value: apptData.value.total        ?? 0, iconBg: 'var(--amber-lt)', iconColor: 'var(--amber)',  icon: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>' },
  { label: 'Referred to TMDU', group: 'cases', value: caseData.value.referred_tmdu ?? 0, only: 'GCU', iconBg: 'var(--purple-lt)', iconColor: 'var(--purple)', icon: '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>' },
]);

const maxMonthlyCount = computed(() => {
  const counts = referralData.value.monthly_trend?.map(m => m.count) || [1];
  return Math.max(...counts, 1);
});

const apptSummary = computed(() => {
  const byUnit = apptData.value.by_unit || [];
  const byUnitStatus = apptData.value.by_unit_status || [];
  return ['GCU', 'SDU', 'TMDU'].map(unit => {
    const unitData = byUnit.find(u => u.unit === unit);
    const count = status => byUnitStatus.find(s => s.unit === unit && s.status === status)?.count || 0;
    return {
      unit,
      total:     unitData?.count || 0,
      pending:   count('pending'),
      confirmed: count('confirmed'),
      completed: count('completed'),
      cancelled: count('cancelled'),
      no_show:   count('no_show'),
    };
  }).filter(r => r.total > 0);
});

async function fetchAll() {
  loading.value = true;
  loadError.value = false;
  try {
    const params = { unit: unit.value, date_from: dateFrom.value, date_to: dateTo.value };
    // SDU's year-end report is its complaints; its other reports use the same
    // referral / case / appointment figures as the other units.
    const sdu = unit.value === 'SDU' ? reportAPI.complaints(params) : Promise.resolve({ data: {} });
    // GCU's printed-report sections load alongside the other figures.
    const gcu = unit.value === 'GCU' ? reportAPI.gcuAccomplishment(params) : Promise.resolve({ data: {} });
    const [r, c, a, d, rc, sv, g, sd] = await Promise.all([
      reportAPI.referrals(params),
      reportAPI.cases(params),
      reportAPI.appointments(params),
      reportAPI.dashboard(),
      reportAPI.recurringConcerns(params),
      reportAPI.services(params),
      gcu,
      sdu,
    ]);
    sduData.value       = sd.data;
    referralData.value  = r.data;
    caseData.value      = c.data;
    apptData.value      = a.data;
    dashData.value      = d.data;
    recurringData.value = rc.data;
    servicesData.value  = sv.data;
    gcuData.value       = g.data;
  } catch (e) {
    console.error(e);
    // Say so, rather than leaving a page of zeros that reads as "no records".
    loadError.value = e.response?.status === 403 ? 'forbidden' : 'failed';
  } finally {
    loading.value = false;
  }
}

function pct(count, total) {
  if (!total) return 0;
  return Math.round(count / total * 100);
}

function monthLabel(month) {
  return ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][month - 1] || month;
}


onMounted(async () => {
  try {
    const res = await axios.get(`${API_BASE}/management/academic-years`, authHeaders());
    ayRows.value = Array.isArray(res.data) ? res.data : [];
  } catch (e) {
    ayRows.value = []; // fall back to the default calendar
  }
  academicYear.value = currentAY.value;
  period.value = currentTerm().value;
  applyPeriod();
});
</script>

<style scoped>
/* Kind-of-report tabs, under the unit buttons */
.rt-tabs { display: flex; gap: 4px; margin-bottom: 16px; border-bottom: 1px solid var(--silver); overflow-x: auto; scrollbar-width: none; }
.rt-tabs::-webkit-scrollbar { display: none; }
.rt-tab {
  flex: none; padding: 8px 14px; border: none; background: none; cursor: pointer;
  font-family: var(--font); font-size: 13px; font-weight: 500; color: var(--stone);
  border-bottom: 2px solid transparent; margin-bottom: -1px; white-space: nowrap;
  transition: color .12s, border-color .12s;
}
.rt-tab:hover { color: var(--ink); }
.rt-tab.active { color: var(--moss); font-weight: 700; border-bottom-color: var(--moss); }

/* Period filter - same pattern as the Audit Trail filter bar. */
.rp-bar { flex-wrap: nowrap; }
.rp-field { display: flex; align-items: center; gap: 6px; flex: 0 1 auto; min-width: 0; }
.rp-field > span { font-size: 12px; color: var(--stone); font-weight: 500; white-space: nowrap; }
.rp-field .fsm { min-width: 0; }
.rp-range {
  flex: 0 1 auto; min-width: 0; display: flex; align-items: center; gap: 4px;
  padding: 0 8px; height: 36px; background: #fff;
  border: 1.5px solid var(--silver); border-radius: var(--r-sm);
}
.rp-range:focus-within { border-color: var(--moss); }
.rp-range.locked { background: var(--snow); }
.rp-range input {
  border: none; outline: none; background: none; min-width: 0; width: 118px;
  font-family: var(--font); font-size: 12.5px; color: var(--ink);
}
.rp-range input:disabled { color: var(--stone); cursor: default; }
.rp-range span { color: var(--fog); font-size: 12px; }
.rp-btn { flex: none; white-space: nowrap; }
.rp-export { position: relative; flex: none; margin-left: auto; }

.export-menu {
  position: absolute; right: 0; top: calc(100% + 6px); z-index: 30;
  min-width: 180px; padding: 5px;
  background: #fff; border: 1px solid var(--cloud); border-radius: var(--r-sm);
  box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
}
.export-menu button {
  display: block; width: 100%; padding: 7px 10px; border: none; background: none;
  border-radius: 6px; font-family: var(--font); text-align: left; cursor: pointer;
}
.export-menu button:hover { background: var(--mist); }
.export-menu strong { display: block; font-size: 12.5px; color: var(--ink); font-weight: 600; }
.export-menu small { display: block; font-size: 11px; color: var(--stone); }

/* Phones: one control per line. */
@media (max-width: 860px) {
  .rp-bar { flex-wrap: wrap; }
  .rp-field, .rp-range { flex: 1 1 100%; }
  .rp-field .fsm { flex: 1; }
  .rp-range input { flex: 1; width: auto; }
  .rp-btn { flex: 1 1 auto; justify-content: center; }
  .rp-export { margin-left: 0; flex: 1 1 auto; }
  .rp-export > .ibtn { width: 100%; justify-content: center; }
}
/* Case Completion and Attendance */
.cc-top { display: flex; align-items: center; gap: 14px; margin-bottom: 12px; }
.cc-rate { font-family: var(--serif); font-style: italic; font-size: 40px; line-height: 1; color: var(--forest); font-weight: 700; }
.cc-rate span { font-size: 20px; margin-left: 2px; }
.cc-note { font-size: 12.5px; color: var(--stone); line-height: 1.45; }
.cc-note strong { color: var(--ink); }
.cc-bar { height: 10px; border-radius: 6px; background: var(--amber-lt); overflow: hidden; }
.cc-done { height: 100%; background: var(--moss); border-radius: 6px; transition: width .5s; }
.cc-legend { display: flex; flex-wrap: wrap; gap: 6px 16px; margin-top: 8px; font-size: 12px; color: var(--stone); }
.cc-legend i { display: inline-block; width: 9px; height: 9px; border-radius: 2px; margin-right: 5px; }
.cc-legend strong { color: var(--ink); margin-left: 2px; }
.cc-sub { margin: 18px 0 8px; font-size: 10px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; color: var(--fog); }
.cc-aging { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
.cc-age { background: var(--snow); border: 1px solid var(--cloud); border-radius: var(--r-sm); padding: 8px 6px; text-align: center; }
.cc-age.warn { background: var(--amber-lt); border-color: var(--amber); }
.cc-age-num { font-size: 18px; font-weight: 700; color: var(--ink); line-height: 1.1; }
.cc-age-label { font-size: 10.5px; color: var(--stone); margin-top: 3px; }
.cr-table { width: 92%; }
.att-wrap { display: flex; align-items: center; gap: 22px; padding: 14px 0 16px; border-top: 1px solid var(--cloud); }
.att-rate { flex: none; text-align: center; min-width: 96px; }
.att-num { font-family: var(--serif); font-style: italic; font-size: 32px; line-height: 1; color: var(--forest); font-weight: 700; }
.att-num span { font-size: 16px; margin-left: 2px; }
.att-label { font-size: 10px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; color: var(--fog); margin-top: 5px; }
.att-detail { flex: 1; min-width: 0; }
.att-note { font-size: 11px; color: var(--fog); margin-top: 6px; }
@media (max-width: 860px) {
  .cc-aging { grid-template-columns: repeat(2, 1fr); }
  .att-wrap { flex-direction: column; align-items: stretch; }
  .cr-table { width: 100%; }
}

/* Compact tables for the GCU "by the Numbers" sections, centred in the card
   so the numbers sit near the course names instead of at the far edge. */
.gcu-num{font-size:12px;width:70%;margin:6px auto 10px}
.gcu-num thead th{padding:6px 10px;font-size:10px;text-align:left}
.gcu-num thead th.num{text-align:center}
.gcu-num thead tr:last-child th.num{width:110px}
.gcu-num tbody td{padding:5px 10px}
.gcu-num td.num{text-align:center;width:90px}
.gcu-num td.col{font-weight:600;vertical-align:top;border-right:1px solid var(--cloud)}
.gcu-num td.tot{font-weight:600}
.gcu-num tr.sum td{font-weight:700;border-top:1px solid var(--silver)}

/* Services: two columns with a fading divider and a small mark in the middle. */
.svc-wrap{padding:14px 22px 16px}
/* Centred block, same width as the tables above. */
.narrow{width:70%;margin-left:auto;margin-right:auto}
.svc-grid{display:grid;grid-template-columns:1fr 1fr;column-gap:56px;position:relative}
.svc-grid::before{content:'';position:absolute;top:4px;bottom:4px;left:50%;width:1px;background:linear-gradient(to bottom,transparent,var(--mint) 18%,var(--moss) 50%,var(--mint) 82%,transparent)}
.svc-grid::after{content:'';position:absolute;top:50%;left:50%;width:9px;height:9px;transform:translate(-50%,-50%) rotate(45deg);background:#fff;border:1.5px solid var(--moss);border-radius:2px}
.svc-head{display:flex;justify-content:space-between;font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);padding:0 4px 6px;border-bottom:1px solid var(--cloud)}
.svc-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:6px 4px;font-size:12px;color:var(--ink);border-bottom:1px dashed var(--cloud)}
.svc-count{min-width:30px;text-align:center;font-weight:700;color:var(--moss);background:var(--mist);border-radius:10px;padding:1px 8px}
.svc-count.zero{color:var(--fog);background:transparent;font-weight:500}
.svc-total{display:flex;justify-content:center;align-items:center;gap:10px;margin-top:12px;padding-top:10px;border-top:1px solid var(--silver);font-size:12.5px;color:var(--stone)}
.svc-total strong{font-size:15px;color:var(--forest)}
@media (max-width:860px){
  .gcu-num,.narrow{width:100%}
  .svc-grid{grid-template-columns:1fr}
  .svc-grid::before,.svc-grid::after{display:none}
  .svc-col-right{margin-top:10px}
}
</style>