@php
    $oldValues = $auditLog->old_values ?? [];
    $newValues = $auditLog->new_values ?? [];
    $recordValues = array_merge($oldValues, $newValues);
    $recordEmail =
        $auditLog->auditable?->email ?? ($auditLog->auditable?->user?->email ?? ($recordValues['email'] ?? null));
    $packageName = $auditLog->auditable?->name ?? ($recordValues['name'] ?? null);
    $booleanFields = ['backup_included', 'cdn_included', 'load_balancer_included', 'is_recommended'];
    $fields =
        $auditLog->action === 'updated'
            ? array_unique(array_merge(array_keys($oldValues), array_keys($newValues)))
            : array_keys($auditLog->action === 'created' ? $newValues : $oldValues);
    $formatValue = static function (mixed $value, ?string $field = null) use ($booleanFields): string {
        if ($value === null || $value === '') {
            return '—';
        }

        if (
            is_bool($value) ||
            ($field !== null && in_array($field, $booleanFields, true) && in_array((string) $value, ['0', '1'], true))
        ) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return collect($value)->map(fn(mixed $item, string|int $key): string => $key . ': ' . $item)->implode(', ');
        }

        return (string) $value;
    };
@endphp

<div class="modal fade" id="audit-log-{{ $auditLog->id }}" tabindex="-1"
    aria-labelledby="audit-log-label-{{ $auditLog->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="audit-log-label-{{ $auditLog->id }}">
                        {{ Illuminate\Support\Str::headline($auditLog->module) }} {{ ucfirst($auditLog->action) }}</h5>
                    @if ($auditLog->module === 'users' && $recordEmail)
                        <div class="text-primary"><strong>Email:</strong> {{ $recordEmail }}</div>
                    @elseif ($auditLog->module === 'packages' && $packageName)
                        <div class="text-primary"><strong>Package:</strong> {{ $packageName }}</div>
                    @endif
                    <small class="text-secondary">{{ $auditLog->created_at?->format('d M Y, h:i A') }}</small>
                </div>
                <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr class="">
                            <th class="bg-light">Sr. No.</th>
                            <th class="bg-light">Field Name</th>
                            <th class="bg-light">Old Value</th>
                            <th class="bg-light">New Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($fields as $field)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="fw-semibold">{{ Illuminate\Support\Str::headline($field) }}</td>
                                <td>{{ $formatValue($oldValues[$field] ?? null, $field) }}</td>
                                <td>{{ $formatValue($newValues[$field] ?? null, $field) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-secondary" colspan="4">No field changes recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
