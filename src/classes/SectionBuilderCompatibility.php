<?php
namespace EndrockTheme\Classes;

/**
 * Clase para mantener compatibilidad con código existente
 */
class SectionBuilderCompatibility {
    /**
     * Constructor
     */
    public function __construct() {
        $this->define_functions();
    }
    
    /**
     * Definir funciones de compatibilidad
     */
    private function define_functions() {
        // IMPORTANTE: No usamos namespaces para las funciones globales
        
        // Solo definir si no existen ya
        if (!function_exists('render_snippet')) {
            /**
             * Función para renderizar snippets (compatibility)
             */
            function render_snippet($name, $data = [], $return = false) {
                global $sections_builder_theme;
                if (isset($sections_builder_theme) && isset($sections_builder_theme->snippets)) {
                    return $sections_builder_theme->snippets->render($name, $data, $return);
                }
                return '';
            }
            
            // Registrar en el espacio global
            $GLOBALS['render_snippet'] = 'render_snippet';
        }
        
        if (!function_exists('get_snippet')) {
            /**
             * Función para obtener snippets (compatibility)
             */
            function get_snippet($name, $data = []) {
                global $sections_builder_theme;
                if (isset($sections_builder_theme) && isset($sections_builder_theme->snippets)) {
                    return $sections_builder_theme->snippets->render($name, $data, true);
                }
                return '';
            }
            
            // Registrar en el espacio global
            $GLOBALS['get_snippet'] = 'get_snippet';
        }
        
        if (!function_exists('sections_theme_render_section')) {
            /**
             * Función para renderizar secciones (compatibility)
             */
            function sections_theme_render_section($section) {
                global $sections_builder_theme;
                if (isset($sections_builder_theme) && isset($sections_builder_theme->sections)) {
                    return $sections_builder_theme->sections->render_section($section);
                }
                return '';
            }
            
            // Registrar en el espacio global
            $GLOBALS['sections_theme_render_section'] = 'sections_theme_render_section';
        }
        
        if (!function_exists('render_json_template')) {
            /**
             * Función para renderizar templates JSON (compatibility)
             */
            function render_json_template() {
                global $sections_builder_theme;
                if (isset($sections_builder_theme) && isset($sections_builder_theme->templates)) {
                    $sections_builder_theme->templates->render_current_template();
                }
            }
            
            // Registrar en el espacio global
            $GLOBALS['render_json_template'] = 'render_json_template';
        }
    }
}