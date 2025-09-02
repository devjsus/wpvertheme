<?php

namespace EndrockTheme\Classes;

/**
 * Clase para el builder de plantillas JSON
 */
class SectionBuilderBuilder
{
    /**
     * Directorio para los assets del builder
     */
    private $assets_url;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->assets_url = SB_THEME_URL . '/assets/builder/';

        // Registrar hooks para el admin
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);


        // Registrar endpoints REST API - Asegúrate de que esta línea esté presente
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        $this->register_ajax_endpoints();
    }

    /**
     * Registrar endpoints AJAX como alternativa a la REST API
     */
    public function register_ajax_endpoints()
    {
        add_action('wp_ajax_get_templates', [$this, 'ajax_get_templates']);
        add_action('wp_ajax_get_sections', [$this, 'ajax_get_sections']);
        add_action('wp_ajax_get_section_schemas', [$this, 'ajax_get_section_schemas']);
        add_action('wp_ajax_get_template', [$this, 'ajax_get_template']);
        add_action('wp_ajax_save_template', [$this, 'ajax_save_template']);
    }

    /**
     * Endpoint AJAX para obtener plantillas
     */
    public function ajax_get_templates()
    {
        // Verificar nonce
        check_ajax_referer('sections_builder_nonce', 'nonce');

        // Obtener plantillas usando tu método existente
        $templates = []; // Implementa aquí la lógica para obtener plantillas

        // Buscar en el tema
        $theme_dir = get_template_directory() . '/templates';

        // Verificar configuración personalizada
        if (current_theme_supports('sections-builder')) {
            $config = get_theme_support('sections-builder');
            if (is_array($config) && !empty($config[0]) && isset($config[0]['templates_directory'])) {
                $theme_dir = get_template_directory() . '/' . $config[0]['templates_directory'];
            }
        }

        if (is_dir($theme_dir)) {
            $files = glob($theme_dir . '/*.json');

            foreach ($files as $file) {
                $template_id = basename($file, '.json');
                $content = file_get_contents($file);
                $template_data = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $templates[$template_id] = [
                        'name' => isset($template_data['name']) ? $template_data['name'] : ucfirst(str_replace('-', ' ', $template_id)),
                        'description' => isset($template_data['description']) ? $template_data['description'] : '',
                        'path' => $file,
                        'source' => 'theme'
                    ];
                }
            }
        }

        wp_send_json_success($templates);
        exit;
    }

    /**
     * Endpoint AJAX para obtener secciones disponibles
     */
    public function ajax_get_sections()
    {
        // Verificar nonce
        check_ajax_referer('sections_builder_nonce', 'nonce');

        $sections = [];

        // Buscar en el directorio del tema
        $theme_dir = get_template_directory() . '/sections';

        

        // Verificar configuración personalizada
        if (current_theme_supports('sections-builder')) {
            $config = get_theme_support('sections-builder');
            if (is_array($config) && !empty($config[0]) && isset($config[0]['sections_directory'])) {
                $theme_dir = get_template_directory() . '/' . $config[0]['sections_directory'];
            }
        }
        
        if (is_dir($theme_dir)) {
            // Escanear subdirectorios (cada uno es una sección)
            $subdirs = glob($theme_dir . '/*', GLOB_ONLYDIR);
            foreach ($subdirs as $subdir) {
                $section_id = basename($subdir);
                
                // Ignorar directorios que comienzan con _ (helpers, etc)
                if (strpos($section_id, '_') === 0) {
                    continue;
                }
                
                $section_file = $subdir . '/' . $section_id . '.php';
                                
                if (file_exists($section_file)) {
                    // Si existe schema.php, usar su información
                    $schema_file = $subdir . '/schema.php';
                    $section_name = ucfirst(str_replace('-', ' ', $section_id));
                    
                    if (file_exists($schema_file)) {
                        $schema_data = include($schema_file);
                        if (is_array($schema_data) && isset($schema_data['name'])) {
                            $section_name = $schema_data['name'];
                        }
                    }

                    $sections[$section_id] = [
                        'id' => $section_id,
                        'name' => $section_name,
                        'file' => $section_file,
                        'source' => 'theme'
                    ];
                }
            }
        }

        wp_send_json_success($sections);
        exit;
    }

    /**
     * Endpoint AJAX para obtener esquemas de secciones (con soporte para bloques)
     */
    public function ajax_get_section_schemas()
    {
        // Verificar nonce
        check_ajax_referer('sections_builder_nonce', 'nonce');

        $schemas = [];

        // Buscar en las carpetas de secciones del tema
        $theme_dir = get_template_directory() . '/sections';

        // Verificar configuración personalizada
        if (current_theme_supports('sections-builder')) {
            $config = get_theme_support('sections-builder');
            if (is_array($config) && !empty($config[0]) && isset($config[0]['sections_directory'])) {
                $theme_dir = get_template_directory() . '/' . $config[0]['sections_directory'];
            }
        }

        // Para depuración
        error_log('Buscando esquemas en directorio: ' . $theme_dir);

        if (is_dir($theme_dir)) {
            // Obtener subdirectorios (cada uno es una sección)
            $section_dirs = glob($theme_dir . '/*', GLOB_ONLYDIR);

            foreach ($section_dirs as $section_dir) {
                $section_id = basename($section_dir);

                // Verificar si existe schema.php
                $schema_file = $section_dir . '/schema.php';

                if (file_exists($schema_file)) {
                    // Para depuración
                    error_log('Encontrado esquema: ' . $schema_file);

                    // Cargar el schema
                    $schema_data = include($schema_file);

                    if (is_array($schema_data)) {
                        // Transformar el schema al formato esperado por el builder
                        $properties = [];

                        if (isset($schema_data['settings']) && is_array($schema_data['settings'])) {
                            foreach ($schema_data['settings'] as $key => $setting) {
                                $property = [
                                    'type' => $this->map_type($setting['type']),
                                    'title' => isset($setting['label']) ? $setting['label'] : $key,
                                    'description' => isset($setting['description']) ? $setting['description'] : '',
                                    'default' => isset($setting['default']) ? $setting['default'] : ''
                                ];

                                // Para campos de tipo imagen, añadir formato
                                if ($setting['type'] === 'image') {
                                    $property['format'] = 'image';
                                }

                                // Agregar enumeraciones si existen
                                if (isset($setting['options']) && is_array($setting['options'])) {
                                    $property['enum'] = array_keys($setting['options']);
                                }

                                $properties[$key] = $property;
                            }
                        }

                        // Procesar esquemas de bloques
                        $blocks_schema = [];

                        if (isset($schema_data['blocks']) && is_array($schema_data['blocks'])) {
                            foreach ($schema_data['blocks'] as $block_id => $block_data) {
                                $block_properties = [];

                                if (isset($block_data['settings']) && is_array($block_data['settings'])) {
                                    foreach ($block_data['settings'] as $key => $setting) {
                                        $block_property = [
                                            'type' => $this->map_type($setting['type']),
                                            'title' => isset($setting['label']) ? $setting['label'] : $key,
                                            'description' => isset($setting['description']) ? $setting['description'] : '',
                                            'default' => isset($setting['default']) ? $setting['default'] : ''
                                        ];

                                        // Para campos de tipo imagen, añadir formato
                                        if ($setting['type'] === 'image') {
                                            $block_property['format'] = 'image';
                                        }

                                        // Agregar enumeraciones si existen
                                        if (isset($setting['options']) && is_array($setting['options'])) {
                                            $block_property['enum'] = array_keys($setting['options']);
                                        }

                                        $block_properties[$key] = $block_property;
                                    }
                                }

                                $blocks_schema[$block_id] = [
                                    'id' => $block_id,
                                    'name' => isset($block_data['name']) ? $block_data['name'] : ucfirst(str_replace('-', ' ', $block_id)),
                                    'description' => isset($block_data['description']) ? $block_data['description'] : '',
                                    'properties' => $block_properties
                                ];
                            }
                        }

                        // Crear el schema completo
                        $formatted_schema = [
                            'name' => isset($schema_data['name']) ? $schema_data['name'] : ucfirst(str_replace('-', ' ', $section_id)),
                            'description' => isset($schema_data['description']) ? $schema_data['description'] : '',
                            'properties' => $properties,
                            'blocks' => $blocks_schema  // Añadir los esquemas de bloques
                        ];

                        $schemas[$section_id] = [
                            'id' => $section_id,
                            'name' => $formatted_schema['name'],
                            'schema' => $formatted_schema,
                            'file' => $schema_file,
                            'source' => 'theme'
                        ];
                    }
                }
            }
        }

        // También buscar en el plugin como fallback (código similar para plugin)

        // Para depuración
        error_log('Schemas encontrados: ' . count($schemas));

        // Añade estas líneas a tu ajax_get_section_schemas() antes de wp_send_json_success
        error_log('Schemas procesados:');
        foreach ($schemas as $section_id => $schema_data) {
            error_log("- Sección: " . $section_id);
            error_log("  - Propiedades: " . count($schema_data['schema']['properties']));

            if (isset($schema_data['schema']['blocks'])) {
                error_log("  - Bloques: " . count($schema_data['schema']['blocks']));
                foreach ($schema_data['schema']['blocks'] as $block_id => $block) {
                    error_log("    - Bloque: " . $block_id . " (" . $block['name'] . ")");
                }
            } else {
                error_log("  - No tiene bloques definidos");
            }
        }

        wp_send_json_success($schemas);
        exit;
    }

    /**
     * Endpoint AJAX para obtener una plantilla específica
     */
    public function ajax_get_template()
    {
        // Verificar nonce
        check_ajax_referer('sections_builder_nonce', 'nonce');

        // Obtener el nombre de la plantilla
        $template_name = isset($_POST['template_name']) ? sanitize_text_field($_POST['template_name']) : '';

        if (empty($template_name)) {
            wp_send_json_error(['message' => 'Nombre de plantilla no proporcionado']);
            exit;
        }

        // Buscar en el tema
        $theme_dir = get_template_directory() . '/templates';

        // Verificar configuración personalizada
        if (current_theme_supports('sections-builder')) {
            $config = get_theme_support('sections-builder');
            if (is_array($config) && !empty($config[0]) && isset($config[0]['templates_directory'])) {
                $theme_dir = get_template_directory() . '/' . $config[0]['templates_directory'];
            }
        }

        $template_file = $theme_dir . '/' . $template_name . '.json';

        if (!file_exists($template_file)) {
            // Buscar en el plugin como alternativa
            $template_file = SB_THEME_URL . 'templates/' . $template_name . '.json';

            if (!file_exists($template_file)) {
                wp_send_json_error(['message' => 'Plantilla no encontrada']);
                exit;
            }
        }

        $json_content = file_get_contents($template_file);
        $template_data = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => 'Error al decodificar el JSON de la plantilla']);
            exit;
        }

        wp_send_json_success($template_data);
        exit;
    }

    /**
     * Endpoint AJAX para guardar una plantilla
     */
    public function ajax_save_template()
    {
        // Verificar nonce
        check_ajax_referer('sections_builder_nonce', 'nonce');

        // Obtener nombre de plantilla y datos
        $template_name = isset($_POST['template_name']) ? sanitize_text_field($_POST['template_name']) : '';
        $template_data = isset($_POST['template_data']) ? $_POST['template_data'] : '';

        if (empty($template_name)) {
            wp_send_json_error(['message' => 'Nombre de plantilla no proporcionado']);
            exit;
        }

        if (empty($template_data)) {
            wp_send_json_error(['message' => 'Datos de plantilla no proporcionados']);
            exit;
        }

        // Si los datos vienen como string JSON, convertirlos a array
        if (is_string($template_data)) {
            error_log('Datos de plantilla recibidos: ' . substr($template_data, 0, 100) . '...');

            $template_data = json_decode(stripslashes($template_data), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Error JSON: ' . json_last_error_msg());
                wp_send_json_error(['message' => 'Error al decodificar los datos JSON de la plantilla: ' . json_last_error_msg()]);
                exit;
            }
        }

        // Determinar dónde guardar la plantilla
        $theme_dir = get_template_directory() . '/templates';

        // Verificar configuración personalizada
        if (current_theme_supports('sections-builder')) {
            $config = get_theme_support('sections-builder');
            if (is_array($config) && !empty($config[0]) && isset($config[0]['templates_directory'])) {
                $theme_dir = get_template_directory() . '/' . $config[0]['templates_directory'];
            }
        }

        // Registrar información de depuración
        error_log('Directorio del tema: ' . $theme_dir);
        error_log('¿El directorio existe? ' . (is_dir($theme_dir) ? 'Sí' : 'No'));
        error_log('¿El directorio es escribible? ' . (is_writable($theme_dir) ? 'Sí' : 'No'));

        // Asegurarse de que el directorio existe
        if (!is_dir($theme_dir)) {
            $dir_created = wp_mkdir_p($theme_dir);
            error_log('Intento de crear directorio: ' . ($dir_created ? 'Éxito' : 'Fallido'));

            if (!$dir_created) {
                wp_send_json_error(['message' => 'No se pudo crear el directorio de plantillas']);
                exit;
            }
        }

        // Ruta completa al archivo
        $template_file = $theme_dir . '/' . $template_name . '.json';
        error_log('Ruta de archivo completa: ' . $template_file);

        // Convertir datos a JSON formateado
        $json_content = json_encode($template_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Error al codificar JSON: ' . json_last_error_msg());
            wp_send_json_error(['message' => 'Error al codificar datos a JSON: ' . json_last_error_msg()]);
            exit;
        }

        // Guardar archivo
        $result = file_put_contents($template_file, $json_content);

        if ($result === false) {
            error_log('Error al guardar el archivo. ¿Archivo escribible? ' . (is_writable($template_file) ? 'Sí' : 'No'));
            wp_send_json_error(['message' => 'Error al guardar la plantilla en el archivo. Verifica los permisos de escritura.']);
            exit;
        } else {
            error_log('Archivo guardado correctamente. Bytes escritos: ' . $result);
        }

        // Verificar que el archivo se haya creado/actualizado correctamente
        if (file_exists($template_file)) {
            $file_size = filesize($template_file);
            $file_time = filemtime($template_file);
            error_log('Archivo verificado: Existe, tamaño=' . $file_size . ', última modificación=' . date('Y-m-d H:i:s', $file_time));
        } else {
            error_log('ERROR: El archivo no existe después de guardarlo');
        }

        wp_send_json_success([
            'message' => 'Plantilla guardada correctamente',
            'file_path' => $template_file,
            'bytes_written' => $result
        ]);
        exit;
    }

    /**
     * Registrar menú de administración
     */
    public function register_admin_menu()
    {
        add_menu_page(
            __('Template Builder', 'sections-builder'),
            __('Template Builder', 'sections-builder'),
            'manage_options',
            'sections-builder-templates',
            [$this, 'render_admin_page'],
            'dashicons-layout',
            30
        );
    }

    /**
     * Renderizar página de administración
     */
    public function render_admin_page()
    {
?>
        <div class="wrap">
            <h1><?php echo esc_html__('Template Builder', 'sections-builder'); ?></h1>
            <div id="sections-builder-app"></div>
        </div>
<?php
    }

    /**
     * Cargar scripts y estilos para el admin
     */
    public function enqueue_admin_assets($hook)
    {
        // Solo cargar en la página del builder
        if ($hook !== 'toplevel_page_sections-builder-templates') {
            return;
        }

        // Registrar y cargar estilos
        wp_enqueue_style(
            'sections-builder-styles',
            $this->assets_url . 'css/builder.css',
            [],
            SB_VERSION . '-' . time()
        );

        // Cargar media uploader de WordPress
        wp_enqueue_media();

        // Cargar script principal del builder
        wp_enqueue_script(
            'sections-builder-app',
            $this->assets_url . 'js/builder.js',
            ['jquery', 'wp-api-fetch'],
            SB_VERSION,
            true
        );

        // Pasar datos al script
        wp_localize_script(
            'sections-builder-app',
            'sectionsBuilderData',
            [
                'restUrl' => esc_url_raw(rest_url('sections-builder/v1')),
                'nonce' => wp_create_nonce('sections_builder_nonce'),
                //'nonce' => wp_create_nonce('wp_rest'),
                'themeSupport' => current_theme_supports('sections-builder'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'pluginUrl' => SB_THEME_URL,
                'version' => SB_VERSION
            ]
        );
    }

    /**
     * Registrar rutas de la REST API
     */
    public function register_rest_routes()
    {

        $self = $this;

        // Imprime un mensaje en el log para verificar que esta función se ejecuta
        //error_log('SB: Registrando rutas REST');

        // Asegúrate que este namespace coincida con lo que usas en las peticiones
        /*register_rest_route('sections-builder/v1', '/templates', [
            'methods' => 'GET',
            'callback' => [$this, 'get_templates'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        register_rest_route('sections-builder/v1', '/templates/(?P<template_name>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_template'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        register_rest_route('sections-builder/v1', '/templates/(?P<template_name>[a-zA-Z0-9_-]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'save_template'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        register_rest_route('sections-builder/v1', '/section-schemas', [
            'methods' => 'GET',
            'callback' => [$this, 'get_section_schemas'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);*/

        register_rest_route('sections-builder/v1', '/sections', [
            'methods' => 'GET',
            'callback' => function () {
                return ['message' => 'Secciones endpoint funcionando correctamente'];
            },
            'permission_callback' => function () {
                return true; // Todos pueden acceder a este endpoint de prueba
            }
        ]);
    }

    /**
     * Verificar permisos de administrador
     */
    public function check_admin_permission()
    {
        return current_user_can('manage_options');
    }

    /**
     * Obtener todas las plantillas
     */
    public function get_templates()
    {
        $templates = [];

        // Buscar en el tema
        $theme_dir = get_template_directory() . '/templates';

        // Verificar configuración personalizada
        if (current_theme_supports('sections-builder')) {
            $config = get_theme_support('sections-builder');
            if (is_array($config) && !empty($config[0]) && isset($config[0]['templates_directory'])) {
                $theme_dir = get_template_directory() . '/' . $config[0]['templates_directory'];
            }
        }

        if (is_dir($theme_dir)) {
            $files = glob($theme_dir . '/*.json');

            foreach ($files as $file) {
                $template_id = basename($file, '.json');
                $content = file_get_contents($file);
                $template_data = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $templates[$template_id] = [
                        'name' => isset($template_data['name']) ? $template_data['name'] : ucfirst(str_replace('-', ' ', $template_id)),
                        'description' => isset($template_data['description']) ? $template_data['description'] : '',
                        'path' => $file,
                        'source' => 'theme'
                    ];
                }
            }
        }

        // Buscar en el plugin
        $plugin_dir = SB_THEME_URL . 'templates';
        if (is_dir($plugin_dir)) {
            $files = glob($plugin_dir . '/*.json');

            foreach ($files as $file) {
                $template_id = basename($file, '.json');

                // Evitar duplicados (priorizar el tema)
                if (isset($templates[$template_id])) {
                    continue;
                }

                $content = file_get_contents($file);
                $template_data = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $templates[$template_id] = [
                        'name' => isset($template_data['name']) ? $template_data['name'] : ucfirst(str_replace('-', ' ', $template_id)),
                        'description' => isset($template_data['description']) ? $template_data['description'] : '',
                        'path' => $file,
                        'source' => 'plugin'
                    ];
                }
            }
        }

        return rest_ensure_response($templates);
    }

    /**
     * Obtener una plantilla específica
     */
    public function get_template($request)
    {
        $template_name = $request->get_param('template_name');
        $templates_manager = sections_builder()->templates;

        $template_file = $templates_manager->find_json_template_file($template_name);

        if (!$template_file) {
            return new \WP_Error(
                'template_not_found',
                __('La plantilla no existe', 'sections-builder'),
                ['status' => 404]
            );
        }

        $json_content = file_get_contents($template_file);
        $template_data = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error(
                'invalid_json',
                __('El archivo JSON es inválido', 'sections-builder'),
                ['status' => 500]
            );
        }

        return rest_ensure_response($template_data);
    }

    /**
     * Guardar una plantilla
     */
    public function save_template($request)
    {
        $template_name = $request->get_param('template_name');
        $template_data = $request->get_json_params();

        if (empty($template_data)) {
            return new \WP_Error(
                'empty_data',
                __('No se recibieron datos para guardar', 'sections-builder'),
                ['status' => 400]
            );
        }

        // Determinar la ruta de guardado
        $templates_manager = sections_builder()->templates;
        $template_file = $templates_manager->find_json_template_file($template_name);

        // Si no existe, crearlo en el tema (si tiene soporte) o en el plugin
        if (!$template_file) {
            if (current_theme_supports('sections-builder')) {
                $templates_dir = get_template_directory() . '/templates';

                // Obtener directorio personalizado si está configurado
                $config = get_theme_support('sections-builder');
                if (is_array($config) && !empty($config[0]) && isset($config[0]['templates_directory'])) {
                    $templates_dir = get_template_directory() . '/' . $config[0]['templates_directory'];
                }

                // Crear directorio si no existe
                if (!file_exists($templates_dir)) {
                    wp_mkdir_p($templates_dir);
                }

                $template_file = $templates_dir . '/' . $template_name . '.json';
            } else {
                // Guardar en el plugin
                $template_file = SB_THEME_URL . 'templates/' . $template_name . '.json';
            }
        }

        // Guardar el archivo JSON
        $json_content = json_encode($template_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $result = file_put_contents($template_file, $json_content);

        if ($result === false) {
            return new \WP_Error(
                'save_error',
                __('Error al guardar la plantilla', 'sections-builder'),
                ['status' => 500]
            );
        }

        return rest_ensure_response([
            'success' => true,
            'message' => __('Plantilla guardada correctamente', 'sections-builder')
        ]);
    }

    /**
     * Obtener todas las secciones disponibles
     */
    public function get_available_sections()
    {
        $sections = [];

        // Buscar en el directorio del tema
        $theme_dir = get_template_directory() . '/sections';

        // Verificar configuración personalizada
        if (current_theme_supports('sections-builder')) {
            $config = get_theme_support('sections-builder');
            if (is_array($config) && !empty($config[0]) && isset($config[0]['sections_directory'])) {
                $theme_dir = get_template_directory() . '/' . $config[0]['sections_directory'];
            }
        }

        if (is_dir($theme_dir)) {
            // Escanear subdirectorios (cada uno es una sección)
            $subdirs = glob($theme_dir . '/*', GLOB_ONLYDIR);
            foreach ($subdirs as $subdir) {
                $section_id = basename($subdir);

                // Ignorar directorios que comienzan con _ (helpers, etc)
                if (strpos($section_id, '_') === 0) {
                    continue;
                }

                $section_file = $subdir . '/' . $section_id . '.php';

                if (file_exists($section_file)) {
                    // Si existe schema.php, usar su información
                    $schema_file = $subdir . '/schema.php';
                    $section_name = ucfirst(str_replace('-', ' ', $section_id));

                    if (file_exists($schema_file)) {
                        $schema_data = include($schema_file);
                        if (is_array($schema_data) && isset($schema_data['name'])) {
                            $section_name = $schema_data['name'];
                        }
                    }

                    $sections[$section_id] = [
                        'id' => $section_id,
                        'name' => $section_name,
                        'file' => $section_file,
                        'source' => 'theme'
                    ];
                }
            }
        }

        // Buscar en el directorio del plugin como fallback
        $plugin_dir = SB_THEME_URL . 'sections';
        if (is_dir($plugin_dir)) {
            // Escanear subdirectorios (cada uno es una sección)
            $subdirs = glob($plugin_dir . '/*', GLOB_ONLYDIR);
            foreach ($subdirs as $subdir) {
                $section_id = basename($subdir);

                // Ignorar directorios que comienzan con _ (helpers, etc)
                if (strpos($section_id, '_') === 0) {
                    continue;
                }

                $section_file = $subdir . '/' . $section_id . '.php';

                if (file_exists($section_file) && !isset($sections[$section_id])) {
                    // Si existe schema.php, usar su información
                    $schema_file = $subdir . '/schema.php';
                    $section_name = ucfirst(str_replace('-', ' ', $section_id));

                    if (file_exists($schema_file)) {
                        $schema_data = include($schema_file);
                        if (is_array($schema_data) && isset($schema_data['name'])) {
                            $section_name = $schema_data['name'];
                        }
                    }

                    $sections[$section_id] = [
                        'id' => $section_id,
                        'name' => $section_name,
                        'file' => $section_file,
                        'source' => 'plugin'
                    ];
                }
            }
        }

        return rest_ensure_response($sections);
    }


    /**
     * Escanear directorio de secciones
     */
    private function scan_sections_directory($directory, &$sections, $source)
    {
        // Escanear archivos PHP directamente en el directorio
        $files = glob($directory . '/*.php');
        foreach ($files as $file) {
            $section_id = basename($file, '.php');

            // Ignorar archivos que comienzan con _ (helpers, etc)
            if (strpos($section_id, '_') === 0) {
                continue;
            }

            $sections[$section_id] = [
                'id' => $section_id,
                'name' => ucfirst(str_replace('-', ' ', $section_id)),
                'file' => $file,
                'source' => $source
            ];
        }

        // Escanear subdirectorios que pueden contener secciones
        $subdirs = glob($directory . '/*', GLOB_ONLYDIR);
        foreach ($subdirs as $subdir) {
            $section_id = basename($subdir);

            // Ignorar directorios que comienzan con _ (helpers, etc)
            if (strpos($section_id, '_') === 0) {
                continue;
            }

            $section_file = $subdir . '/' . $section_id . '.php';

            if (file_exists($section_file)) {
                $sections[$section_id] = [
                    'id' => $section_id,
                    'name' => ucfirst(str_replace('-', ' ', $section_id)),
                    'file' => $section_file,
                    'source' => $source
                ];
            }
        }
    }

    /**
     * Mapear tipos de campos del formato antiguo al formato nuevo
     */
    private function map_type($type)
    {
        $type_map = [
            'text' => 'string',
            'textarea' => 'string',
            'image' => 'string',  // Mantiene como string pero le añadiremos formato
            'number' => 'number',
            'checkbox' => 'boolean',
            'select' => 'string',
            'color' => 'color',
            'radio' => 'string',
            'file' => 'string',
            'gallery' => 'array',
            'repeater' => 'array'
        ];

        return isset($type_map[$type]) ? $type_map[$type] : 'string';
    }

    /**
     * Obtener los esquemas de las secciones
     */
    public function get_section_schemas()
    {
        $schemas = [];

        // Buscar en las carpetas de secciones del tema
        $theme_dir = get_template_directory() . '/sections';

        // Verificar configuración personalizada
        if (current_theme_supports('sections-builder')) {
            $config = get_theme_support('sections-builder');
            if (is_array($config) && !empty($config[0]) && isset($config[0]['sections_directory'])) {
                $theme_dir = get_template_directory() . '/' . $config[0]['sections_directory'];
            }
        }

        if (is_dir($theme_dir)) {
            // Obtener subdirectorios (cada uno es una sección)
            $section_dirs = glob($theme_dir . '/*', GLOB_ONLYDIR);

            foreach ($section_dirs as $section_dir) {
                $section_id = basename($section_dir);

                // Verificar si existe schema.php
                $schema_file = $section_dir . '/schema.php';

                if (file_exists($schema_file)) {
                    // Cargar el schema
                    $schema_data = include($schema_file);

                    if (is_array($schema_data)) {
                        // Transformar el schema al formato esperado por el builder
                        $properties = [];

                        if (isset($schema_data['settings']) && is_array($schema_data['settings'])) {
                            foreach ($schema_data['settings'] as $key => $setting) {
                                $properties[$key] = [
                                    'type' => $this->map_type($setting['type']),
                                    'title' => isset($setting['label']) ? $setting['label'] : $key,
                                    'description' => isset($setting['description']) ? $setting['description'] : '',
                                    'default' => isset($setting['default']) ? $setting['default'] : ''
                                ];

                                // Agregar enumeraciones si existen
                                if (isset($setting['options']) && is_array($setting['options'])) {
                                    $properties[$key]['enum'] = array_keys($setting['options']);
                                }
                            }
                        }

                        // Crear el schema en el formato esperado
                        $formatted_schema = [
                            'name' => isset($schema_data['name']) ? $schema_data['name'] : ucfirst(str_replace('-', ' ', $section_id)),
                            'description' => isset($schema_data['description']) ? $schema_data['description'] : '',
                            'properties' => $properties
                        ];

                        $schemas[$section_id] = [
                            'id' => $section_id,
                            'name' => $formatted_schema['name'],
                            'schema' => $formatted_schema,
                            'file' => $schema_file,
                            'source' => 'theme'
                        ];
                    }
                }
            }
        }

        // También buscar en el plugin como fallback
        $plugin_dir = SB_THEME_URL . 'sections';
        if (is_dir($plugin_dir)) {
            $section_dirs = glob($plugin_dir . '/*', GLOB_ONLYDIR);

            foreach ($section_dirs as $section_dir) {
                $section_id = basename($section_dir);

                // Verificar si existe schema.php
                $schema_file = $section_dir . '/schema.php';

                if (file_exists($schema_file)) {
                    // Cargar el schema
                    $schema_data = include($schema_file);

                    if (is_array($schema_data) && !isset($schemas[$section_id])) {
                        // Transformar el schema al formato esperado por el builder
                        $properties = [];

                        if (isset($schema_data['settings']) && is_array($schema_data['settings'])) {
                            foreach ($schema_data['settings'] as $key => $setting) {
                                $properties[$key] = [
                                    'type' => $this->map_type($setting['type']),
                                    'title' => isset($setting['label']) ? $setting['label'] : $key,
                                    'description' => isset($setting['description']) ? $setting['description'] : '',
                                    'default' => isset($setting['default']) ? $setting['default'] : ''
                                ];

                                // Agregar enumeraciones si existen
                                if (isset($setting['options']) && is_array($setting['options'])) {
                                    $properties[$key]['enum'] = array_keys($setting['options']);
                                }
                            }
                        }

                        // Crear el schema en el formato esperado
                        $formatted_schema = [
                            'name' => isset($schema_data['name']) ? $schema_data['name'] : ucfirst(str_replace('-', ' ', $section_id)),
                            'description' => isset($schema_data['description']) ? $schema_data['description'] : '',
                            'properties' => $properties
                        ];

                        $schemas[$section_id] = [
                            'id' => $section_id,
                            'name' => $formatted_schema['name'],
                            'schema' => $formatted_schema,
                            'file' => $schema_file,
                            'source' => 'plugin'
                        ];
                    }
                }
            }
        }

        return rest_ensure_response($schemas);
    }

    /**
     * Escanear directorio de esquemas
     */
    private function scan_schemas_directory($directory, &$schemas, $source)
    {
        $files = glob($directory . '/*.json');

        foreach ($files as $file) {
            $schema_id = basename($file, '.json');
            $content = file_get_contents($file);
            $schema_data = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $schemas[$schema_id] = [
                    'id' => $schema_id,
                    'name' => isset($schema_data['name']) ? $schema_data['name'] : ucfirst(str_replace('-', ' ', $schema_id)),
                    'schema' => $schema_data,
                    'file' => $file,
                    'source' => $source
                ];
            }
        }
    }
}
