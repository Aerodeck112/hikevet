<!doctype html>
<html lang="ro">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $subjectLine }}</title></head>
<body style="margin:0;background:#ECF2E3;font-family:Arial,Helvetica,sans-serif;color:#353535;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ECF2E3;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">
<tr><td style="background:#21B073;color:#ffffff;padding:20px 28px;font-size:20px;font-weight:bold;">{{ \App\Support\Settings::get('clinic.name') }}</td></tr>
<tr><td style="padding:28px;font-size:15px;line-height:1.6;">
<h1 style="font-size:20px;margin:0 0 16px;color:#1c2b22;">{{ $subjectLine }}</h1>
{!! $html !!}
</td></tr>
<tr><td style="padding:18px 28px;background:#f6f8f3;font-size:13px;color:#5c6660;line-height:1.5;">
{{ \App\Support\Settings::get('clinic.address') }}, {{ \App\Support\Settings::get('clinic.city') }}<br>
{{ \App\Support\Settings::get('clinic.phone') }} | {{ \App\Support\Settings::get('clinic.email') }}
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
