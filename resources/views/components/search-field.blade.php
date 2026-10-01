{{--
    One search field for every page, so search looks and behaves the same everywhere.
    Attributes other than "class" go on the <input>, so pages keep their own hooks (data-*, name, role...).
    "submit-on-clear" reloads a server-side search (a GET form) when the field is cleared.
--}}
@props([
    'id',
    'value' => '',
    'placeholder' => 'Search',
    'label' => null,
    'submitOnClear' => false,
])
<div {{ $attributes->only('class')->class(['k-search', 'has-value' => filled($value)]) }} data-k-search @if($submitOnClear) data-k-search-submit @endif>
    <svg class="k-search-icon" aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
    <input {{ $attributes->except('class')->merge([
        'id' => $id,
        'type' => 'search',
        'value' => $value,
        'placeholder' => $placeholder,
        'aria-label' => $label ?? $placeholder,
        'autocomplete' => 'off',
        'spellcheck' => 'false',
    ]) }}>
    <button type="button" class="k-search-clear" data-k-search-clear aria-label="Clear search" @unless(filled($value)) hidden @endunless><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 7l10 10M17 7 7 17"></path></svg></button>
    <kbd class="k-search-key" aria-hidden="true" title="Press / to search">/</kbd>
    {{ $slot }}
</div>
