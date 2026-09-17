<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>

<body class="auth-page">
    <main class="auth-card-wrapper">
        <section class="auth-card">
            <img class="auth-logo" src="{{ asset('images/wappzoLogo.svg') }}" alt="Wappzo">
            <h1 class="auth-title">Change your password</h1>
            <p class="auth-subtitle">Set a new password before accessing your dashboard.</p>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('tenant.password.change') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="current_password">Temporary password</label>
                    <input class="form-control" id="current_password" name="current_password" type="password"
                        autocomplete="current-password" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">New password</label>
                    <input class="form-control" id="password" name="password" type="password"
                        autocomplete="new-password" required>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password_confirmation">Confirm new password</label>
                    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password"
                        autocomplete="new-password" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Change password</button>
            </form>
        </section>
    </main>
</body>

</html>
