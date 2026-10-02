<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your tenant user credentials</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1f2937;">
    <p>Hello {{ $user->first_name }},</p>

    <p>Your user account for <strong>{{ $domain }}</strong> has been created.</p>

    <p><strong>Email:</strong> {{ $user->email }}<br>
        <strong>Temporary password:</strong> {{ $temporaryPassword }}
    </p>

    <p>You must change this password after your first login before accessing the dashboard.</p>

    <p><a href="{{ route('tenant.login.form', ['domain' => $domain]) }}">Sign in</a></p>

    <p>Thanks,<br>{{ config('app.name') }}</p>
</body>

</html>
