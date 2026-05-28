<?php
/**
 * Yuino Contact Form: 確認画面パーシャル
 *
 * @var string $form  登録済みフォームキー
 * @var array  $data  入力済みデータ
 */

if (!defined('ABSPATH')) {
  exit;
}

$args = $args ?? [];
$form = $args['form'] ?? '';
$data = $args['data'] ?? [];

if (!ycf_form_exists($form)) {
  return;
}

$fields = ycf_get_form_fields($form);

$submit_action_url = admin_url('admin-post.php');
$back_action_url   = admin_url('admin-post.php');
?>

<div class="ContactForm ContactForm--confirm" data-ycf-form="<?php echo esc_attr($form); ?>" data-ycf-step="confirm-view">
  <p class="ContactForm__ConfirmLead">下記の内容で送信します。よろしければ「送信する」を押してください。</p>

  <dl class="ContactForm__ConfirmList">
    <?php foreach ($fields as $key => $field):
      $type  = $field['type'] ?? 'text';
      $value = isset($data[$key]) ? $data[$key] : '';
    ?>
      <div class="ContactForm__ConfirmItem">
        <dt class="ContactForm__ConfirmLabel"><?php echo esc_html($field['label'] ?? $key); ?></dt>
        <dd class="ContactForm__ConfirmValue">
          <?php
          if ($type === 'checkbox') {
            echo $value === '1' ? '同意済み' : '未同意';
          } elseif ($type === 'radio' || $type === 'select') {
            $options = $field['options'] ?? [];
            echo esc_html(isset($options[$value]) ? $options[$value] : $value);
          } elseif ($type === 'textarea') {
            echo nl2br(esc_html($value));
          } else {
            echo esc_html($value);
          }
          ?>
        </dd>
      </div>
    <?php endforeach; ?>
  </dl>

  <form method="post" action="<?php echo esc_url($submit_action_url); ?>" class="ContactForm__SubmitForm" data-ycf-submit-form>
    <?php foreach ($data as $key => $value): ?>
      <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" />
    <?php endforeach; ?>
    <input type="hidden" name="action" value="<?php echo esc_attr(YCF_ACTION_SUBMIT); ?>" />
    <input type="hidden" name="ycf_form" value="<?php echo esc_attr($form); ?>" />
    <?php wp_nonce_field(YCF_NONCE_ACTION_SUBMIT . '_' . $form, '_ycf_nonce'); ?>

    <?php if (ycf_is_turnstile_configured()): ?>
      <div class="ContactForm__TurnstileWrap">
        <div
          class="cf-turnstile"
          data-sitekey="<?php echo esc_attr(ycf_get_setting('turnstile_site_key')); ?>"
          data-theme="light"
          data-language="ja"
        ></div>
      </div>
    <?php endif; ?>

    <div class="ContactForm__ConfirmActions">
      <button type="submit" class="ContactForm__SubmitButton">送信する</button>
    </div>
  </form>

  <form method="post" action="<?php echo esc_url($back_action_url); ?>" class="ContactForm__BackForm">
    <?php foreach ($data as $key => $value): ?>
      <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" />
    <?php endforeach; ?>
    <input type="hidden" name="action" value="<?php echo esc_attr(YCF_ACTION_INPUT); ?>" />
    <input type="hidden" name="ycf_form" value="<?php echo esc_attr($form); ?>" />
    <input type="hidden" name="ycf_step" value="back" />
    <?php wp_nonce_field(YCF_NONCE_ACTION_INPUT . '_' . $form, '_ycf_nonce'); ?>
    <div class="ContactForm__BackActions">
      <button type="submit" class="ContactForm__BackButton">修正する</button>
    </div>
  </form>
</div>
