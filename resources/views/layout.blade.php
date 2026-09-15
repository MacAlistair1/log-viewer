<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('log-viewer.ui.title', 'Log Viewer') }} | @yield('title', 'Dashboard')</title>
    <link rel="stylesheet" href="{{ log_viewer_asset('css/log-viewer.css') }}">
    @stack('head')
</head>

<body class="lv-root" data-lv-refresh-enabled="{{ config('log-viewer.ui.auto_refresh.enabled') ? '1' : '0' }}"
    data-lv-refresh-interval="{{ (int) config('log-viewer.ui.auto_refresh.interval', 10000) }}"
    data-lv-refresh-allow-toggle="{{ config('log-viewer.ui.auto_refresh.user_toggle', true) ? '1' : '0' }}">
    <nav class="lv-navbar">
        <a class="lv-navbar-brand" href="{{ route('log-viewer.index') }}">
            <span class="lv-dot"></span>
            <span>{{ config('log-viewer.ui.title', 'Log Viewer') }}</span>
        </a>
        <div class="lv-navbar-spacer"></div>
        <div class="lv-navbar-actions">
            <a href="{{ config('log-viewer.ui.back_to_app_url') ?? url('/') }}" class="lv-btn">
                <span>&larr; Back</span>
                <span class="lv-refresh-prefix">to {{ config('app.name', 'Application') }}</span>
            </a>
            <a href="//github.com/MacAlistair1" target="_blank" rel="noopener noreferrer" class="lv-icon-link"
                title="View on GitHub">
                <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.385-1.333-1.754-1.333-1.754-1.089-.744.084-.729.084-.729 1.205.084 1.84 1.237 1.84 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.605-2.665-.3-5.467-1.334-5.467-5.93 0-1.31.467-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12" />
                </svg>
            </a>
            <button type="button" class="lv-btn lv-btn-sm" data-lv-theme-toggle aria-pressed="false">
                <span data-lv-theme-icon>🌙</span><span class="lv-theme-label" data-lv-theme-label>Dark</span>
            </button>
        </div>
    </nav>

    <main class="lv-container">
        @if (session('status'))
            <div class="lv-alert">{{ session('status') }}</div>
        @endif

        @yield('content')
    </main>

    @php
        $__lvMemory = number_format(memory_get_peak_usage(true) / 1048576, 2) . ' MB';
        $__lvDuration = defined('LARAVEL_START')
            ? number_format((microtime(true) - LARAVEL_START) * 1000) . 'ms'
            : null;
        $__lvVersion = \Jeeven\LogViewer\LogViewerServiceProvider::VERSION;
    @endphp
    <div class="lv-meta-footer">
        <p class="lv-meta-text">
            <span><span class="lv-meta-label">Memory: </span><b>{{ $__lvMemory }}</b></span>
            @if ($__lvDuration)
                <span class="lv-meta-sep">&middot;</span>
                <span><span class="lv-meta-label">Duration: </span><b>{{ $__lvDuration }}</b></span>
            @endif
            <span class="lv-meta-sep">&middot;</span>
            <span><span class="lv-meta-label">Version: </span><b>v{{ $__lvVersion }}</b></span>
        </p>
        <a href="https://www.buymeacoffee.com/jeeven" target="_blank" rel="noopener noreferrer" class="lv-coffee-link"
            title="buying me a cup of coffee ❤️">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 9h13a3 3 0 0 1 0 6h-1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                    stroke-linejoin="round" />
                <path d="M4 9h13v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V9Z" stroke="currentColor" stroke-width="1.6"
                    stroke-linecap="round" stroke-linejoin="round" />
                <path d="M8 3.5c-.4.6-.4 1.1 0 1.7M11.5 3.5c-.4.6-.4 1.1 0 1.7" stroke="currentColor" stroke-width="1.6"
                    stroke-linecap="round" />
            </svg>
        </a>
    </div>

    <script src="{{ log_viewer_asset('js/log-viewer.js') }}"></script>
    @stack('scripts')
</body>

</html>