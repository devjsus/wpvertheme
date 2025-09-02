<?php
/**
 *
 * @package  Endrock-Theme
 * @subpackage  Timber
 * @since   Timber 0.1
 */
use Timber\Timber;

$context = Timber::context();
Timber::render('templates/archive.twig', $context);