<!DOCTYPE html>
<html lang="{{ $lang }}">
<body style="margin:0;background:#f6f1e7;font-family:Georgia,serif;color:#2b2420">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px">
<table width="520" cellpadding="0" cellspacing="0" style="max-width:520px;background:#fff;border-radius:14px;padding:32px">
<tr><td>
<h1 style="font-size:24px;margin:0 0 12px">{{ trans('portal.mail_hello', [], $lang) }}</h1>
<p style="font-family:system-ui,sans-serif;font-size:15px;line-height:1.5">{{ trans('portal.mail_text', [], $lang) }}</p>
<p style="text-align:center;margin:28px 0"><a href="{{ $confirmUrl }}" style="background:{{ $location->brand_color }};color:#fff;padding:14px 24px;border-radius:999px;text-decoration:none;font-family:system-ui,sans-serif;font-weight:600">{{ trans('portal.mail_button', [], $lang) }}</a></p>
<p style="font-family:system-ui,sans-serif;font-size:13px;color:#7a6f66">{{ trans('portal.mail_ignore', [], $lang) }}</p>
</td></tr></table>
</td></tr></table>
</body>
</html>
