<?php

namespace EndrockTheme\Classes;

class SectionBuilderCore
{
    /**
     * Instancia de la clase (singleton)
     */
    private static $instance = null;

    /**
     * Instancias de los componentes principales
     */
    public $templates;
    public $sections;
    public $snippets;
    public $compatibility;
    public $builder; // Agregar esta línea

    /**
     * Constructor
     */
    public function __construct()
    {
        // Inicializar una sola vez
        if (self::$instance) {
            return self::$instance;
        }

        self::$instance = $this;
    }

    public function find_snippet_file($name){
        return $this->snippets->find_snippet_file($name);
    }

    /**
     * Inicializar el plugin
     */
    public function init()
    {
        // Cargar componentes principales
        $this->load_components();

        // Verificar compatibilidad del tema
        $this->setup_theme_compatibility();

        // Registrar hooks
        $this->register_hooks();

        // Hacer accesible la instancia globalmente para compatibilidad - Agregar este bloque
        global $sections_builder_theme;
        $sections_builder_theme = $this;
    }

    public function find_section_file($section) {
        return $this->sections->find_section_file($section);
    }

    public function find_json_template_file($template){
        return $this->templates->find_json_template_file($template);
    }

    /**
     * Cargar componentes principales
     */
    private function load_components()
    {
        // Cargar componentes principales
        $this->templates = new SectionBuilderTemplates();
        $this->sections = new SectionBuilderSections();
        $this->snippets = new SectionBuilderSnippets();
        $this->compatibility = new SectionBuilderCompatibility();

        // Cargar el builder (solo en admin) - Agregar este bloque
        if (is_admin()) {
            $this->builder = new SectionBuilderBuilder();
        }
    }

    /**
     * Configurar compatibilidad con el tema
     */
    private function setup_theme_compatibility()
    {
        // Verificar si el tema actual tiene soporte para sections-builder
        if (current_theme_supports('sections-builder')) {
            // Obtener la configuración del tema
            $config = get_theme_support('sections-builder');

            if (is_array($config) && !empty($config[0])) {
                // Configurar los directorios personalizados
                $this->setup_custom_directories($config[0]);
            }
        }
    }

    /**
     * Configurar directorios personalizados
     */
    private function setup_custom_directories($config)
    {
        // Directorio de secciones
        if (isset($config['sections_directory'])) {
            $this->sections->set_theme_directory($config['sections_directory']);
        }

        // Directorio de templates
        if (isset($config['templates_directory'])) {
            $this->templates->set_theme_directory($config['templates_directory']);
        }

        // Directorio de snippets
        if (isset($config['snippets_directory'])) {
            $this->snippets->set_theme_directory($config['snippets_directory']);
        }
    }

    /**
     * Registrar hooks
     */
    private function register_hooks()
    {
        // Hooks para templates
        add_filter('template_include', [$this->templates, 'template_include']);

        // Hooks para metaboxes
        if (is_admin()) {
            add_action('add_meta_boxes', [$this->templates, 'register_meta_boxes']);
            add_action('save_post', [$this->templates, 'save_meta_boxes']);
        }
    }
}