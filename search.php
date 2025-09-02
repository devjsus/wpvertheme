<?php
/**
 * Search results page
 *
 * Methods for TimberHelper can be found in the /lib sub-directory
 *
 * @package  WordPress
 * @subpackage  Timber
 * @since   Timber 0.1
 */
use Timber\Timber;

$context = Timber::context();
Timber::render( 'templates/search.twig', $context );