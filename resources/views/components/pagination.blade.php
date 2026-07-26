@props(['baseUrl', 'current', 'total', 'queryString' => ''])

@if ($total > 1)
    <nav aria-label="Pagination" class="pagination" class="mt-0">
        @if ($current > 1)
            <a href="{{ $baseUrl . ($current - 1) . $queryString }}" class="page-link" aria-label="{{ __('a11y.previous_page') }}" rel="prev">
                <span aria-hidden="true">&laquo;</span>
            </a>
        @endif

        @php
            $start = max(1, $current - 2);
            $end = min($total, $current + 2);
        @endphp

        @if ($start > 1)
            <a href="{{ $baseUrl . '1' . $queryString }}" class="page-link">1</a>
            @if ($start > 2)
                <span class="page-disabled">...</span>
            @endif
        @endif

        @for ($i = $start; $i <= $end; $i++)
            @if ($i === (int) $current)
                <span class="page-current" aria-current="page">{{ $i }}</span>
            @else
                <a href="{{ $baseUrl . $i . $queryString }}" class="page-link">{{ $i }}</a>
            @endif
        @endfor

        @if ($end < $total)
            @if ($end < $total - 1)
                <span class="page-disabled">...</span>
            @endif
            <a href="{{ $baseUrl . $total . $queryString }}" class="page-link">{{ $total }}</a>
        @endif

        @if ($current < $total)
            <a href="{{ $baseUrl . ($current + 1) . $queryString }}" class="page-link" aria-label="{{ __('a11y.next_page') }}" rel="next">
                <span aria-hidden="true">&raquo;</span>
            </a>
        @endif
    </nav>
@endif
