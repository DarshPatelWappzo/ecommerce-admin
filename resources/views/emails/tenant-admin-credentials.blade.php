<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your tenant administrator credentials</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1f2937;">
    <p>Hello {{ $recipientName }},</p>

    <p>Your administrator account for <strong>{{ $domain }}</strong> has been created.</p>

    <p><strong>Email:</strong> {{ $email }}<br>
        <strong>Temporary password:</strong> {{ $temporaryPassword }}
    </p>

    <p>You must change this password after your first login before accessing the dashboard.</p>

    <p>
        <a href="{{ route('tenant.login.form', ['domain' => $domain]) }}"
            style="display: inline-block; padding: 10px 16px; color: #ffffff; background-color: #2563eb; text-decoration: none; border-radius: 6px;">
            Sign in
        </a>
    </p>

    <p>Thanks,<br>{{ config('app.name') }}</p>
</body>

</html>
