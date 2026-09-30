<div class="category-picker">
    <input class="control category-picker-input" id="{{ $id }}" name="category" value="{{ $value }}" placeholder="{{ $placeholder }}" maxlength="80" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="{{ $id }}-options" aria-expanded="false" data-pascal-case required>
    <button class="category-picker-toggle" type="button" aria-label="Show all categories" title="Show all categories" aria-controls="{{ $id }}-options" aria-expanded="false" tabindex="-1"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m7 10 5 5 5-5"></path></svg></button>
    <div id="{{ $id }}-options" class="product-search-options category-picker-options" role="listbox" hidden></div>
</div>
