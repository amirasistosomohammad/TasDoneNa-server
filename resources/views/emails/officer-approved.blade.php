<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TasDoneNa — Officer account approved</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 520px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #243047;">TasDoneNa</h2>
    <p>Hi {{ $name }},</p>
    <p>
        Your TasDoneNa personnel account has been <strong>approved</strong>. You can now sign in using your registered
        email address and password.
    </p>

    @if(!empty($remarks))
        <p style="margin-top: 18px; font-weight: bold; color: #374151;">Notes from the administrator</p>
        <p style="white-space: pre-line; color: #4b5563;">{{ $remarks }}</p>
    @endif

    <p style="margin-top: 18px; font-size: 14px; color: #4b5563;">
        If you did not request this account or believe this email was sent to you in error, please contact your
        system administrator.
    </p>

    <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
    <p style="font-size: 12px; color: #888;">
        TasDoneNa — Task Management for Public School Administrative Officers, Tigbao District
    </p>
</body>
</html>

