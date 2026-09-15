@extends('log-viewer::layout')

@section('title', ($groupMeta['label'] ?? null) ? $groupMeta['label'] . ' — ' . ($channelMeta['label'] ?? $channelKey) : ($channelMeta['label'] ?? $channelKey))

@section('content')
    <div class="lv-shell {{ is_array($sidebarItems) ? 'has-sidebar' : '' }}">
        @if (is_array($sidebarItems))
            <aside class="lv-sidebar">
                <div class="lv-sidebar-header">{{ $groupMeta['label'] ?? $channelKey }}</div>
                <nav class="lv-sidebar-nav">
                    @forelse ($sidebarItems as $key => $item)
                        <a href="{{ route('log-viewer.show', ['channel' => $channelKey, 'sub' => $key]) }}"
                           class="lv-sidebar-link {{ $key === $subKey ? 'is-active' : '' }}">
                            <span class="lv-sidebar-link-label">{{ $item['label'] }}</span>
                            @if (!$item['exists'])
                                <span class="lv-sidebar-dot"></span>
                            @endif
                        </a>
                    @empty
                        <div class="lv-sidebar-empty">No sub-channels found yet.</div>
                    @endforelse
                </nav>
            </aside>
        @endif

        <div class="lv-shell-main">
            <div class="lv-page-header">
                <div>
                    @if ($groupMeta)
                        <a href="{{ route('log-viewer.index') }}" class="lv-back">&larr; All channels</a>
                        <h1 class="lv-title">{{ $channelMeta['label'] ?? $subKey }}</h1>
                    @else
                        <a href="{{ route('log-viewer.index') }}" class="lv-back">&larr; All channels</a>
                        <h1 class="lv-title">{{ $channelMeta['label'] ?? $channelKey }}</h1>
                    @endif
                </div>
                @if (!$isEmptyGroup)
                    <div class="lv-actions">
                        @if (config('log-viewer.ui.auto_refresh.user_toggle', true))
                            <button type="button" class="lv-btn" data-lv-refresh-toggle aria-pressed="false">
                                <span class="lv-refresh-dot" data-lv-refresh-dot></span>
                                <span class="lv-refresh-prefix">Auto-refresh:</span>
                                <span data-lv-refresh-label>Off</span>
                            </button>
                        @endif
                        @if ($canDownload)
                            <a href="{{ route('log-viewer.download', array_filter(['channel' => $channelKey, 'sub' => $subKey])) }}" class="lv-btn">Download</a>
                        @endif
                        @if ($canDelete)
                            <form method="POST" action="{{ route('log-viewer.clear', array_filter(['channel' => $channelKey, 'sub' => $subKey])) }}"
                                  onsubmit="return confirm('This will permanently clear this log. Continue?');">
                                @csrf @method('DELETE')
                                <button class="lv-btn lv-btn-danger">Clear log</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>

            @if ($isEmptyGroup)
                <div class="lv-panel"><div class="lv-empty">No sub-channels found here yet.</div></div>
            @else
                {{-- Filter bar --}}
                <form method="GET" class="lv-filters" data-lv-filters>
                    @if ($subKey)
                        <input type="hidden" name="sub" value="{{ $subKey }}">
                    @endif
                    <div class="lv-field lv-field-search">
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search message…" class="lv-input">
                    </div>
                    <div class="lv-field">
                        <select name="level" class="lv-select">
                            <option value="">All levels</option>
                            @foreach ($levels as $lvl)
                                <option value="{{ $lvl }}" @selected(($filters['level'] ?? '') === (string) $lvl)>{{ $lvl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lv-field">
                        <input type="datetime-local" name="from" value="{{ $filters['from'] ?? '' }}" class="lv-input">
                    </div>
                    <div class="lv-field">
                        <input type="datetime-local" name="to" value="{{ $filters['to'] ?? '' }}" class="lv-input">
                    </div>
                    <div class="lv-filters-buttons">
                        <button class="lv-btn lv-btn-primary">Filter</button>
                        <a href="{{ route('log-viewer.show', array_filter(['channel' => $channelKey, 'sub' => $subKey])) }}" class="lv-btn">Reset</a>
                    </div>
                </form>

                {{-- Entries + pagination: this is the only part auto-refresh
                     swaps, so the filter form above is never disturbed
                     mid-edit (e.g. while typing in the search box). --}}
                <div id="lv-refresh-target">
                    <div class="lv-panel">
                        @forelse ($result['data'] as $i => $entry)
                            @php
                                $levelKey  = strtolower((string) ($entry['level'] ?? 'unknown'));
                                $badgeText = $entry['level_label'] ?? $entry['level'] ?? '—';
                                $badgeKey  = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $badgeText)) ?: $levelKey;
                                $hasDetail = !empty($entry['stack']) || !empty($entry['context']);
                                $targetId  = 'lv-entry-' . $i;
                            @endphp
                            <div class="lv-row">
                                <button type="button" class="lv-row-btn {{ $hasDetail ? 'is-clickable' : '' }}"
                                        @if($hasDetail) data-lv-toggle="{{ $targetId }}" aria-expanded="false" @endif>
                                    <span class="lv-badge lv-badge-{{ $badgeKey }} lv-badge-{{ $levelKey }}">{{ $badgeText }}</span>
                                    <span class="lv-row-message lv-mono">{{ $entry['message'] }}</span>
                                    <span class="lv-row-date lv-mono">{{ $entry['date'] ?? '' }}</span>
                                    @if ($hasDetail)
                                        <span class="lv-chevron">▾</span>
                                    @else
                                        <span></span>
                                    @endif
                                </button>

                                @if ($hasDetail)
                                    <div class="lv-row-detail" id="{{ $targetId }}">
                                        @if (!empty($entry['stack']))
                                            <pre class="lv-pre lv-mono">{{ $entry['stack'] }}</pre>
                                        @endif
                                        @if (!empty($entry['context']))
                                            <div class="lv-context-grid">
                                                @foreach ($entry['context'] as $label => $value)
                                                    @php
                                                        $display = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : (string) $value;
                                                        $long = strlen($display) > 140 || str_contains($display, "\n");
                                                    @endphp
                                                    <div class="{{ $long ? '' : 'lv-context-item' }}" style="{{ $long ? 'grid-column: 1 / -1' : '' }}">
                                                        <div class="lv-context-label">{{ $label }}</div>
                                                        @if ($long)
                                                            <pre class="lv-pre lv-mono" style="max-height:200px;">{{ $display }}</pre>
                                                        @else
                                                            <div class="lv-context-value lv-mono">{{ $display }}</div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="lv-empty">No log entries match your filters.</div>
                        @endforelse
                    </div>

                    {{-- Pagination --}}
                    @include('log-viewer::partials.pagination', ['result' => $result])
                </div>
            @endif
        </div>
    </div>
@endsection
