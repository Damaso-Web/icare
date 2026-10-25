// Keep-alive: pings the Render backend on a schedule so its free-tier service
// doesn't spin down (which caused slow first loads that looked like failed logins).
//
// Scheduled for Philippine active hours only (≈6am–midnight PHT) so the backend
// still sleeps overnight and stays within Render's free monthly hour allowance.
// Cron runs in UTC: hours 22,23,0–15 UTC == 06:00–23:59 Philippine time (UTC+8).
const BACKEND = 'https://icare-backend-5jwe.onrender.com/';

export default async () => {
  try {
    const res = await fetch(BACKEND, { method: 'GET' });
    return new Response(`pinged ${BACKEND} -> ${res.status}`, { status: 200 });
  } catch (err) {
    return new Response(`ping failed: ${err}`, { status: 200 });
  }
};

export const config = { schedule: '*/10 0-15,22,23 * * *' };
