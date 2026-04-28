<x-mail::message>
# Підтвердіть вашу email-адресу

Ви підписались на відстеження ціни оголошення на OLX.

@if($listing->title)
**{{ $listing->title }}**
@endif

@if($listing->current_price)
Поточна ціна: **{{ number_format((float) $listing->current_price, 0, '.', ' ') }} грн**
@endif

Натисніть кнопку нижче, щоб підтвердити підписку та почати отримувати сповіщення про зміну ціни.

<x-mail::button :url="route('verify', $subscription->token)" color="success">
Підтвердити підписку
</x-mail::button>

Якщо ви не підписувались — просто ігноруйте цей лист.

З повагою,<br>
{{ config('app.name') }}
</x-mail::message>
