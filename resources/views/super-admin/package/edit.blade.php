@extends('layouts.app')

@section('title', 'Edit Package')

@section('content')
    <div class="container-fluid">
        <div class="mb-4">
            <p class="text-primary fw-semibold mb-1">Super Admin</p>
            <h1 class="page-title mb-1">Edit package</h1>
            <p class="text-secondary mb-0">Update the package details.</p>
        </div>

        <div class="dashboard-card">
            @include('super-admin.package._form', [
                'action' => route('super-admin.package.update', $package),
                'method' => 'PUT',
                'package' => $package,
                'submitLabel' => 'Save changes',
            ])
        </div>
    </div>
@endsection
