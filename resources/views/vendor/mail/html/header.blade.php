@props(['url'])
<tr>
<td class="header">
{{-- Plain text on purpose: the email header must not link to any website. --}}
<span style="color: #3d4852; font-size: 19px; font-weight: bold; display: inline-block;">
{{ $slot }}
</span>
</td>
</tr>