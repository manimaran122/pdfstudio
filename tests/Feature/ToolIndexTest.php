<?php

namespace Tests\Feature;

use Tests\TestCase;

class ToolIndexTest extends TestCase
{
    public function test_home_shows_hero_most_used_and_every_tool(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Work with PDFs on your own servers.')
            ->assertSee('Drop files to get started')
            ->assertSeeInOrder(['Most used', 'Merge PDF', 'Split PDF', 'Compress PDF', 'PDF to Word', 'All tools'])
            ->assertSee('PDF to Markdown')
            ->assertSee('Files you create will appear here')
            ->assertSee('href="'.route('tools.merge').'"', false)
            ->assertSee('href="'.route('tools.show', 'redact-pdf').'"', false);
    }

    public function test_top_bar_has_menus_search_and_highlights_the_current_tool(): void
    {
        $this->get(route('tools.show', 'split-pdf'))
            ->assertOk()
            ->assertSee('PDF<b class="text-accent">Studio</b>', false)->assertDontSee('Stellar')
            ->assertSee('Search tools')
            ->assertSee('33 tools in 7 categories')
            ->assertSee('aria-current="page"', false)
            ->assertSee('Split PDF settings');
    }

    public function test_merge_page_renders_upload_step(): void
    {
        $this->get(route('tools.merge'))
            ->assertOk()
            ->assertSee('Drop PDF files here')
            ->assertSee('Up to 20 files · 25 MB each · PDF only')
            ->assertSee('Merge PDF settings')
            ->assertSee('No files yet');
    }
}
