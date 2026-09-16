<?php

namespace Tests\Unit;

use Tests\TestCase;

class LangPathTest extends TestCase
{
    public function test_translations_come_from_the_lang_folder_of_this_version()
    {
        $this->assertSame(base_path('lang'), lang_path());
    }

    public function test_the_texts_of_every_locale_are_readable()
    {
        foreach (['pt-Br', 'en'] as $locale) {
            $this->app->setLocale($locale);
            $this->assertNotSame('text.openDiscord', __('text.openDiscord'));
            $this->assertNotSame('admin.brand', __('admin.brand'));
        }
    }
}
