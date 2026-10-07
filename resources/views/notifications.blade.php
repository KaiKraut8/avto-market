<x-layouts.app :title="__('Notifications')" active="notifications">

<main class="wrap alerts-page">
    <div class="page-title">
        <h1><x-icon name="bell" :size="26" /> {{ __('Notifications') }}</h1>
        @if ($notifications->isNotEmpty())
            <form method="post" action="{{ route('notifications.destroy') }}" data-confirm="{{ __('Clear all notifications?') }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn ghost small">{{ __('Clear all') }}</button>
            </form>
        @endif
    </div>

    @forelse ($notifications as $n)
        @php($a = \App\Support\AlertText::for($n))
        <a @class(['alert-row', 'unread' => isset($unread[$n->id]), 'alert-'.($n->data['kind'] ?? 'other')]) href="{{ $a['url'] }}">
            <span class="alert-icon"><x-icon :name="$a['icon']" :size="18" /></span>
            <span class="alert-text">
                <b>{{ $a['title'] }}</b>
                <span>{{ $a['text'] }}</span>
            </span>
            <time datetime="{{ $n->created_at->toIso8601String() }}">{{ $n->created_at->diffForHumans() }}</time>
        </a>
    @empty
        <div class="empty">
            {{ __('Nothing yet. You will be told here when a car you saved gets a deal.') }}
            @unless ($user->hasBuyerPremium())
                <div class="hint" style="margin-top:.6rem">{!! __('Premium buyers also hear about deals on <b>similar</b> cars and new cars that match their saved searches.') !!} <a href="{{ route('premium.index') }}#buyers">{{ __('See premium for buyers') }}</a></div>
            @endunless
        </div>
    @endforelse
</main>

</x-layouts.app>
