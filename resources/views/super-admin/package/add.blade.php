@extends('layouts.app')

@section('title', 'Add Package')

@section('content')
    <div class="container-fluid">
        <div class="mb-4">
            <p class="text-primary fw-semibold mb-1">Super Admin</p>
            <h1 class="page-title mb-1">Add package</h1>
            <p class="text-secondary mb-0">Create a package for your users.</p>
        </div>

        <div class="dashboard-card">
            @include('super-admin.package._form', [
                'action' => route('super-admin.package.store'),
                'method' => 'POST',
                'package' => null,
                'submitLabel' => 'Create package',
            ])
        </div>
    </div>
@endsection
