@php
    $record = $getRecord();
    $colorCode = optional($record->color)->code ?? '#9CA3AF';
    $hasPhoto = ! empty($record->catalog_photo_path);
    $photoUrl = $hasPhoto
        ? asset('storage/'.ltrim($record->catalog_photo_path, '/'))
        : null;
@endphp

<div class="w-11 h-11 rounded-md border border-gray-200 overflow-hidden flex-shrink-0"
     style="background-color: {{ $colorCode }}">
    @if ($hasPhoto)
        <img src="{{ $photoUrl }}"
             class="w-full h-full object-cover"
             alt="{{ $record->item_name }}"
             onerror="this.parentElement.style.backgroundImage='none';
                      this.remove();">
    @endif
</div>
