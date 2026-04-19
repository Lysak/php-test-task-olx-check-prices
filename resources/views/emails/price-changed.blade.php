<x-mail::message>
# {{ $oldPrice === null ? 'Поточна ціна оголошення' : 'Ціна оголошення змінилась!' }}

**{{ $listing->title }}**

@if($oldPrice === null)
<x-mail::table>
| | Ціна |
| :-- | --: |
| Зараз | **{{ number_format($newPrice, 0, '.', ' ') }} грн** |
</x-mail::table>
@else
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
@endif

<x-mail::button :url="$listing->url">
Переглянути оголошення
</x-mail::button>
<div style="margin-top: 0; text-align: center;">
<a href="{{ route('unsubscribe', $subscription->token) }}" style="color: #6b7280; font-size: 14px; text-decoration: underline;">Відписатися</a>
</div>

З повагою,<br>
{{ config('app.name') }}
</x-mail::message>
