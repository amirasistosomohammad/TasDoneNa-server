<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TasDoneNa — Reset your password</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 480px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #243047;">TasDoneNa</h2>
    <p>Hi {{ $name }},</p>
    <p>You requested a password reset. Click the link below to set a new password:</p>
    <p style="margin: 24px 0;">
        <a href="{{ $resetUrl }}" style="display: inline-block; padding: 12px 24px; background-color: #f54286; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: bold;">Reset password</a>
    </p>
    <p style="font-size: 14px; color: #6b7280;">Or copy and paste this link into your browser:</p>
    <p style="font-size: 13px; word-break: break-all; color: #243047;">{{ $resetUrl }}</p>
    <p style="font-size: 14px; color: #6b7280;">This link expires in <strong>{{ $expireMinutes }} minutes</strong>. If you did not request a password reset, you can ignore this email.</p>
    <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
    <p style="font-size: 12px; color: #888;">TasDoneNa — Task Management for Public School Administrative Officers, Tigbao District</p>
</body>
</html>
