<x-pulse::card :cols="$cols" :rows="$rows ?? 2" :class="$class">
    <x-pulse::card-header name="Uso por tenant (vizinhos barulhentos)" details="past {{ $this->periodForHumans() }}">
        <x-slot:icon>
            <x-pulse::icons.scale />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        @if (count($rows) === 0)
            <x-pulse::no-results />
        @else
            <x-pulse::table>
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>Tenant</x-pulse::th>
                        <x-pulse::th class="text-right">Requests</x-pulse::th>
                        <x-pulse::th class="text-right">Média (ms)</x-pulse::th>
                        <x-pulse::th class="text-right">Máx (ms)</x-pulse::th>
                        <x-pulse::th class="text-right">Jobs</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <x-pulse::td>{{ $row->tenant }}</x-pulse::td>
                            <x-pulse::td numeric>{{ number_format($row->requests) }}</x-pulse::td>
                            <x-pulse::td numeric>{{ number_format($row->avg) }}</x-pulse::td>
                            <x-pulse::td numeric>{{ number_format($row->max) }}</x-pulse::td>
                            <x-pulse::td numeric>{{ number_format($row->jobs) }}</x-pulse::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
