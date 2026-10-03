<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: sans-serif; font-size: 11px; color: #1a1a1a; }
    h1 { font-size: 18px; margin-bottom: 2px; }
    h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
    .meta { color: #666; font-size: 10px; margin-bottom: 14px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th, td { border: 1px solid #ddd; padding: 4px 7px; text-align: left; font-size: 10px; }
    th { background: #f3f3f3; }
    .stat-grid { width: 100%; margin-bottom: 10px; }
    .stat-grid td { border: none; padding: 4px 10px 4px 0; }
    .stat-label { color: #666; }
    .stat-value { font-weight: bold; font-size: 13px; }
</style>
</head>
<body>
    <h1>iCARE Reports &amp; Analytics</h1>
    <div class="meta">
        Generated {{ $generated_at }} &middot;
        Date Range: {{ $date_from ?: 'All time' }} to {{ $date_to ?: 'present' }}
    </div>

    <table class="stat-grid">
        <tr>
            <td><div class="stat-label">Total Referrals</div><div class="stat-value">{{ $referrals['total'] }}</div></td>
            <td><div class="stat-label">Total Cases</div><div class="stat-value">{{ $cases['total'] }}</div></td>
            <td><div class="stat-label">Pending Cases</div><div class="stat-value">{{ $cases['pending'] }}</div></td>
            <td><div class="stat-label">Avg. Days to Close</div><div class="stat-value">{{ $cases['avg_days_to_close'] }}</div></td>
        </tr>
        <tr>
            <td><div class="stat-label">Total Appointments</div><div class="stat-value">{{ $appointments['total'] }}</div></td>
            <td><div class="stat-label">TMDU Assessments</div><div class="stat-value">{{ $cases['referred_tmdu'] }}</div></td>
            <td><div class="stat-label">Students w/ Recurring Referrals</div><div class="stat-value">{{ $recurring['total_recurring_students'] }}</div></td>
            <td></td>
        </tr>
    </table>

    <h2>Referrals by Status</h2>
    <table>
        <tr><th>Status</th><th>Count</th></tr>
        @forelse ($referrals['by_status'] as $row)
            <tr><td>{{ $row['status'] }}</td><td>{{ $row['count'] }}</td></tr>
        @empty
            <tr><td colspan="2">No data</td></tr>
        @endforelse
    </table>

    <h2>Referrals by Type</h2>
    <table>
        <tr><th>Referral Type</th><th>Count</th></tr>
        @forelse ($referrals['by_type'] as $row)
            <tr><td>{{ $row['referral_type'] }}</td><td>{{ $row['count'] }}</td></tr>
        @empty
            <tr><td colspan="2">No data</td></tr>
        @endforelse
    </table>

    <h2>Cases by Status</h2>
    <table>
        <tr><th>Status</th><th>Count</th></tr>
        @forelse ($cases['by_status'] as $row)
            <tr><td>{{ $row['status'] }}</td><td>{{ $row['count'] }}</td></tr>
        @empty
            <tr><td colspan="2">No data</td></tr>
        @endforelse
    </table>

    <h2>Cases by Unit</h2>
    <table>
        <tr><th>Unit</th><th>Count</th></tr>
        @forelse ($cases['by_unit'] as $row)
            <tr><td>{{ $row['current_unit'] }}</td><td>{{ $row['count'] }}</td></tr>
        @empty
            <tr><td colspan="2">No data</td></tr>
        @endforelse
    </table>

    <h2>Appointments by Unit</h2>
    <table>
        <tr><th>Unit</th><th>Count</th></tr>
        @forelse ($appointments['by_unit'] as $row)
            <tr><td>{{ $row['unit'] }}</td><td>{{ $row['count'] }}</td></tr>
        @empty
            <tr><td colspan="2">No data</td></tr>
        @endforelse
    </table>

    <h2>Appointments by Status</h2>
    <table>
        <tr><th>Status</th><th>Count</th></tr>
        @forelse ($appointments['by_status'] as $row)
            <tr><td>{{ $row['status'] }}</td><td>{{ $row['count'] }}</td></tr>
        @empty
            <tr><td colspan="2">No data</td></tr>
        @endforelse
    </table>

    <h2>Recurring Concerns</h2>
    <table>
        <tr><th>Referral Type</th><th>Total Referrals</th><th>Distinct Students</th><th>Recurring Students</th></tr>
        @forelse ($recurring['by_type'] as $row)
            <tr>
                <td>{{ $row['referral_type'] }}</td>
                <td>{{ $row['total_referrals'] }}</td>
                <td>{{ $row['distinct_students'] }}</td>
                <td>{{ $row['recurring_students'] }}</td>
            </tr>
        @empty
            <tr><td colspan="4">No data</td></tr>
        @endforelse
    </table>
</body>
</html>