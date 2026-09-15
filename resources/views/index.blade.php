@extends('log-viewer::layout')

@section('title', 'Dashboard')

@section('content')
    <div class="lv-page-header">
        <div>
            <h1 class="lv-title">Log Channels</h1>
            <p class="lv-subtitle">Laravel, web server, application and database logs — all in one place.</p>
        </div>
        <div class="lv-actions">
           
        </div>
    </div>

    <div class="lv-grid">
        @forelse ($channels as $key => $channel)
            <a href="{{ route('log-viewer.show', $key) }}" class="lv-card">
                <div class="lv-card-top">
                    <div>
                        <div class="lv-card-eyebrow">{{ $channel['is_group'] ? 'group' : $channel['driver'] }}</div>
                        <div class="lv-card-title">{{ $channel['label'] }}</div>
                    </div>
                    @if ($channel['is_group'])
                        <span class="lv-badge lv-badge-info">{{ $channel['stats']['channel_count'] ?? 0 }} logs</span>
                    @elseif (!$channel['stats']['exists'])
                        <span class="lv-badge lv-badge-missing">missing</span>
                    @else
                        <span class="lv-badge lv-badge-live">active</span>
                    @endif
                </div>
                <div class="lv-card-stats">
                    <div><span>Size</span> <b class="lv-mono">{{ $channel['stats']['size_human'] ?? '—' }}</b></div>
                    <div>
                        <span>Last activity</span>
                        <b class="lv-mono">{{ optional($channel['stats']['last_modified'])->diffForHumans() ?? '—' }}</b>
                    </div>
                </div>
            </a>
        @empty
            <div class="lv-empty">No channels configured yet — add some in <code>config/log-viewer.php</code>.</div>
        @endforelse
    </div>
@endsection
