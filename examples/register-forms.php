<?php
/**
 * Yuino Contact Form: フォーム登録のサンプル
 *
 * このファイルを直接読み込まないこと。
 * 中身をコピーして、利用するテーマの `functions.php` に貼り付けるか、
 * `inc/contact-form.php` のようなパーシャルに切り出して `require_once` する。
 *
 * 「お問い合わせ」と「資料請求」の2フォームを登録する例。
 */

if (!defined('ABSPATH')) {
  exit;
}

add_filter('ycf_register_forms', function ($forms) {
  $common_fields = [
    'name' => [
      'label'        => 'お名前',
      'type'         => 'text',
      'required'     => true,
      'placeholder'  => '例）山田 太郎',
      'maxlength'    => 100,
      'autocomplete' => 'name',
    ],
    'kana' => [
      'label'       => 'フリガナ',
      'type'        => 'text',
      'required'    => true,
      'placeholder' => '例）ヤマダ タロウ',
      'maxlength'   => 100,
    ],
    'connection_method' => [
      'label'    => 'ご連絡方法のご希望',
      'type'     => 'radio',
      'required' => true,
      'default'  => 'mail',
      'options'  => [
        'mail' => 'メールでの連絡を希望する',
        'tel'  => '電話での連絡を希望する',
        'line' => 'LINEでの連絡を希望する',
      ],
    ],
    'contact_value' => [
      'label'     => 'ご連絡先',
      'type'      => 'contact_value',
      'required'  => true,
      'maxlength' => 200,
    ],
    'address' => [
      'label'        => 'ご住所',
      'type'         => 'text',
      'required'     => true,
      'placeholder'  => '例）東京都〇〇区〇〇1-2-3',
      'maxlength'    => 200,
      'autocomplete' => 'street-address',
    ],
    'agree_privacy' => [
      'label'    => '個人情報保護方針への同意',
      'type'     => 'checkbox',
      'required' => true,
    ],
  ];

  $forms['contact'] = [
    'label'       => 'お問い合わせ',
    'page_slug'   => 'contact',
    'thanks_slug' => 'contact-thanks',
    'privacy_url' => home_url('/privacy/'),
    'fields'      => array_merge(
      [
        'purpose' => [
          'label'       => 'ご相談目的',
          'type'        => 'select',
          'required'    => true,
          'options'     => [
            '購入相談'   => '購入相談',
            '賃貸相談'   => '賃貸相談',
            '売却相談'   => '売却相談',
            'その他'     => 'その他',
          ],
          'placeholder' => 'プルダウンで選択する',
        ],
        'message' => [
          'label'       => 'お問い合わせ内容',
          'type'        => 'textarea',
          'required'    => true,
          'placeholder' => 'お問い合わせ内容をご記入ください',
          'maxlength'   => 2000,
        ],
      ],
      $common_fields
    ),
  ];

  $forms['request'] = [
    'label'       => '資料請求',
    'page_slug'   => 'request',
    'thanks_slug' => 'request-thanks',
    'privacy_url' => home_url('/privacy/'),
    'fields'      => $common_fields,
  ];

  return $forms;
});
