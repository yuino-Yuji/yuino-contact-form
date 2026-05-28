<?php
/**
 * Yuino Contact Form: サニタイズ
 */

if (!defined('ABSPATH')) {
  exit;
}

function ycf_sanitize_input($form_key, $raw) {
  $fields = ycf_get_form_fields($form_key);
  $clean  = [];

  foreach ($fields as $key => $field) {
    $value = isset($raw[$key]) ? wp_unslash($raw[$key]) : '';
    $type  = isset($field['type']) ? $field['type'] : 'text';

    switch ($type) {
      case 'textarea':
        $clean[$key] = sanitize_textarea_field($value);
        break;

      case 'checkbox':
        $clean[$key] = !empty($value) ? '1' : '';
        break;

      case 'radio':
      case 'select':
        $allowed = isset($field['options']) ? array_keys($field['options']) : [];
        $clean[$key] = in_array($value, $allowed, true) ? $value : '';
        break;

      case 'email':
        $clean[$key] = sanitize_email($value);
        break;

      case 'contact_value':
      case 'text':
      case 'tel':
      default:
        $clean[$key] = sanitize_text_field($value);
        break;
    }
  }

  return $clean;
}
