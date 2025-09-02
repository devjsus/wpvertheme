<?php

namespace EndrockTheme\Classes;

class SectionBuilderBuilder {
    // Directorios predeterminados
    private $sections_directory = 'sections';
    private $templates_directory = 'templates';
    private $snippets_directory = 'snippets';
    
    /**
     * Inicializar el plugin
     */
    public function init() {
        // Cargar configuración del tema
        $this->load_theme_config();
        
        // Registrar hooks
        $this->register_hooks();
    }
    
    /**
     * Cargar configuración del tema
     */
    private function load_theme_config() {
        
        // Verificar si el tema tiene soporte para sections-builder
        if (current_theme_supports('sections-builder')) {
            $config = get_theme_support('sections-builder');
            
            
            if (is_array($config) && !empty($config[0])) {
                // Configurar directorios
                if (isset($config[0]['sections_directory'])) {
                    $this->sections_directory = $config[0]['sections_directory'];
                }
                
                if (isset($config[0]['templates_directory'])) {
                    $this->templates_directory = $config[0]['templates_directory'];
                }
                
                if (isset($config[0]['snippets_directory'])) {
                    $this->snippets_directory = $config[0]['snippets_directory'];
                }
            }
        }
    }
    
    /**
     * Registrar hooks
     */
    private function register_hooks() {
        // Hooks para el admin
        if (is_admin()) {
            add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
            add_action('save_post', [$this, 'save_meta_boxes']);
        }
        
        // Hooks para el frontend
        add_filter('template_include', [$this, 'template_include']);
    }
    
    /**
     * Registrar meta boxes
     */
    public function register_meta_boxes() {
        add_meta_box(
            'json_template_selector',
            'Plantilla JSON',
            [$this, 'render_meta_box'],
            'page',
            'side',
            'high'
        );
    }
    
    /**
     * Renderizar meta box
     */
    public function render_meta_box($post) {
        // Verificar nonce
        wp_nonce_field('json_template_selector', 'json_template_selector_nonce');
        
        // Obtener plantilla seleccionada
        $current_template = get_post_meta($post->ID, '_json_template', true);
        
        // Obtener plantillas disponibles
        $templates = $this->get_available_templates();
        
        ?>
        <p>Selecciona una plantilla JSON para esta página:</p>
        <select name="json_template" id="json_template" class="widefat">
            <option value="">Ninguna (usar plantilla estándar)</option>
            <?php foreach ($templates as $template_id => $template) : ?>
                <option value="<?php echo esc_attr($template_id); ?>" <?php selected($current_template, $template_id); ?>>
                    <?php echo esc_html($template['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Las plantillas JSON permiten construir páginas con secciones personalizables.</p>
        <?php
    }
    
    /**
     * Guardar meta box
     */
    public function save_meta_boxes($post_id) {
        // Verificar seguridad
        if (!isset($_POST['json_template_selector_nonce']) || !wp_verify_nonce($_POST['json_template_selector_nonce'], 'json_template_selector')) {
            return;
        }
        
        // Verificar autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('edit_page', $post_id)) {
            return;
        }
        
        // Guardar selección
        if (isset($_POST['json_template'])) {
            update_post_meta($post_id, '_json_template', sanitize_text_field($_POST['json_template']));
        }
    }
    
    /**
     * Incluir plantilla personalizada
     */
    public function template_include($template) {
        // Solo para páginas
        if (!is_singular('page')) {
            return $template;
        }
        
        $post_id = get_the_ID();
        $template_name = get_post_meta($post_id, '_json_template', true);
        
        // Sin plantilla seleccionada
        if (empty($template_name)) {
            return $template;
        }
        
        // Verificar si existe la plantilla JSON
        $json_template_file = $this->find_json_template_file($template_name);
        
        if (!$json_template_file) {
            return $template;
        }
        
        // Buscar archivo de plantilla PHP
        $custom_template = locate_template('json-page-template.php');
        
        if (!$custom_template) {
            $custom_template = SB_THEME_DIR . 'templates/json-page-template.php';
            
            if (!file_exists($custom_template)) {
                return $template;
            }
        }
        
        return $custom_template;
    }
    
    /**
     * Encontrar archivo de plantilla JSON
     */
    public function find_json_template_file($template_name) {
        $possible_paths = [
            // Buscar en el tema
            get_template_directory() . "/{$this->templates_directory}/{$template_name}.json",
            
            // Buscar en el plugin
            SB_THEME_DIR . "templates/{$template_name}.json"
        ];
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return false;
    }
    
    /**
     * Encontrar archivo de sección
     */
    public function find_section_file($section_id) {
        $possible_paths = [
            // Buscar en el tema
            get_template_directory() . "/{$this->sections_directory}/{$section_id}.php",
            get_template_directory() . "/{$this->sections_directory}/{$section_id}/{$section_id}.php",
            
            // Buscar en el plugin
            SB_THEME_DIR . "sections/{$section_id}.php",
            SB_THEME_DIR . "sections/{$section_id}/{$section_id}.php"
        ];
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return false;
    }
    
    /**
     * Encontrar archivo de snippet
     */
    public function find_snippet_file($name) {
        $possible_paths = [
            // Buscar en el tema
            get_template_directory() . "/{$this->snippets_directory}/{$name}.php",
            
            // Buscar en el plugin
            SB_THEME_DIR . "snippets/{$name}.php"
        ];
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return false;
    }
    
    /**
     * Obtener todas las plantillas disponibles
     */
    public function get_available_templates() {
        $templates = [];
        
        // Buscar en el tema
        $theme_dir = get_template_directory() . "/{$this->templates_directory}";
        if (is_dir($theme_dir)) {
            $this->scan_directory_for_templates($theme_dir, $templates);
        }
        
        // Buscar en el plugin
        $plugin_dir = SB_THEME_DIR . "templates";
        if (is_dir($plugin_dir)) {
            $this->scan_directory_for_templates($plugin_dir, $templates);
        }
        
        return $templates;
    }
    
    /**
     * Escanear directorio en busca de plantillas
     */
    private function scan_directory_for_templates($directory, &$templates) {
        $files = glob($directory . '/*.json');
        
        foreach ($files as $file) {
            $template_id = basename($file, '.json');
            
            // Evitar procesar archivos que no son JSON
            if ($template_id === basename($file)) {
                continue;
            }
            
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                $name = isset($data['name']) ? $data['name'] : ucfirst(str_replace(['-', '_'], ' ', $template_id));
                
                $templates[$template_id] = [
                    'name' => $name,
                    'description' => isset($data['description']) ? $data['description'] : '',
                    'path' => $file
                ];
            }
        }
    }
}