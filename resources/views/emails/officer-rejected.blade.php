<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TasDoneNa — Officer registration update</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 520px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #243047;">TasDoneNa</h2>
    <p>Hi {{ $name }},</p>
    <p>
        Thank you for your interest in TasDoneNa. After review, your personnel registration has been
        <strong>not approved</strong> at this time.
    </p>

    @if(!empty($reason))
        <p style="margin-top: 18px; font-weight: bold; color: #374151;">Reason provided by the administrator</p>
        <p style="white-space: pre-line; color: #4b5563;">{{ $reason }}</p>
    @endif

    <p style="margin-top: 18px; font-size: 14px; color: #4b5563;">
        If you believe this decision was made in error or your circumstances have changed, please contact your
        system administrator for further guidance.
    </p>

    <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
    <p style="font-size: 12px; color: #888;">
        TasDoneNa — Task Management for Public School Administrative Officers, Tigbao District
    </p>
</body>
</html>

