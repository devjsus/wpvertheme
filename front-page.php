<?php
/**
 *
 * @package  Endrock-Theme
 * @subpackage  Timber
 * @since   Timber 0.1
 */
use Timber\Timber;
use EndrockTheme\Classes\SectionBuilderTemplates;

$template = new SectionBuilderTemplates();
$template_content = $template->get_json_template('index');

$context = Timber::context();
$context['order'] = $template_content['order'];
$context['sections'] = $template_content['sections'];

Timber::render('templates/home.twig', $context); 