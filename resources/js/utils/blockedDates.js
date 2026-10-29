import { ref } from 'vue';
import axios from 'axios';

// Dates blocked by active Holidays and Suspensions (School Calendar). Every appointment

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;

/** map = { 'YYYY-MM-DD': { type, name, label } }  */
export function useBlockedDates(role = 'staff') {
    const blocked = ref({});

    async function load() {
        const student = role === 'student';
        const token = localStorage.getItem(student ? 'student_token' : 'token');
        try {
            // From the start of last year, so a past holiday picked on a calendar still shows its reason.
            const y = new Date().getFullYear();
            const res = await axios.get(`${API_BASE}${student ? '/student' : ''}/calendar/blocked-dates`, {
                params: { from: `${y - 1}-01-01`, to: `${y + 2}-01-01` },
                headers: { Authorization: `Bearer ${token}` },
            });
            blocked.value = res.data?.blocked || {};
        } catch (e) {
            blocked.value = {};
        }
    }

    /** "This date is not available: Holiday: All Saints' Day." or '' */
    function messageFor(date) {
        if (!date) return '';
        const d = String(date).slice(0, 10);
        // Bug (10/30/2026): this only ever checked the School Calendar's
        // holidays/suspensions - a weekend date (which the backend has
        // always rejected via after_or_equal:today + an isWeekend check in
        // store()/scheduleForStudent()/rescheduleNow()) was never flagged
        // here, so the date picker let staff pick a Saturday/Sunday with no
        // warning at all, only to have the save fail server-side. A plain
        // <input type="date"> can't disable specific weekdays on its own,
        // so this is checked in JS instead. Parsed at local noon so the
        // day-of-week doesn't shift back a day for a timezone behind UTC.
        const day = new Date(`${d}T12:00:00`).getDay();
        if (day === 0 || day === 6) {
            return 'Appointments can only be scheduled Monday through Friday.';
        }
        const hit = blocked.value[d];
        return hit ? `This date is not available: ${hit.label}.` : '';
    }

    return { blocked, load, messageFor };
}

// B628 (10/30/2026): office hours (8:00 AM - 4:00 PM) were only ever
// enforced server-side, and only on scheduleForStudent()/rescheduleNow() -
// store() (used by e.g. the Follow-up Session form) didn't check this at
// all. None of the date/time pickers warned about it client-side either, so
// staff could type/pick a start or end time outside this window, submit,
// and only find out from a 422 error (or, via store(), have it silently
// accepted). Exported standalone (not part of useBlockedDates) since it
// only needs the two time strings, not the loaded holiday map.
/** "Appointments can only be scheduled between 8:00 AM and 4:00 PM." or '' */
export function officeHoursMessage(startTime, endTime) {
    if (!startTime || !endTime) return '';
    if (startTime < '08:00' || endTime > '16:00') {
        return 'Appointments can only be scheduled between 8:00 AM and 4:00 PM.';
    }
    return '';
}