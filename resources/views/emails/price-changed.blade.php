<x-mail::message>
# Ціна оголошення змінилась!

**{{ $listing->title }}**

<x-mail::table>
| | Ціна |
| :-- | --: |
| Була | {{ number_format($oldPrice, 0, '.', ' ') }} грн |
| Стала | **{{ number_format($newPrice, 0, '.', ' ') }} грн** |
</x-mail::table>

@if($newPrice < $oldPrice)
Ціна знизилась на **{{ number_format($oldPrice - $newPrice, 0, '.', ' ') }} грн**.
@else
Ціна зросла на **{{ number_format($newPrice - $oldPrice, 0, '.', ' ') }} грн**.
@endif

<x-mail::button :url="$listing->url">
Переглянути оголошення
</x-mail::button>

З повагою,<br>
{{ config('app.name') }}
</x-mail::message>
