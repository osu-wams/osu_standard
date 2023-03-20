<?php

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter()
 */
function osu_standard_form_install_configure_form_alter(&$form, FormStateInterface $formState) {
  // Set some placeholder text for this.
  $form['site_information']['site_mail']['#default_value'] = 'noreply@mail.drupal.oregonstate.edu';
  $form['site_information']['site_name']['#attributes']['placeholder'] = t('OSU Site');

  // Account information defaults.
  $form['admin_account']['account']['name']['#default_value'] = 'cws_dpla';
  $form['admin_account']['account']['mail']['#default_value'] = 'noreply@mail.drupal.oregonstate.edu';

  // Date/time settings.
  $form['regional_settings']['site_default_country']['#default_value'] = 'US';
  $form['regional_settings']['date_default_timezone']['#default_value'] = 'America/Los_Angeles';

  // Update notifications.
  $form['update_notifications']['enable_update_status_module']['#default_value'] = 0;
}

/**
 * Implements hook_install_tasks().
 */
function osu_standard_install_tasks(&$install_state) {
  $tasks = [];
  $tasks['osu_standard_default_modules'] = [
    'display_name' => t('Add Modules.'),
    'display' => TRUE,
  ];
  return $tasks;
}

/**
 * Install modules that require a full site to be ready.
 *
 * This allows modules to be installed and not have a hard dependency on the
 * installation profile.
 *
 * @param array $install_state
 *   The Drupal Install State.
 */
function osu_standard_default_modules(array &$install_state) {
  \Drupal::service('module_installer')->install([
    'ckeditor_div_manager',
    'osu_block_types',
    'osu_story',
    'osu_profile',
    'osu_library_hero',
    'osu_library_three_column_cards',
    'osu_library_three_column_equal',
    'osu_library_two_column_25_75',
    'osu_library_two_column_50_50',
  ], TRUE);
}

/**
 * @param array $install_state
 *  The Drupal Install State.
 */
function osu_standard_config_google_search(array &$install_state) {
  $site_host = \Drupal::request()->getHost();
  $site_host = str_replace( ['dev.', 'stage.'], '', $site_host);
  /** @var \Drupal\Core\Config\CachedStorage $config_storage */
  $config_storage = \Drupal::service('config.storage');
  $google_search_config = $config_storage->read('search.page.google_cse_search');
  $google_search_config['configuration']['limit_domain'] = $site_host;
  $config_storage->write('search.page.google_cse_search', $google_search_config);
}
