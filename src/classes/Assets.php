<?php

namespace EndrockTheme\Classes;

class Assets
{
    public function __construct()
    {
        // Optimization: Preloaded from head.twig
        // add_action('wp_enqueue_scripts', array($this, 'registerStyles'));
        add_action('wp_enqueue_scripts', array($this, 'registerScripts'));
    }

    public function registerStyles()
    {
        wp_enqueue_style('styles', get_stylesheet_directory_uri() . '/style.css', array(), '1.0.0', 'all');
        wp_enqueue_style('endrock.styles', get_stylesheet_directory_uri() . '/dist/endrock.styles.css', array(), '1.0.0', 'all');
    }

    public function registerScripts()
    {
        wp_register_script('endrock.script', get_template_directory_uri() . '/dist/endrock.scripts.js', array(), '1.0.0', true);
        wp_enqueue_script('endrock.script');
        wp_localize_script('endrock.script', 'ajax_var', array(
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('my-ajax-nonce'),
            'action' => 'ajax_search',
        ));
    }
}
