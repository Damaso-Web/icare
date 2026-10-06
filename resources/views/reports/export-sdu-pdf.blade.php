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
    <h1>iCARE SDU Report</h1>
    <div class="meta">
        Generated {{ $generated_at }} &middot;
        @if (!empty($period_label)){{ $period_label }} &middot;@endif
        Date Range: {{ $date_from ?: 'All time' }} to {{ $date_to ?: 'present' }}
    </div>

    <table class="stat-grid">
        <tr>
            <td><div class="stat-label">Total Complaints</div><div class="stat-value">{{ $complaints['total'] }}</div></td>
            @foreach ($complaints['by_status'] as $row)
                <td><div class="stat-label">{{ $row['label'] }}</div><div class="stat-value">{{ $row['count'] }}</div></td>
            @endforeach
        </tr>
    </table>

    <h2>Complaints by Misconduct</h2>
    <table>
        <tr><th>Misconduct</th><th>Complaints</th></tr>
        @forelse ($complaints['by_misconduct'] as $row)
            <tr><td>{{ $row['label'] }}</td><td>{{ $row['count'] }}</td></tr>
        @empty
            <tr><td colspan="2">No data</td></tr>
        @endforelse
    </table>

    <h2>Complaints by College</h2>
    <table>
        <tr><th>College</th><th>Complaints</th></tr>
        @forelse ($complaints['by_college'] as $row)
            <tr><td>{{ $row['label'] }}</td><td>{{ $row['count'] }}</td></tr>
        @empty
            <tr><td colspan="2">No data</td></tr>
        @endforelse
    </table>

    <h2>Complaints by Department</h2>
    <table>
        <tr><th>Department</th><th>College</th><th>Complaints</th></tr>
        @forelse ($complaints['by_department'] as $row)
            <tr><td>{{ $row['label'] }}</td><td>{{ $row['college'] }}</td><td>{{ $row['count'] }}</td></tr>
        @empty
            <tr><td colspan="3">No data</td></tr>
        @endforelse
    </table>
</body>
</html>
