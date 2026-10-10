{{--
    Report PDF. Printed from the same blocks as the Excel workbook
    (App\Support\ReportWorkbook::$doc), so both carry the same content;
    this template only decides how it looks on paper.
--}}
@php
    $fmt = fn($v) => is_float($v) && floor($v) != $v ? number_format($v, 1) : $v;
    // Reference numbers and dates read badly when they wrap.
    $noWrap = fn($v) => is_string($v) && (preg_match('/^[A-Z]{2,4}-\d{4}-\d+$/', $v) || preg_match('/^[A-Z][a-z]{2} \d{1,2}, \d{4}$/', $v));
    $isFirst = true;
    $keepOpen = false;
    // Rows in the next table after block $at; a heading is kept on the same
    // page as its table only when the table is short enough to move with it.
    $nextRows = function (int $at) use ($doc) {
        for ($i = $at + 1; $i < count($doc); $i++) {
            $b = $doc[$i];
            if (in_array($b['type'], ['sheet', 'section'], true)) return 0;
            if ($b['type'] === 'table' || $b['type'] === 'services') return count($b['rows']);
            if (in_array($b['type'], ['grouped', 'matrix'], true)) return collect($b['groups'])->sum(fn($g) => count($g['rows'] ?? []));
        }
        return 0;
    };
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 17mm 16mm 16mm 16mm; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.2pt; color: #1d1d1d; line-height: 1.25; }

    /* Letterhead (first page) and the running header on later pages */
    .letterhead { text-align: center; padding-bottom: 10px; border-bottom: 2px solid #1f5c3a; margin-bottom: 4px; }
    .letterhead .uni  { font-size: 14pt; font-weight: bold; letter-spacing: .6px; }
    .letterhead .off  { font-size: 9.5pt; margin-top: 2px; }
    .letterhead .unit { font-size: 10pt; font-weight: bold; margin-top: 1px; }
    .rule-thin { border-bottom: .6px solid #1f5c3a; margin-bottom: 14px; }
    .report-title { text-align: center; margin-bottom: 16px; }
    .report-title .name   { font-size: 12.5pt; font-weight: bold; color: #1f5c3a; letter-spacing: .5px; }
    .report-title .period { font-size: 8.6pt; color: #555; margin-top: 3px; }

    .running { border-bottom: .8px solid #c9d1cb; padding-bottom: 5px; margin-bottom: 14px; font-size: 7.6pt; color: #666; }
    .running td { padding: 0; border: none; }

    .page-break { page-break-before: always; }

    h2 { font-size: 11pt; color: #1f5c3a; margin: 0 0 10px; padding-left: 7px; border-left: 3px solid #1f5c3a; }
    h3 { font-size: 9.4pt; color: #1f5c3a; margin: 9px 0 4px; }
    .keep h3 { margin-top: 9px; }
    .line { font-size: 9.2pt; font-weight: bold; margin: 2px 0 6px; }
    .note { font-size: 7.4pt; color: #666; font-style: italic; margin: 8px 0 4px; }

    table { width: 100%; border-collapse: collapse; margin-bottom: 9px; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    th, td { border: .6px solid #b9c2bc; padding: 2.6px 6px; vertical-align: middle; }
    td.nw { white-space: nowrap; }
    /* Small summary tables sit centred at a readable width instead of stretching. */
    table.narrow { width: 62%; margin-left: auto; margin-right: auto; }
    .keep { page-break-inside: avoid; }
    th { font-size: 7.8pt; text-align: center; }
    .num { text-align: center; white-space: nowrap; }
    .empty { text-align: center; color: #888; font-style: italic; }

    /* General tables */
    table.t th { background: #1f5c3a; color: #fff; }
    table.t tbody tr:nth-child(even) td { background: #f5f8f6; }
    table.t tr.total td { background: #e6efe8; font-weight: bold; }

    /* Printed GCU Accomplishment Report style (same colours as the Excel) */
    table.p th { background: #d6dce5; color: #1d1d1d; }
    table.p td.cnt { background: #fdf1db; }
    table.p td.tot { background: #f6cf6e; font-weight: bold; }
    table.p td.grp { font-weight: bold; }
    table.p tr.group-start td { border-top: 1.1px solid #8c8c8c; }
    table.p tr.total td { background: #c9d6c3; font-weight: bold; }
    table.p tr.gold td { background: #f6cf6e; font-weight: bold; }
    table.p.matrix th, table.p.matrix td { padding: 3px 3px; font-size: 7pt; }

    .signatures { width: 100%; margin-top: 34px; border: none; }
    .signatures td { border: none; width: 50%; padding: 0 30px 0 0; vertical-align: top; }
    .signatures .label { margin-bottom: 30px; }
    .signatures .sigline { border-top: .8px solid #333; width: 230px; padding-top: 3px; font-size: 7.4pt; color: #555; font-style: italic; }
</style>
</head>
<body>

@foreach ($doc as $at => $block)
    @php $short = $nextRows($at - 1) <= 18 ? 'keep' : ''; @endphp
    @switch($block['type'])

        @case('sheet')
            @if ($keepOpen) </div> @php $keepOpen = false; @endphp @endif
            @if ($isFirst)
                <div class="letterhead">
                    <div class="uni">BENGUET STATE UNIVERSITY</div>
                    <div class="off">Office of Student Services</div>
                    <div class="unit">{{ $unitName }}</div>
                </div>
                <div class="rule-thin"></div>
                <div class="report-title">
                    <div class="name">{{ mb_strtoupper($reportTitle) }}</div>
                    <div class="period">{{ $period }}</div>
                </div>
                @php $isFirst = false; @endphp
            @else
                <div class="page-break"></div>
                <table class="running"><tr>
                    <td style="text-align:left">Benguet State University · {{ $unitName }}</td>
                    <td style="text-align:right">{{ $reportTitle }} · {{ $period }}</td>
                </tr></table>
            @endif
            <h2>{{ $block['heading'] }}</h2>
            @break

        @case('section')
            @if ($keepOpen) </div> @php $keepOpen = false; @endphp @endif
            @if ($nextRows($at) <= 18)
                <div class="keep">
                @php $keepOpen = true; @endphp
            @endif
            <h3>{{ $block['title'] }}</h3>
            @break

        @case('line')
            <div class="line">{{ $block['text'] }}</div>
            @break

        @case('note')
            <div class="note">{{ $block['text'] }}</div>
            @break

        @case('table')
            @php
                $headers = $block['headers'];
                $rows    = $block['rows'];
                $opt     = $block['options'];
                // Columns with no heading and no values exist only to widen the Excel sheet.
                $keep = [];
                foreach ($headers as $i => $h) {
                    $used = trim((string) $h) !== '' || collect($rows)->contains(fn($r) => trim((string) (array_values($r)[$i] ?? '')) !== '');
                    if ($used) $keep[] = $i;
                }
                $numeric = array_merge($opt['center'] ?? [], $opt['total'] ?? [], $opt['percent'] ?? []);
            @endphp
            <table class="t {{ count($keep) <= 3 ? 'narrow' : '' }} {{ $short }}">
                <thead><tr>
                    @foreach ($keep as $i)<th>{{ $headers[$i] }}</th>@endforeach
                </tr></thead>
                <tbody>
                    @forelse ($rows as $r)
                        @php $r = array_values($r); @endphp
                        <tr>
                            @foreach ($keep as $i)
                                @php $v = $r[$i] ?? ''; @endphp
                                <td class="{{ in_array($i, $numeric, true) ? 'num' : '' }} {{ $noWrap($v) ? 'nw' : '' }}">
                                    {{ in_array($i, $opt['percent'] ?? [], true) && is_numeric($v) ? number_format($v * 100, 1) . '%' : $fmt($v) }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($keep) }}" class="empty">No records for this period.</td></tr>
                    @endforelse
                    @if ($rows && !empty($opt['total']))
                        <tr class="total">
                            @foreach ($keep as $n => $i)
                                <td class="{{ $n === 0 ? '' : 'num' }}">
                                    @if ($n === 0) TOTAL
                                    @elseif (in_array($i, $opt['total'], true)) {{ $fmt(collect($rows)->sum(fn($r) => (float) (array_values($r)[$i] ?? 0))) }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endif
                </tbody>
            </table>
            @if ($keepOpen) </div> @php $keepOpen = false; @endphp @endif
            @break

        @case('grouped')
            @php
                $all = collect($block['groups'])->flatMap(fn($g) => $g['rows'] ?? []);
            @endphp
            <table class="p {{ $short }}">
                <thead>
                    <tr>
                        <th rowspan="2" style="width:16%">{{ $block['first'] }}</th>
                        <th rowspan="2">COURSE</th>
                        <th colspan="3">{{ $block['groupTitle'] }}</th>
                    </tr>
                    <tr><th style="width:11%">MALE</th><th style="width:11%">FEMALE</th><th style="width:11%">TOTAL</th></tr>
                </thead>
                <tbody>
                    @if ($all->isEmpty())
                        <tr><td colspan="5" class="empty">No records for this period.</td></tr>
                    @endif
                    @foreach ($block['groups'] as $g)
                        @foreach ($g['rows'] ?? [] as $i => $r)
                            <tr class="{{ $i === 0 ? 'group-start' : '' }}">
                                <td class="grp">{{ $i === 0 ? $g['college'] : '' }}</td>
                                <td>{{ $r['course'] }}</td>
                                <td class="num cnt">{{ $r['male'] }}</td>
                                <td class="num cnt">{{ $r['female'] }}</td>
                                <td class="num tot">{{ $r['total'] }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                    <tr class="total">
                        <td></td><td>TOTAL</td>
                        <td class="num">{{ $all->sum('male') }}</td>
                        <td class="num">{{ $all->sum('female') }}</td>
                        <td class="num">{{ $all->sum('total') }}</td>
                    </tr>
                </tbody>
            </table>
            @if ($keepOpen) </div> @php $keepOpen = false; @endphp @endif
            @break

        @case('services')
            <table class="p {{ $short }}">
                <thead><tr><th style="text-align:left">SERVICES</th><th style="width:16%">{{ $block['valueHeader'] }}</th></tr></thead>
                <tbody>
                    @foreach ($block['rows'] as [$label, $value])
                        <tr><td>{{ $label }}</td><td class="num">{{ $value }}</td></tr>
                    @endforeach
                    <tr class="gold">
                        <td style="text-align:right">TOTAL</td>
                        <td class="num">{{ collect($block['rows'])->sum(fn($r) => (int) $r[1]) }}</td>
                    </tr>
                </tbody>
            </table>
            @if ($keepOpen) </div> @php $keepOpen = false; @endphp @endif
            @break

        @case('matrix')
            @php
                $all  = collect($block['groups'])->flatMap(fn($g) => $g['rows'] ?? []);
                $cats = $block['categories'];
                $hasCats = $all->contains(fn($r) => !empty($r['categories']));
            @endphp
            <table class="p matrix {{ $short }}">
                <thead>
                    <tr>
                        <th colspan="2">{{ $block['first'] }}</th>
                        @foreach ($cats as $label)<th colspan="2">{{ $label }}</th>@endforeach
                        <th rowspan="2" style="width:6%">TOTAL</th>
                    </tr>
                    <tr>
                        <th style="width:9%">{{ $block['collegeHeader'] }}</th>
                        <th style="width:13%">COURSES</th>
                        @foreach ($cats as $label)<th>MALE</th><th>FEMALE</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                    @if ($all->isEmpty())
                        <tr><td colspan="{{ 3 + 2 * count($cats) }}" class="empty">No records for this period.</td></tr>
                    @endif
                    @foreach ($block['groups'] as $g)
                        @foreach ($g['rows'] ?? [] as $i => $r)
                            <tr class="{{ $i === 0 ? 'group-start' : '' }}">
                                <td class="grp">{{ $i === 0 ? $g['college'] : '' }}</td>
                                <td>{{ $r['course'] }}</td>
                                @foreach (array_keys($cats) as $key)
                                    <td class="num">{{ $r['categories'][$key]['male'] ?? '' }}</td>
                                    <td class="num">{{ $r['categories'][$key]['female'] ?? '' }}</td>
                                @endforeach
                                <td class="num tot">{{ $r['total'] }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                    <tr class="total">
                        <td></td><td>TOTAL</td>
                        @foreach (array_keys($cats) as $key)
                            <td class="num">{{ $hasCats ? $all->sum(fn($r) => $r['categories'][$key]['male'] ?? 0) : '' }}</td>
                            <td class="num">{{ $hasCats ? $all->sum(fn($r) => $r['categories'][$key]['female'] ?? 0) : '' }}</td>
                        @endforeach
                        <td class="num">{{ $all->sum('total') }}</td>
                    </tr>
                </tbody>
            </table>
            @if ($keepOpen) </div> @php $keepOpen = false; @endphp @endif
            @break

        @case('signatures')
            <table class="signatures"><tr>
                <td><div class="label">Prepared by:</div><div class="sigline">Signature over printed name</div></td>
                <td><div class="label">Noted by:</div><div class="sigline">Signature over printed name</div></td>
            </tr></table>
            @break

    @endswitch
@endforeach
@if ($keepOpen) </div> @endif

</body>
</html>
