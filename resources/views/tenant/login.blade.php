<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tenant Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>

<body class="auth-page">
    <main class="auth-card-wrapper">
        <section class="auth-card">
            <img class="auth-logo" src="{{ asset('images/wappzoLogo.svg') }}" alt="Wappzo">
            <h1 class="auth-title">Tenant Login</h1>
            <p class="auth-subtitle">Enter your store domain and administrator credentials.</p>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('tenant.login.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="domain">Store domain</label>
                    <input class="form-control @error('domain') is-invalid @enderror" id="domain" name="domain"
                        type="text" value="{{ old('domain', request('domain')) }}" placeholder="example.com"
                        autocomplete="url" required autofocus>
                    @error('domain')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                        type="email" value="{{ old('email') }}" autocomplete="email" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control @error('password') is-invalid @enderror" id="password" name="password"
                        type="password" autocomplete="current-password" required>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <button class="btn btn-primary w-100" type="submit">Sign in</button>
            </form>
        </section>
    </main>
</body>

</html>
