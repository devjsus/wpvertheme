<?php
namespace EndrockTheme\Classes;

class Customize
{
    public function __construct()
    {
        /** Enganchamos las opciones del personalizador */
        add_action('customize_register', array($this, "themeCustomizerAdditions"));
    }

    public function themeCustomizerAdditions($wp_customize)
    {
        /** Agregamos la sección de datos de contacto */
        $wp_customize->add_section('endrock_base_theme_contact_data', [
            'priority' => 160,
            'title' => __('Contact data', 'endrock_base_theme'),
            'panel' => '',
            'capability' => 'edit_theme_options',
        ]);

        /** Agregamos la sección de info para reCaptcha */
        $wp_customize->add_section('endrock_base_theme_recaptcha_data', [
            'priority' => 161,
            'title' => __('Google reCaptcha data', 'endrock_base_theme'),
            'panel' => '',
            'capability' => 'edit_theme_options',
        ]);

        /*
         * Agregamos todas las opciones que el theme debe manejar
         */

        /** Añadimos la opción para especificar la dirección */
        $wp_customize->add_setting('endrock_base_theme_address', array(
            'type' => 'theme_mod',
            'capability' => 'edit_theme_options',
            'theme_supports' => '',
            'default' => '',
            'transport' => 'refresh',
            'sanitize_callback' => 'sanitize_text_field',
        ));

        /** Añadimos la opción para especificar el teléfono */
        $wp_customize->add_setting('endrock_base_theme_phone', array(
            'type' => 'theme_mod',
            'capability' => 'edit_theme_options',
            'theme_supports' => '',
            'default' => '',
            'transport' => 'refresh',
            'sanitize_callback' => 'sanitize_text_field',
        ));

        /** Añadimos la opción para especificar el correo */
        $wp_customize->add_setting('endrock_base_theme_email', array(
            'type' => 'theme_mod',
            'capability' => 'edit_theme_options',
            'theme_supports' => '',
            'default' => '',
            'transport' => 'refresh',
            'sanitize_callback' => 'sanitize_email',
        ));

        /** Añadimos la opción para especificar el API Key de reCaptcha */
        $wp_customize->add_setting('endrock_base_theme_recaptcha_apikey', array(
            'type' => 'theme_mod',
            'capability' => 'edit_theme_options',
            'theme_supports' => '',
            'default' => '',
            'transport' => 'refresh',
            'sanitize_callback' => 'sanitize_key',
        ));

        /** Añadimos la opción para especificar el secret de reCaptcha */
        $wp_customize->add_setting('endrock_base_theme_recaptcha_secret', array(
            'type' => 'theme_mod',
            'capability' => 'edit_theme_options',
            'theme_supports' => '',
            'default' => '',
            'transport' => 'refresh',
            'sanitize_callback' => 'sanitize_text_field',
        ));

        /*
         * Ahora añadimos los controles para esas secciones
         */

        /** Añadimos la opción para especificar la dirección */
        $wp_customize->add_control('endrock_base_theme_address', array(
            'type' => 'text',
            'priority' => 0,
            'section' => 'endrock_base_theme_contact_data',
            'label' => __('Address', 'endrock_base_theme'),
            'description' => __('Write the company address', 'endrock_base_theme'),
            'input_attrs' => array(
                'placeholder' => __('Example: Cra 1 #1-01, Bogotá', 'endrock_base_theme'),
            ),
        ));

        /** Añadimos la opción para especificar el teléfono */
        $wp_customize->add_control('endrock_base_theme_phone', array(
            'type' => 'tel',
            'priority' => 1,
            'section' => 'endrock_base_theme_contact_data',
            'label' => __('Phone number', 'endrock_base_theme'),
            'description' => __('Write the company phone number', 'endrock_base_theme'),
            'input_attrs' => array(
                'placeholder' => __('Example: +57 1 999 5522', 'endrock_base_theme'), //TODO: Verificar cuales caracteres admite un campo tel, si no, dejar como text
            ),
        ));

        /** Añadimos la opción para especificar el correo */
        $wp_customize->add_control('endrock_base_theme_email', array(
            'type' => 'email',
            'priority' => 2,
            'section' => 'endrock_base_theme_contact_data',
            'label' => __('Email', 'endrock_base_theme'),
            'description' => __('Write the company email', 'endrock_base_theme'),
            'input_attrs' => array(
                'placeholder' => __('Example: info@endrock.com', 'endrock_base_theme'),
            ),
        ));

        /** Añadimos la opción para especificar el API Key de reCaptcha */
        $wp_customize->add_control('endrock_base_theme_recaptcha_apikey', array(
            'type' => 'text',
            'priority' => 0,
            'section' => 'endrock_base_theme_recaptcha_data',
            'label' => __('reCaptcha API Key', 'endrock_base_theme'),
            'description' => sprintf(__('Write the api key obtained from google, to gat an API key go to <a href="%s" target="_blank" rel="no-follow">here</a>', 'endrock_base_theme'), 'https://g.co/recaptcha/v3'),
            'input_attrs' => array(
                'placeholder' => __('reCaptcha V3 API Key', 'endrock_base_theme'),
            ),
        ));

        /** Añadimos la opción para especificar el correo */
        $wp_customize->add_control('endrock_base_theme_recaptcha_secret', array(
            'type' => 'text',
            'priority' => 1,
            'section' => 'endrock_base_theme_recaptcha_data',
            'label' => __('reCaptcha secret', 'endrock_base_theme'),
            'description' => __('Write the reCaptcha secret obtained from Google reCaptcha', 'endrock_base_theme'),
            'input_attrs' => array(
                'placeholder' => __('reCaptcha V3 Secret', 'endrock_base_theme'),
            ),
        ));
    }
}
