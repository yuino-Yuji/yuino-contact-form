<?php
/**
 * Yuino Contact Form: Cloudflare Turnstile 検証
 */

if (!defined('ABSPATH')) {
  exit;
}

function ycf_verify_turnstile($token, $remote_ip = '') {
  if (!ycf_is_turnstile_configured()) {
    return true;
  }

  if (empty($token)) {
    return false;
  }

  $body = [
    'secret'   => ycf_get_turnstile_secret(),
    'response' => $token,
  ];
  if (!empty($remote_ip)) {
    $body['remoteip'] = $remote_ip;
  }

  $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
    'body'    => $body,
    'timeout' => 10,
  ]);

  if (is_wp_error($response)) {
    error_log('[YuinoContactForm] Turnstile request failed: ' . $response->get_error_message());
    return false;
  }

  $code = wp_remote_retrieve_response_code($response);
  if ($code !== 200) {
    error_log('[YuinoContactForm] Turnstile non-200 response: ' . $code);
    return false;
  }

  $payload = json_decode(wp_remote_retrieve_body($response), true);
  return !empty($payload['success']);
}

function ycf_enqueue_turnstile_script() {
  static $printed = false;
  if ($printed) {
    return;
  }
  $printed = true;
  echo '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}
