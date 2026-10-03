<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: sans-serif; font-size: 10px; color: #1a1a1a; }
    h1 { font-size: 16px; margin-bottom: 2px; }
    .meta { color: #666; font-size: 10px; margin-bottom: 14px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ddd; padding: 3px 6px; text-align: left; font-size: 9px; word-wrap: break-word; }
    th { background: #f3f3f3; }
</style>
</head>
<body>
    <h1>iCARE Audit Trail</h1>
    <div class="meta">Generated {{ $generated_at }} &middot; {{ count($logs) }} record(s)</div>

    <table>
        <tr>
            <th style="width:13%">Timestamp</th>
            <th style="width:12%">User</th>
            <th style="width:10%">Role</th>
            <th style="width:10%">Action</th>
            <th style="width:45%">Description</th>
            <th style="width:10%">IP Address</th>
        </tr>
        @forelse ($logs as $log)
            <tr>
                <td>{{ $log->created_at }}</td>
                <td>{{ $log->user_name }}</td>
                <td>{{ $log->user_role }}</td>
                <td>{{ $log->action }}</td>
                <td>{{ $log->description }}</td>
                <td>{{ $log->ip_address }}</td>
            </tr>
        @empty
            <tr><td colspan="6">No audit logs found.</td></tr>
        @endforelse
    </table>
</body>
</html>