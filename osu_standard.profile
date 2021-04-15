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
 */
function osu_standard_default_modules(array &$install_state) {
//  \Drupal::service('module_installer')->install([
//    'osu_groups',
//    'osu_groups_basic_group',
//  ], TRUE);
}
