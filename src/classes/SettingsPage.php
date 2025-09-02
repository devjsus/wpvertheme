<?php
namespace EndrockTheme\Classes;

class SettingsPage
{
    public function __construct()
    {
        $this->registerSettingsPage();
    }

    public function registerSettingsPage()
    {

        if (function_exists('acf_add_options_page')) {
            $parent = acf_add_options_page(array(
                'page_title' => 'Theme Settings',
                'menu_title' => 'Theme Settings',
                'position' => '1',
                'menu_slug' => 'settings',
                'capability' => 'edit_posts',
                'redirect' => false,
                'icon_url' => get_template_directory_uri() . '/static/favicon-16x16.png',
            ));

            $child = acf_add_options_sub_page(array(
                'page_title' => __('Top bar'),
                'menu_title' => __('Top bar'),
                'parent_slug' => $parent['menu_slug'],

            ));
            $child = acf_add_options_sub_page(array(
                'page_title' => __('Footer'),
                'menu_title' => __('Footer'),
                'parent_slug' => $parent['menu_slug'],
            ));

        }
    }
}
