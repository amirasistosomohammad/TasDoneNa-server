<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TasDoneNa — Officer account deactivated</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 520px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #243047;">TasDoneNa</h2>
    <p>Hi {{ $name }},</p>
    <p>
        This is to inform you that your TasDoneNa personnel account has been <strong>deactivated</strong>.
        You will not be able to sign in until the account is activated again by an administrator.
    </p>

    @if(!empty($reason))
        <p style="margin-top: 18px; font-weight: bold; color: #374151;">Reason provided by the administrator</p>
        <p style="white-space: pre-line; color: #4b5563;">{{ $reason }}</p>
    @endif

    <p style="margin-top: 18px; font-size: 14px; color: #4b5563;">
        If you have questions about this change, please contact your system administrator.
    </p>

    <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
    <p style="font-size: 12px; color: #888;">
        TasDoneNa — Task Management for Public School Administrative Officers, Tigbao District
    </p>
</body>
</html>

