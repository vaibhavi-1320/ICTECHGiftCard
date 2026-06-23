<?php

namespace Tests\Feature;

use App\Models\GiftCardTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
        $response->assertHeader('Location');
        $this->assertStringContainsString('/shopify/app', (string) $response->headers->get('Location'));
    }

    public function test_admin_shell_returns_successful_response(): void
    {
        $response = $this->get('/admin');

        $response->assertOk();
    }

    public function test_shopify_app_route_returns_successful_response_without_shop(): void
    {
        $response = $this->get('/shopify/app?shop=test-shop.myshopify.com');

        $response->assertRedirect(route('shopify.gift-cards.index', ['shop' => 'test-shop.myshopify.com']));
    }

    public function test_gift_card_index_route_is_available(): void
    {
        $response = $this->get('/shopify/gift-cards');

        $response->assertOk();
        $response->assertSee('Gift Cards');
    }

    public function test_template_index_route_is_available(): void
    {
        $response = $this->get('/shopify/templates');

        $response->assertOk();
        $response->assertSee('Templates');
    }

    public function test_template_create_route_is_available(): void
    {
        $response = $this->get('/shopify/templates/create');

        $response->assertOk();
        $response->assertSee('Create Template');
    }

    public function test_template_edit_route_is_available(): void
    {
        $response = $this->get('/shopify/templates/1/edit');

        $response->assertNotFound();
    }

    public function test_template_update_preserves_existing_metadata_when_fields_are_missing(): void
    {
        $template = GiftCardTemplate::query()->create([
            'name' => 'Birthday',
            'tag' => 'birth',
            'active' => true,
            'body_html' => '<p>Hello</p>',
            'metadata' => [
                'preview_title' => 'OLD TITLE',
                'preview_price' => '99',
                'preview_code' => 'ABC123',
                'custom_text_1' => 'Existing',
                'custom_color_1' => '#123456',
            ],
        ]);

        $response = $this->put('/shopify/templates/'.$template->id.'?shop=test-shop.myshopify.com', [
            'name' => 'Birthday Updated',
            'tag' => 'birth2',
            'active' => 1,
        ]);

        $response->assertRedirect();
        $template->refresh();

        $this->assertSame('OLD TITLE', $template->metadata['preview_title']);
        $this->assertSame('99', $template->metadata['preview_price']);
        $this->assertSame('ABC123', $template->metadata['preview_code']);
        $this->assertSame('Existing', $template->metadata['custom_text_1']);
        $this->assertSame('#123456', $template->metadata['custom_color_1']);
    }
}
