<!doctype html>
<html lang="ro">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $subjectLine }}</title></head>
<body style="margin:0;background:#F3F1FA;font-family:Arial,Helvetica,sans-serif;color:#353535;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3F1FA;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">
<tr><td style="background:#1E3BA8;padding:0 20px 0 28px;border-bottom:4px solid #1F1638;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
<td style="padding:16px 0;vertical-align:middle;"><img src="{{ asset('media/brand/logo-alb-320.png') }}" width="132" height="96" alt="{{ \App\Support\Settings::get('clinic.name') }}" style="display:block;border:0;width:132px;height:auto;"></td>
<td align="right" style="vertical-align:bottom;padding-top:10px;line-height:0;"><img src="{{ asset('media/personaje/pisica-si-caine.png') }}" width="128" height="80" alt="Raza și Hiko" style="display:block;border:0;width:128px;height:80px;"></td>
</tr></table>
</td></tr>
<tr><td style="padding:28px;font-size:15px;line-height:1.6;">
<h1 style="font-size:20px;margin:0 0 16px;color:#1F1638;">{{ $subjectLine }}</h1>
{!! $html !!}
</td></tr>
<tr><td style="padding:18px 28px;background:#F3F1FA;font-size:13px;color:#4B4560;line-height:1.5;">
{{ \App\Support\Settings::get('clinic.address') }}, {{ \App\Support\Settings::get('clinic.city') }}<br>
{{ \App\Support\Settings::get('clinic.phone') }} | {{ \App\Support\Settings::get('clinic.email') }}
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
