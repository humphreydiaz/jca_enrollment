<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class StatusBadgeTest extends TestCase
{
    public function test_status_badge_formats_labels_and_infers_semantic_tones(): void
    {
        $review = Blade::render('<x-ui.status-badge status="under_review" />');
        $approved = Blade::render('<x-ui.status-badge status="approved" />');
        $rejected = Blade::render('<x-ui.status-badge status="rejected" />');

        $this->assertStringContainsString('Under Review', $review);
        $this->assertStringContainsString('jca-status-info', $review);
        $this->assertStringContainsString('jca-status-soft', $review);
        $this->assertStringContainsString('jca-status-ready', $approved);
        $this->assertStringContainsString('jca-status-outline', $approved);
        $this->assertStringContainsString('jca-status-danger', $rejected);
        $this->assertStringContainsString('jca-status-soft', $rejected);
    }

    public function test_status_badge_still_supports_custom_content_and_tone(): void
    {
        $html = Blade::render('<x-ui.status-badge tone="warning">Awaiting confirmation</x-ui.status-badge>');

        $this->assertStringContainsString('Awaiting confirmation', $html);
        $this->assertStringContainsString('jca-status-warning', $html);
        $this->assertStringContainsString('jca-status-soft', $html);
    }

    public function test_status_badge_can_preserve_context_specific_wording(): void
    {
        $html = Blade::render('<x-ui.status-badge status="authorized" label="Authorized for exam" />');

        $this->assertStringContainsString('Authorized for exam', $html);
        $this->assertStringContainsString('jca-status-ready', $html);
    }

    public function test_status_badge_supports_explicit_outline_and_flat_variants(): void
    {
        $outline = Blade::render('<x-ui.status-badge status="regular" tone="info" variant="outline" />');
        $flat = Blade::render('<x-ui.status-badge status="archived" />');

        $this->assertStringContainsString('jca-status-outline', $outline);
        $this->assertStringContainsString('jca-status-flat', $flat);
    }
}
