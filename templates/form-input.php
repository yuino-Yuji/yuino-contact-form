<?php
/**
 * Yuino Contact Form: 入力画面パーシャル
 *
 * テーマ側で `wp-content/themes/<theme>/yuino-contact-form/form-input.php`
 * に同名ファイルを置けば、このテンプレートを上書きできる。
 *
 * @var string $form    登録済みフォームキー
 * @var array  $data    入力値
 * @var array  $errors  エラーメッセージ
 */

if (!defined('ABSPATH')) {
  exit;
}

$args   = $args ?? [];
$form   = $args['form']   ?? '';
$data   = $args['data']   ?? [];
$errors = $args['errors'] ?? [];

if (!ycf_form_exists($form)) {
  return;
}

$config = ycf_get_form_config($form);
$fields = ycf_get_form_fields($form);

$action_url  = admin_url('admin-post.php');
$nonce_name  = '_ycf_nonce';
$privacy_url = $config['privacy_url'] ?? '';

$default_method = '';
foreach ($fields as $fkey => $fval) {
  if (($fval['type'] ?? '') === 'radio' && $fkey === 'connection_method') {
    $default_method = $fval['default'] ?? 'mail';
    break;
  }
}
$method_value = isset($data['connection_method']) && $data['connection_method'] !== ''
  ? $data['connection_method']
  : $default_method;
?>

<form
  class="ContactForm"
  method="post"
  action="<?php echo esc_url($action_url); ?>"
  data-ycf-form="<?php echo esc_attr($form); ?>"
  data-ycf-step="confirm"
  novalidate
>
  <input type="hidden" name="action" value="<?php echo esc_attr(YCF_ACTION_INPUT); ?>" />
  <input type="hidden" name="ycf_form" value="<?php echo esc_attr($form); ?>" />
  <input type="hidden" name="ycf_step" value="confirm" />
  <?php wp_nonce_field(YCF_NONCE_ACTION_INPUT . '_' . $form, $nonce_name); ?>

  <?php if (!empty($errors['_global'])): ?>
    <div class="ContactForm__GlobalError" role="alert">
      <?php echo esc_html($errors['_global']); ?>
    </div>
  <?php endif; ?>

  <div class="ContactForm__Body">

    <?php foreach ($fields as $key => $field):
      $type      = $field['type'] ?? 'text';
      $field_id  = 'ycf-field-' . $key;
      $value     = isset($data[$key]) ? $data[$key] : '';
      $has_error = !empty($errors[$key]);
      $required  = !empty($field['required']);
    ?>
      <div class="ContactForm__Field <?php echo $has_error ? 'is-error' : ''; ?>" data-ycf-field="<?php echo esc_attr($key); ?>">

        <?php if ($type === 'checkbox'): ?>
          <div class="ContactForm__LabelRow">
            <span class="ContactForm__Label"><?php echo esc_html($field['label'] ?? $key); ?></span>
            <?php if ($required): ?><span class="ContactForm__Required" aria-label="必須">必須</span><?php endif; ?>
          </div>
          <?php
            $is_privacy      = ($key === 'agree_privacy');
            $already_checked = ($value === '1');
            $start_disabled  = $is_privacy && $privacy_url !== '' && !$already_checked;
          ?>
          <div class="ContactForm__Control">
            <label class="ContactForm__Checkbox">
              <input
                type="checkbox"
                id="<?php echo esc_attr($field_id); ?>"
                name="<?php echo esc_attr($key); ?>"
                value="1"
                <?php checked($value, '1'); ?>
                <?php if ($required): ?>required<?php endif; ?>
                data-ycf-agree="<?php echo $is_privacy ? 'true' : 'false'; ?>"
                <?php if ($start_disabled): ?>disabled aria-describedby="ycf-agree-note-<?php echo esc_attr($form); ?>"<?php endif; ?>
              />
              <span class="ContactForm__CheckboxLabel">
                <?php if ($is_privacy && $privacy_url !== ''): ?>
                  <a
                    href="<?php echo esc_url($privacy_url); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="ContactForm__PrivacyLink"
                    data-ycf-privacy-link
                  >個人情報保護方針</a>に同意します
                <?php else: ?>
                  <?php echo esc_html($field['label'] ?? $key); ?>に同意します
                <?php endif; ?>
              </span>
            </label>
            <?php if ($start_disabled): ?>
              <p id="ycf-agree-note-<?php echo esc_attr($form); ?>" class="ContactForm__HelpText" data-ycf-agree-note>
                ※「個人情報保護方針」のリンクをクリックして内容をご確認ください。確認後にチェックが可能になります。
              </p>
            <?php endif; ?>
          </div>

        <?php elseif ($type === 'select'): ?>
          <div class="ContactForm__LabelRow">
            <label class="ContactForm__Label" for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($field['label'] ?? $key); ?></label>
            <?php if ($required): ?><span class="ContactForm__Required" aria-label="必須">必須</span><?php endif; ?>
          </div>
          <div class="ContactForm__Control">
            <select
              id="<?php echo esc_attr($field_id); ?>"
              name="<?php echo esc_attr($key); ?>"
              class="ContactForm__Select"
              <?php if ($required): ?>required<?php endif; ?>
            >
              <option value=""><?php echo esc_html($field['placeholder'] ?? '選択してください'); ?></option>
              <?php foreach (($field['options'] ?? []) as $val => $label): ?>
                <option value="<?php echo esc_attr($val); ?>" <?php selected($value, $val); ?>><?php echo esc_html($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

        <?php elseif ($type === 'radio'): ?>
          <?php $current_radio = ($key === 'connection_method') ? $method_value : $value; ?>
          <div class="ContactForm__LabelRow">
            <span class="ContactForm__Label"><?php echo esc_html($field['label'] ?? $key); ?></span>
            <?php if ($required): ?><span class="ContactForm__Required" aria-label="必須">必須</span><?php endif; ?>
          </div>
          <div class="ContactForm__Control">
            <ul class="ContactForm__RadioList" role="radiogroup" aria-label="<?php echo esc_attr($field['label'] ?? $key); ?>">
              <?php foreach (($field['options'] ?? []) as $val => $label): ?>
                <li class="ContactForm__RadioItem">
                  <label class="ContactForm__Radio">
                    <input
                      type="radio"
                      name="<?php echo esc_attr($key); ?>"
                      value="<?php echo esc_attr($val); ?>"
                      <?php checked($current_radio, $val); ?>
                      <?php if ($key === 'connection_method'): ?>data-ycf-method="<?php echo esc_attr($val); ?>"<?php endif; ?>
                      <?php if ($required): ?>required<?php endif; ?>
                    />
                    <span class="ContactForm__RadioLabel"><?php echo esc_html($label); ?></span>
                  </label>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>

        <?php elseif ($type === 'textarea'): ?>
          <div class="ContactForm__LabelRow">
            <label class="ContactForm__Label" for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($field['label'] ?? $key); ?></label>
            <?php if ($required): ?><span class="ContactForm__Required" aria-label="必須">必須</span><?php endif; ?>
          </div>
          <div class="ContactForm__Control">
            <textarea
              id="<?php echo esc_attr($field_id); ?>"
              name="<?php echo esc_attr($key); ?>"
              class="ContactForm__Textarea"
              rows="<?php echo esc_attr($field['rows'] ?? 6); ?>"
              maxlength="<?php echo esc_attr($field['maxlength'] ?? 2000); ?>"
              placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"
              <?php if ($required): ?>required<?php endif; ?>
            ><?php echo esc_textarea($value); ?></textarea>
          </div>

        <?php elseif ($type === 'contact_value'):
          $meta = ycf_get_contact_value_meta($method_value);
          $help_id = 'ycf-contact-help-' . $form;
        ?>
          <div class="ContactForm__LabelRow">
            <label class="ContactForm__Label" for="<?php echo esc_attr($field_id); ?>" data-ycf-contact-label><?php echo esc_html($field['label'] ?? 'ご連絡先'); ?></label>
            <?php if ($required): ?><span class="ContactForm__Required" aria-label="必須">必須</span><?php endif; ?>
          </div>
          <div class="ContactForm__Control">
            <input
              type="text"
              id="<?php echo esc_attr($field_id); ?>"
              name="<?php echo esc_attr($key); ?>"
              class="ContactForm__Input"
              value="<?php echo esc_attr($value); ?>"
              maxlength="<?php echo esc_attr($field['maxlength'] ?? 200); ?>"
              placeholder="<?php echo esc_attr($meta['placeholder']); ?>"
              inputmode="<?php echo esc_attr($meta['inputmode']); ?>"
              autocomplete="<?php echo esc_attr($meta['autocomplete']); ?>"
              <?php if (!empty($meta['pattern'])): ?>pattern="<?php echo esc_attr($meta['pattern']); ?>"<?php endif; ?>
              <?php if ($required): ?>required<?php endif; ?>
              data-ycf-contact-input
              aria-describedby="<?php echo esc_attr($help_id); ?>"
            />
            <p id="<?php echo esc_attr($help_id); ?>" class="ContactForm__HelpText" data-ycf-contact-help><?php echo esc_html($meta['description']); ?></p>
          </div>

        <?php else: /* text, email, tel など */ ?>
          <?php $input_type = ($type === 'text') ? 'text' : $type; ?>
          <div class="ContactForm__LabelRow">
            <label class="ContactForm__Label" for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($field['label'] ?? $key); ?></label>
            <?php if ($required): ?><span class="ContactForm__Required" aria-label="必須">必須</span><?php endif; ?>
          </div>
          <div class="ContactForm__Control">
            <input
              type="<?php echo esc_attr($input_type); ?>"
              id="<?php echo esc_attr($field_id); ?>"
              name="<?php echo esc_attr($key); ?>"
              class="ContactForm__Input"
              value="<?php echo esc_attr($value); ?>"
              maxlength="<?php echo esc_attr($field['maxlength'] ?? 200); ?>"
              placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"
              <?php if (!empty($field['autocomplete'])): ?>autocomplete="<?php echo esc_attr($field['autocomplete']); ?>"<?php endif; ?>
              <?php if (!empty($field['pattern'])): ?>pattern="<?php echo esc_attr($field['pattern']); ?>"<?php endif; ?>
              <?php if ($required): ?>required<?php endif; ?>
            />
          </div>
        <?php endif; ?>

        <?php if ($has_error): ?>
          <p class="ContactForm__Error" role="alert"><?php echo esc_html($errors[$key]); ?></p>
        <?php endif; ?>

      </div>
    <?php endforeach; ?>

  </div>

  <div class="ContactForm__Submit">
    <button type="submit" class="ContactForm__SubmitButton">確認する</button>
  </div>
</form>
