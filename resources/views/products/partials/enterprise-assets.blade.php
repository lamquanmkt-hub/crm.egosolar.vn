@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/ego-inventory-enterprise.css') }}?v={{ file_exists(public_path('css/ego-inventory-enterprise.css')) ? filemtime(public_path('css/ego-inventory-enterprise.css')) : '3.0.0' }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('js/ego-inventory-enterprise.js') }}?v={{ file_exists(public_path('js/ego-inventory-enterprise.js')) ? filemtime(public_path('js/ego-inventory-enterprise.js')) : '3.0.0' }}" defer></script>
    @endpush
@endonce
