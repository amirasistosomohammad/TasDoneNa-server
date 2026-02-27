<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TasDoneNa — Verify your email</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 480px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #243047;">TasDoneNa</h2>
    <p>Hi {{ $name }},</p>
    <p>Thank you for registering. Use the one-time password below to verify your email:</p>
    <p style="font-size: 24px; font-weight: bold; letter-spacing: 4px; color: #d5326f; margin: 20px 0;">{{ $otp }}</p>
    <p>This code expires in <strong>15 minutes</strong>. Do not share it with anyone.</p>
    <p>If you did not register for TasDoneNa, you can ignore this email.</p>
    <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
    <p style="font-size: 12px; color: #888;">TasDoneNa — Task Management for Public School Administrative Personnel, Tigbao District</p>
</body>
</html>
