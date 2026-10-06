<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Replacement Status Changed</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1f2937;">

    <p> Your replacement request <strong>{{ $replacementNumber }}</strong> is now
        <strong>{{ ucwords(str_replace('_', ' ', $status)) }}</strong>.
    </p>

    <p>You can check your replacement request for the latest details and shipment tracking.</p>

    <p>Thanks,<br>{{ config('app.name') }}</p>
</body>

</html>
