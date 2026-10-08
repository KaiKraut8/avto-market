{{-- The chat assistant: a welcome bubble that folds into a draggable circle after 5 seconds; click it for the chat --}}
<div class="assistant" data-assistant data-url="{{ route('assistant') }}" hidden>
    <div class="assistant-bubble" data-assistant-bubble role="status">
        <button type="button" class="assistant-bubble-text" data-assistant-open>{{ __('Welcome. How can I help you today?') }}</button>
        <button type="button" class="assistant-bubble-close" data-assistant-dismiss aria-label="{{ __('Close') }}">&times;</button>
    </div>
    <button type="button" class="assistant-circle" data-assistant-circle aria-label="{{ __('Open the assistant') }}" title="{{ __('KAI assistant') }}">
        <x-icon name="chat" :size="24" />
        <span class="assistant-dot" data-assistant-dot hidden></span>
    </button>
    <section class="assistant-chat" data-assistant-chat role="dialog" aria-label="{{ __('KAI assistant') }}" hidden>
        <header class="assistant-head">
            <span class="assistant-avatar"><x-icon name="chat" :size="16" /></span>
            <div><b>{{ __('KAI assistant') }}</b><small>{{ __('Cars, prices, premium, how buying works') }}</small></div>
            <button type="button" class="assistant-min" data-assistant-close aria-label="{{ __('Hide the chat') }}">&minus;</button>
        </header>
        <div class="assistant-log" data-assistant-log aria-live="polite"></div>
        <form class="assistant-form" data-assistant-form>
            <input type="text" name="message" maxlength="{{ config('assistant.max_message') }}" autocomplete="off" placeholder="{{ __('Ask about a car, a price, premium…') }}" aria-label="{{ __('Your question') }}">
            <button type="submit" class="btn accent" aria-label="{{ __('Send') }}">&#10148;</button>
        </form>
    </section>
    <template data-assistant-i18n data-thinking="{{ __('Thinking…') }}" data-error="{{ __('Something went wrong. Please try again.') }}" data-hello="{{ __('Hello! Ask me about a car, prices, premium or how buying works.') }}"></template>
</div>
