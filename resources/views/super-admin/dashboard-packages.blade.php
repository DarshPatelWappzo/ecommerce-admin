<ul class="commerce-package-revenue">
    @forelse ($chart['packages'] as $package)
        <li>
            <span title="{{ $package['name'] }}">{{ $package['name'] }}</span>
            <span class="commerce-package-track" aria-hidden="true"><span
                    style="width: {{ $package['width'] }}%"></span></span>
            <span class="commerce-package-amount">{{ $package['amount'] }}</span>
        </li>
    @empty
        <li class="commerce-empty">No packages available.</li>
    @endforelse
</ul>
