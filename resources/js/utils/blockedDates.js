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
        const hit = date ? blocked.value[String(date).slice(0, 10)] : null;
        return hit ? `This date is not available: ${hit.label}.` : '';
    }

    return { blocked, load, messageFor };
}