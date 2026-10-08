{{--
    Audit Trail PDF. The rows are printed as a series of small tables
    (one header each) instead of one long table: Dompdf lays a long table
    out in one go, which took ~270 MB for 1,000 rows - over the server's
    128 MB PHP limit, so the export failed live.
--}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 16mm 14mm 16mm 14mm; }
    body { font-family: Helvetica, sans-serif; font-size: 8pt; color: #1d1d1d; }
    .letterhead { text-align: center; border-bottom: 2px solid #1f5c3a; padding-bottom: 7px; margin-bottom: 10px; }
    .letterhead .uni { font-size: 13pt; font-weight: bold; letter-spacing: .5px; }
    .letterhead .off { font-size: 9pt; margin-top: 2px; }
    .title { text-align: center; font-size: 11.5pt; font-weight: bold; color: #1f5c3a; }
    .meta { text-align: center; color: #555; font-size: 8pt; margin: 3px 0 12px; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { border: .6px solid #b9c2bc; padding: 2.5px 5px; text-align: left; vertical-align: top; word-wrap: break-word; }
    th { background: #1f5c3a; color: #fff; font-size: 7.6pt; }
    tr.alt td { background: #f5f8f6; }
    td.nw { white-space: nowrap; }
    table.cont { margin-top: -.6px; }
    /* Later pieces keep an invisible heading row only to fix their column widths. */
    tr.sizer th { padding: 0; border: none; height: 0; font-size: 0; line-height: 0; background: none; }
</style>
</head>
<body>
    <div class="letterhead">
        <div class="uni">BENGUET STATE UNIVERSITY</div>
        <div class="off">Office of Student Services</div>
    </div>
    <div class="title">iCARE AUDIT TRAIL</div>
    <div class="meta">Generated {{ $generated_at }} &middot; {{ count($logs) }} record(s)</div>

    @forelse ($logs->chunk(40) as $chunk)
        {{-- Column widths are fixed, so the pieces line up as one table; the heading row prints once. --}}
        <table class="{{ $loop->first ? '' : 'cont' }}">
            <tr class="{{ $loop->first ? '' : 'sizer' }}">
                <th style="width:12%">Timestamp</th>
                <th style="width:13%">User</th>
                <th style="width:9%">Role</th>
                <th style="width:12%">Action</th>
                <th style="width:44%">Description</th>
                <th style="width:10%">IP Address</th>
            </tr>
            @foreach ($chunk->values() as $i => $log)
                <tr class="{{ $i % 2 ? 'alt' : '' }}">
                    <td class="nw">{{ $log->created_at?->format('M j, Y g:i A') }}</td>
                    <td>{{ $log->user_name }}</td>
                    <td>{{ $log->user_role }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->description }}</td>
                    <td>{{ $log->ip_address }}</td>
                </tr>
            @endforeach
        </table>
    @empty
        <table><tr><td style="text-align:center;color:#888">No audit logs found.</td></tr></table>
    @endforelse
</body>
</html>
