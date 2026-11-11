<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admission Slip</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #333; line-height: 1.5; }
        .container { width: 100%; max-width: 700px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .title { font-weight: bold; font-size: 16px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px; }
        .doc-info { width: 100%; font-size: 10px; color: #666; margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
        .doc-info td { vertical-align: top; }
        
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 4px 0; }
        .label { font-weight: bold; width: 150px; }
        .value { border-bottom: 1px solid #999; font-style: italic; color: #111; }
        
        .section-title { font-weight: bold; margin-top: 15px; margin-bottom: 8px; }
        
        .checkbox-list { width: 100%; margin-bottom: 15px; }
        .checkbox-list td { width: 50%; padding: 3px 0; vertical-align: top; }
        
        .signature-area { text-align: center; margin-top: 50px; }
        .signature-name { font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #000; display: inline-block; padding: 0 30px 2px; }
        .signature-title { font-size: 10px; color: #666; margin-top: 4px; }
        
        .footer { text-align: center; font-size: 10px; font-style: italic; color: #666; margin-top: 40px; border-top: 1px dashed #ccc; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="doc-info">
            <table width="100%">
                <tr>
                    <td align="left"><strong>Document Code:</strong> QF-OSS-GCU-09<br><strong>Revision No.:</strong> 01</td>
                    <td align="right"><strong>Effectivity:</strong> 07/04/23<br><strong>Ctrl No.:</strong> {{ $referral->referral_code }}</td>
                </tr>
            </table>
        </div>

        <div class="header">
            <div class="title">Admission Slip</div>
        </div>

        <table class="info-table">
            <tr>
                <td class="label">Name:</td>
                <td class="value">{{ $student->first_name }} {{ $student->middle_name }} {{ $student->last_name }} {{ $student->suffix }}</td>
                <td class="label" style="width: 50px;">Date:</td>
                <td class="value">{{ \Carbon\Carbon::parse($referral->admission_issued_at)->format('F j, Y') }}</td>
            </tr>
            <tr>
                <td class="label">Program & Year Level:</td>
                <td class="value">{{ $student->program }}, Year {{ $student->year_level }}</td>
                <td class="label" style="width: 50px;">Sex:</td>
                <td class="value">{{ $student->sex }}</td>
            </tr>
        </table>

        <table class="info-table">
            <tr>
                <td class="label" style="vertical-align: top;">Date/s Absent/Tardy:</td>
                <td class="value" style="border-bottom: none;">
                    @foreach($details['dates'] ?? [] as $d)
                        {{ \Carbon\Carbon::parse($d['date'])->format('F j, Y') }}, 
                        {{ \Carbon\Carbon::parse($d['time_in'] ?? '08:00')->format('g:i A') }} - 
                        {{ \Carbon\Carbon::parse($d['time_out'] ?? '17:00')->format('g:i A') }}<br>
                    @endforeach
                </td>
            </tr>
            <tr>
                <td class="label">Number of day/s absent/tardy:</td>
                <td class="value">{{ $details['days_count'] ?? '-' }}</td>
            </tr>
        </table>

        <div class="section-title">Cause/s of Absences:</div>
        <table class="checkbox-list">
            @php $causes = $details['causes'] ?? []; @endphp
            <tr>
                <td>{!! in_array('health', $causes) ? '&#9745;' : '&#9744;' !!} health-related concerns</td>
                <td>{!! in_array('personal', $causes) ? '&#9745;' : '&#9744;' !!} personal concerns</td>
            </tr>
            <tr>
                <td>{!! in_array('filial', $causes) ? '&#9745;' : '&#9744;' !!} filial responsibilities</td>
                <td>{!! in_array('socio_cultural', $causes) ? '&#9745;' : '&#9744;' !!} socio-cultural concern</td>
            </tr>
            <tr>
                <td>{!! in_array('environmental', $causes) ? '&#9745;' : '&#9744;' !!} environmental</td>
                <td>{!! in_array('behavioral', $causes) ? '&#9745;' : '&#9744;' !!} behavioral</td>
            </tr>
            <tr>
                <td colspan="2">
                    {!! in_array('official_activity', $causes) ? '&#9745;' : '&#9744;' !!} official co/extra-curricular activity: 
                    <em>{{ $details['cause_activity'] ?? '' }}</em>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    {!! in_array('others', $causes) ? '&#9745;' : '&#9744;' !!} others: 
                    <em>{{ $details['cause_others'] ?? '' }}</em>
                </td>
            </tr>
        </table>

        <div class="section-title">Action/s taken:</div>
        <table class="checkbox-list" style="width: 100%;">
            @php $actions = $details['actions'] ?? []; @endphp
            <tr><td>{!! in_array('verified_documents', $actions) ? '&#9745;' : '&#9744;' !!} verified supporting document/s</td></tr>
            <tr><td>{!! in_array('interviewed', $actions) ? '&#9745;' : '&#9744;' !!} interviewed with guidance</td></tr>
            <tr><td>{!! in_array('life_coaching', $actions) ? '&#9745;' : '&#9744;' !!} life coaching</td></tr>
            <tr><td>{!! in_array('parent_conference', $actions) ? '&#9745;' : '&#9744;' !!} parent conference</td></tr>
            <tr><td>{!! in_array('counseling', $actions) ? '&#9745;' : '&#9744;' !!} counseling</td></tr>
            <tr>
                <td>{!! in_array('others', $actions) ? '&#9745;' : '&#9744;' !!} others: <em>{{ $details['action_others'] ?? '' }}</em></td>
            </tr>
        </table>

        <div class="section-title" style="margin-top: 20px;">
            {!! $referral->admission_excused ? '&#9745;' : '&#9744;' !!} Excused &nbsp;&nbsp;&nbsp;&nbsp;
            {!! $referral->admission_excused === false ? '&#9745;' : '&#9744;' !!} Unexcused
        </div>
        
        <table class="info-table" style="margin-top: 10px;">
            <tr>
                <td class="label">Covers:</td>
                <td class="value" style="border-bottom: 1px solid #999;">
                    {{ implode('; ', array_map(fn($d) => \Carbon\Carbon::parse($d)->format('F j, Y'), $details['excused_dates'] ?? [])) }}
                </td>
            </tr>
            <tr>
                <td class="label">Remarks:</td>
                <td class="value" style="border-bottom: 1px solid #999;">{{ $referral->admission_remarks ?? '-' }}</td>
            </tr>
        </table>

        <div class="signature-area">
            <div class="signature-name">{{ $personnel }}</div>
            <div class="signature-title">Attending OSS Personnel</div>
        </div>

        <div class="footer">
            This document is not official unless signed by a guidance counselor.
        </div>
    </div>
</body>
</html>