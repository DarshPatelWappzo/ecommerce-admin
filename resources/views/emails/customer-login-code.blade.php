<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1f2937;">
    <p>Hello,</p>

    <p> Your sign-in code is <strong>{{ $code }}</strong></p>

    <p>It expires in 10 minutes. If you did not request this code, you can ignore this email.</p>

    <p>Thanks,<br>{{ config('app.name') }}</p>
</body>

</html>
