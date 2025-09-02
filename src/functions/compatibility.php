<?php

use Timber\Timber;

/**
 * Funciones de compatibilidad para el plugin Sections Builder
 * 
 * Este archivo debe ser incluido directamente en el espacio global
 * para asegurar que las funciones estén disponibles en todo momento.
 */

// Función para renderizar snippets
if (!function_exists('render_snippet')) {
    function render_snippet($name, $data = [], $return = false)
    {
        global $sections_builder_theme;

        if (!isset($sections_builder_theme)) {
            // Registro de error si queremos depurar
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Error: La función render_snippet() fue llamada, pero el plugin Sections Builder no está inicializado.');
            }

            if ($return) {
                return '<!-- Error: Plugin Sections Builder no inicializado -->';
            } else {
                echo '<!-- Error: Plugin Sections Builder no inicializado -->';
                return;
            }
        }

        // Encontrar el archivo de snippet
        $snippet_file = $sections_builder_theme->find_snippet_file($name);

        if (!$snippet_file) {
            $error_message = "Error: El snippet '$name' no existe.";
            if ($return) {
                return $error_message;
            } else {
                echo $error_message;
                return;
            }
        }

        // Hacer que las variables estén disponibles en el snippet
        extract($data, EXTR_SKIP);

        // Capturar salida
        ob_start();
        include $snippet_file;
        $output = ob_get_clean();

        if ($return) {
            return $output;
        } else {
            echo $output;
        }
    }
}

// Función para obtener snippet (sin imprimirlo)
if (!function_exists('get_snippet')) {
    function get_snippet($name, $data = [])
    {
        return render_snippet($name, $data, true);
    }
}

// Función para renderizar una sección
if (!function_exists('sections_theme_sb_render_section')) {
    function sections_theme_sb_render_section($section)
    {
        global $sections_builder_theme;

        if (!isset($sections_builder_theme)) {
            return '<!-- Error: Plugin Sections Builder no inicializado -->';
        }

        if (!isset($section['section_id'])) {
            return '<!-- Error: La sección no tiene ID -->';
        }

        $section_id = $section['section_id'];
        $settings = isset($section['settings']) ? $section['settings'] : [];
        $blocks = isset($section['blocks']) ? $section['blocks'] : [];

        // Buscar archivo de sección
        $section_file = $sections_builder_theme->find_section_file($section_id);

        if (!$section_file) {
            return '<div class="section-not-found">Sección no encontrada: ' . esc_html($section_id) . '</div>';
        }

        ob_start();

        echo '<div class="section section-' . esc_attr($section_id) . '" id="section-' . esc_attr($section['id']) . '">';
        echo '<div class="container">';

        // Incluir archivo de sección
        include $section_file;

        echo '</div>'; // .container
        echo '</div>'; // .section

        return ob_get_clean();
    }
}

// Función para renderizar el template JSON
if (!function_exists('render_json_template')) {
    function render_json_template()
    {
        global $sections_builder_theme;

        if (!isset($sections_builder_theme)) {
            echo '<!-- Error: Plugin Sections Builder no inicializado -->';
            the_content();
            return;
        }

        $post_id = get_the_ID();
        $template_name = get_post_meta($post_id, '_json_template', true);

        if (empty($template_name)) {
            the_content();
            return;
        }

        $template_file = $sections_builder_theme->find_json_template_file($template_name);

        if (!$template_file) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                echo "<!-- Template JSON no encontrado: $template_name -->";
            }
            the_content();
            return;
        }

        $json_content = file_get_contents($template_file);
        $template_data = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($template_data['sections']) || !is_array($template_data['sections'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                echo "<!-- Error en JSON o estructura incorrecta para template: $template_name -->";
            }
            the_content();
            return;
        }

        // Determinar el orden de las secciones
        $sections_order = [];

        if (isset($template_data['order']) && is_array($template_data['order'])) {
            $sections_order = $template_data['order'];
        } else {
            $sections_order = array_keys($template_data['sections']);
        }

        echo '<div class="template-sections">';

        // Renderizar secciones en orden
        foreach ($sections_order as $section_id) {
            if (!isset($template_data['sections'][$section_id])) {
                continue;
            }

            $section = $template_data['sections'][$section_id];
            $section['id'] = $section_id;

            echo sections_theme_sb_render_section($section);
        }

        echo '</div>';
    }
}

// Función para renderizar secciones sin depender del plugin
function sb_render_section_directly($section)
{
    if (!isset($section['section_id'])) {
        return '<p>Error: Section ID missing</p>';
    }

    $section_id = $section['section_id'];
    $settings = isset($section['settings']) ? $section['settings'] : [];
    $blocks = isset($section['blocks']) ? $section['blocks'] : [];

    $section_file = get_template_directory() . "/sections/{$section_id}/{$section_id}.php";
    $twig_file = get_template_directory() . "/sections/{$section_id}/{$section_id}.twig";

    if (!file_exists($section_file) || !file_exists($twig_file)) {
        return "<p>Section file not found: {$section_id}.php</p>";
    }

    if (file_exists($twig_file)) {
        return Timber::compile($twig_file, [
            'section' => $section,
            'settings' => $settings,
            'blocks' => $blocks,
        ]);
    } else {
        ob_start();

        echo '<div class="section section-' . esc_attr($section_id) . '" id="section-' . esc_attr($section['id']) . '">';
        echo '<div class="container">';

        // Incluir el archivo de sección
        include $section_file;

        echo '</div>'; // .container
        echo '</div>'; // .section

        return ob_get_clean();
    }
}

function sb_redirection_template()
{
    if (!is_singular('page')) return;

    $post_id = get_the_ID();
    $template_name = get_post_meta($post_id, '_json_template', true);

    if (empty($template_name)) return;

    // Buscar el archivo JSON
    $json_file = get_template_directory() . '/templates/' . $template_name . '.json';

    if (!file_exists($json_file)) {
        error_log("JSON file not found: $json_file");
        return;
    }

    error_log("Loading JSON template: $template_name");

    $json_content = file_get_contents($json_file);
    $template_data = json_decode($json_content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Invalid JSON in template: $template_name");
        return;
    }

    // Cargar la plantilla directamente
    error_log("Forcing JSON template: $template_name");

    get_header();

    echo '<main id="primary" class="site-main">';

    if (isset($template_data['sections']) && is_array($template_data['sections'])) {
        // Determinar el orden
        $sections_order = isset($template_data['order']) ? $template_data['order'] : array_keys($template_data['sections']);

        echo '<div class="template-sections">';

        foreach ($sections_order as $section_id) {
            if (!isset($template_data['sections'][$section_id])) continue;

            $section = $template_data['sections'][$section_id];
            $section['id'] = $section_id;

            // Renderizar directamente sin depender del plugin
            echo sb_render_section_directly($section);
        }

        echo '</div>';
    } else {
        the_content();
    }

    echo '</main>';

    get_footer();
    exit;
}

/**
 * Renderizador de Secciones basado en URL (Estilo Shopify)
 *
 * Este sistema intercepta cualquier petición a una URL con el parámetro 'sections'
 * y devuelve un JSON con el HTML de las secciones solicitadas en lugar de la página completa.
 */

/**
 * Intercepta las peticiones para detectar si se solicitan secciones específicas
 */
function intercept_sections_request()
{
    // Comprobar si la solicitud incluye el parámetro 'sections'
    if (isset($_GET['sections'])) {
        // Obtener las secciones solicitadas
        $sections = $_GET['sections'];

        // Si no hay secciones especificadas, continuar con la carga normal
        if (empty($sections)) {
            return;
        }

        // Convertir a array si viene como string
        if (is_string($sections)) {
            $sections = explode(',', $sections);
            // Limpiar cada sección para evitar problemas de seguridad
            $sections = array_map('sanitize_key', $sections);
        }

        // Inicializar resultado
        $result = array(
            'sections' => array()
        );

        // Obtener el post/página actual para tener contexto
        global $wp_query, $post;

        // Renderizar cada sección solicitada
        foreach ($sections as $section_id) {
            // Renderizar la sección con el contexto actual
            $content = render_section($section_id, $post, false);

            // Añadir al resultado
            $result['sections'][$section_id] = $content;
        }

        // Establecer cabeceras para JSON
        header('Content-Type: application/json');

        // Devolver resultado y finalizar ejecución
        echo json_encode($result);
        exit;
    }
}
// Usar template_redirect para interceptar antes de cargar la plantilla
add_action('template_redirect', 'intercept_sections_request');

/**
 * Verifica si existe un template part para la sección dada
 * 
 * @param string $section_id ID de la sección
 * @return bool True si existe la sección, false en caso contrario
 */
function section_template_exists($section_id)
{
    // Buscar en snippets si la función render_snippet existe
    if (function_exists('render_snippet') && function_exists('snippet_exists')) {
        if (snippet_exists($section_id)) {
            return true;
        }
    }

    // Buscar en template-parts/sections
    $template_path = locate_template("template-parts/sections/{$section_id}.php");
    if (!empty($template_path)) {
        return true;
    }

    // Buscar en la raíz de template-parts
    $template_path = locate_template("template-parts/{$section_id}.php");
    if (!empty($template_path)) {
        return true;
    }

    return false;
}

/**
 * Renderiza una sección específica y devuelve su contenido HTML
 * 
 * @param string $section_id ID de la sección a renderizar
 * @param WP_Post $post Post para el contexto (opcional)
 * @param bool $preview Indica si estamos en modo previsualización
 * @return string Contenido HTML de la sección
 */
function render_section($section_id, $post = null, $preview = false)
{
    // Iniciar buffer de salida
    ob_start();

    // Preparar datos para la sección
    $section_data = get_section_data($section_id, $post, $preview);

    // Intentar renderizar la sección
    $rendered = false;

    // Verificar si podemos usar render_snippet para esta sección
    if (function_exists('render_snippet') && function_exists('snippet_exists') && snippet_exists($section_id)) {
        // Renderizar el snippet
        render_snippet($section_id, $section_data, true);
        $rendered = true;
    }
    // Buscar en template-parts/sections
    elseif (locate_template("template-parts/sections/{$section_id}.php") !== '') {
        // Extraer datos como variables para que estén disponibles en el template
        extract($section_data);

        // Incluir el template part
        get_template_part('template-parts/sections/' . $section_id);
        $rendered = true;
    }
    // Buscar en la raíz de template-parts
    elseif (locate_template("template-parts/{$section_id}.php") !== '') {
        // Extraer datos como variables para que estén disponibles en el template
        extract($section_data);

        // Incluir el template part
        get_template_part('template-parts/' . $section_id);
        $rendered = true;
    }

    // Si no se encontró la sección, mostrar un mensaje de error
    if (!$rendered) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo "<!-- Sección '{$section_id}' no encontrada -->";
        }
    }

    // Capturar la salida y limpiar el buffer
    $content = ob_get_clean();

    // Aplicar filtros para permitir modificaciones
    $content = apply_filters('theme_section_render', $content, $section_id, $post, $preview);

    return $content;
}

/**
 * Obtiene los datos necesarios para renderizar una sección
 * 
 * @param string $section_id ID de la sección
 * @param WP_Post $post Post para el contexto (opcional)
 * @param bool $preview Indica si estamos en modo previsualización
 * @return array Datos para la sección
 */
function get_section_data($section_id, $post = null, $preview = false)
{
    $data = array();

    // Datos básicos disponibles para todas las secciones
    $data['section_id'] = $section_id;
    $data['is_preview'] = $preview;

    // Si tenemos un post, añadir datos del post
    if ($post) {
        $data['post_id'] = $post->ID;
        $data['post_title'] = get_the_title($post);
        $data['post_content'] = $post->post_content;
        $data['post_excerpt'] = get_the_excerpt($post);
        $data['post_thumbnail'] = get_the_post_thumbnail_url($post);
    }

    // Obtener plantilla JSON asociada a la sección, si existe
    $template_json = get_section_template($section_id, $post);
    if ($template_json) {
        $data = array_merge($data, $template_json);
    }

    // Permitir que otros plugins o temas añadan datos
    return apply_filters('theme_section_data', $data, $section_id, $post, $preview);
}

/**
 * Obtiene la plantilla JSON asociada a una sección
 * 
 * @param string $section_id ID de la sección
 * @param WP_Post $post Post para el contexto (opcional)
 * @return array Datos de la plantilla (vacío si no hay)
 */
function get_section_template($section_id, $post = null)
{
    $template_data = array();

    // Si tenemos un post, intentamos obtener la plantilla asociada
    if ($post) {
        $template_name = get_post_meta($post->ID, '_json_template', true);

        if ($template_name) {
            // Obtener la plantilla del sistema de templates
            $template = get_template_json($template_name);

            if ($template && isset($template['sections'])) {
                // Buscar la sección específica en la plantilla
                foreach ($template['sections'] as $section_key => $section) {
                    if (isset($section['section_id']) && $section['section_id'] === $section_id) {
                        // Encontrada la sección, usar sus datos
                        $template_data = $section;
                        break;
                    }
                }
            }
        }
    }

    return $template_data;
}

/**
 * Obtiene una plantilla JSON del sistema de templates
 * 
 * @param string $template_name Nombre de la plantilla
 * @return array|null Datos de la plantilla o null si no existe
 */
function get_template_json($template_name)
{
    $templates_dir = get_template_directory() . '/templates/';
    $template_file = $templates_dir . sanitize_file_name($template_name) . '.json';

    if (file_exists($template_file)) {
        $template_json = file_get_contents($template_file);
        if ($template_json) {
            return json_decode($template_json, true);
        }
    }

    return null;
}
