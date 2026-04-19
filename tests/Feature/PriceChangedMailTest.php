<?php

namespace Tests\Feature;

use App\Mail\PriceChangedMail;
use App\Models\Listing;
use App\Models\Subscription;
use Tests\TestCase;

class PriceChangedMailTest extends TestCase
{
    public function test_renders_unsubscribe_as_plain_text_link_below_primary_button(): void
    {
        $listing = new Listing([
            'title' => 'Test listing',
            'url' => 'https://www.olx.ua/d/uk/obyavlenie/test-IDabc123.html',
        ]);
        $subscription = new Subscription([
            'token' => 'unsubscribe-token',
        ]);

        $html = new PriceChangedMail($listing, 1000.0, 900.0, $subscription)->render();

        $this->assertStringContainsString('href="' . route('unsubscribe', $subscription->token) . '"', $html);
        $this->assertStringContainsString('>Відписатися</a>', $html);
        $this->assertStringContainsString('margin-top: 0; text-align: center;', $html);
        $this->assertStringContainsString('color: #6b7280; font-size: 14px; text-decoration: underline;', $html);
    }
}
