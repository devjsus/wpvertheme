<?php

namespace EndrockTheme\Classes;

use Timber\Timber;

class Shortcodes
{
    public function __construct()
    {
        add_shortcode('custom-search', array($this, 'searchShortcode'));
    }

    public function searchShortcode()
    {
        Timber::render('sections/search-form.twig');
    }
}
