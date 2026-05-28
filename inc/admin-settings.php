<?php
/**
 * Yuino Contact Form: 管理画面（設定ページ）
 *
 * 登録済みフォーム（ycf_register_forms フィルターで登録）ごとに
 * 「宛先・件名・本文」のセクションを自動生成する。
 */

if (!defined('ABSPATH')) {
  exit;
}

const YCF_SETTINGS_PAGE_SLUG = 'yuino-contact-form-settings';
const YCF_SETTINGS_GROUP     = 'yuino_contact_form_group';

add_action('admin_menu', 'ycf_register_settings_menu');
add_action('admin_init', 'ycf_register_settings');
add_action('admin_notices', 'ycf_settings_page_preflight_notice');

function ycf_register_settings_menu() {
  add_options_page(
    'お問い合わせ設定',
    'お問い合わせ設定',
    'manage_options',
    YCF_SETTINGS_PAGE_SLUG,
    'ycf_render_settings_page'
  );
}

function ycf_register_settings() {
  register_setting(YCF_SETTINGS_GROUP, YCF_OPTION_KEY, [
    'type'              => 'array',
    'sanitize_callback' => 'ycf_sanitize_settings',
    'default'           => ycf_get_default_global_settings(),
  ]);

  add_settings_section('ycf_section_smtp', 'SMTP設定', 'ycf_section_smtp_intro', YCF_SETTINGS_PAGE_SLUG);
  ycf_add_field('smtp_host',       'SMTPホスト',           'text',   'ycf_section_smtp', ['placeholder' => 'smtp.example-server.jp']);
  ycf_add_field('smtp_port',       'ポート番号',           'number', 'ycf_section_smtp', ['placeholder' => '465']);
  ycf_add_field('smtp_encryption', '暗号化方式',           'select', 'ycf_section_smtp', [
    'options' => ['ssl' => 'SSL', 'tls' => 'TLS', 'starttls' => 'STARTTLS', 'none' => 'なし'],
  ]);
  ycf_add_field('smtp_username',   '認証ユーザー名',       'text',   'ycf_section_smtp', ['placeholder' => 'info@example.com']);
  ycf_add_field('smtp_from_email', '送信元メールアドレス', 'email',  'ycf_section_smtp', ['placeholder' => 'info@example.com']);
  ycf_add_field('smtp_from_name',  '送信者名（From名）',  'text',   'ycf_section_smtp', ['placeholder' => get_bloginfo('name')]);

  add_settings_section('ycf_section_secrets', '機密情報の状態', 'ycf_section_secrets_intro', YCF_SETTINGS_PAGE_SLUG);

  add_settings_section('ycf_section_turnstile', 'Cloudflare Turnstile', 'ycf_section_turnstile_intro', YCF_SETTINGS_PAGE_SLUG);
  ycf_add_field('turnstile_site_key',   'サイトキー',       'text', 'ycf_section_turnstile', ['placeholder' => '0x4AAAAAAA...']);
  ycf_add_field('turnstile_secret_key', 'シークレットキー', 'text', 'ycf_section_turnstile', ['placeholder' => '0x4AAAAAAB...']);

  // 登録済みフォームごとに「メール設定」セクションを自動生成
  $forms = ycf_get_registered_forms();
  if (empty($forms)) {
    add_settings_section('ycf_section_no_forms', 'フォーム未登録', 'ycf_section_no_forms_intro', YCF_SETTINGS_PAGE_SLUG);
    return;
  }

  foreach ($forms as $form_key => $config) {
    $section_id = 'ycf_section_mail_' . $form_key;
    $section_title = $config['label'] . ' メール設定';

    add_settings_section($section_id, $section_title, function () use ($form_key, $config) {
      printf(
        '<p>%s フォームの管理者通知メール（宛先・件名・本文）と自動返信メール（件名・本文）を設定します。CC・BCCはカンマ区切りで複数指定可能です。</p>',
        esc_html($config['label'])
      );
    }, YCF_SETTINGS_PAGE_SLUG);

    ycf_add_field($form_key . '_admin_to',          '管理者通知 宛先（To）', 'text',     $section_id, ['placeholder' => 'info@example.com']);
    ycf_add_field($form_key . '_admin_cc',          '管理者通知 CC',         'text',     $section_id, ['placeholder' => 'sub@example.com（カンマ区切りで複数指定可）']);
    ycf_add_field($form_key . '_admin_bcc',         '管理者通知 BCC',        'text',     $section_id, ['placeholder' => 'log@example.com']);
    ycf_add_field($form_key . '_admin_subject',     '管理者通知 件名',       'text',     $section_id);
    ycf_add_field($form_key . '_admin_body',        '管理者通知 本文',       'textarea', $section_id, ['rows' => 14, 'form_key' => $form_key]);
    ycf_add_field($form_key . '_autoreply_subject', '自動返信 件名',         'text',     $section_id);
    ycf_add_field($form_key . '_autoreply_body',    '自動返信 本文',         'textarea', $section_id, ['rows' => 14, 'form_key' => $form_key]);
  }
}

function ycf_add_field($key, $label, $type, $section, $extra = []) {
  add_settings_field(
    'ycf_field_' . $key,
    $label,
    'ycf_render_field',
    YCF_SETTINGS_PAGE_SLUG,
    $section,
    array_merge(['key' => $key, 'type' => $type, 'label_for' => 'ycf_input_' . $key], $extra)
  );
}

function ycf_render_field($args) {
  $key   = $args['key'];
  $type  = $args['type'];
  $name  = YCF_OPTION_KEY . '[' . $key . ']';
  $id    = 'ycf_input_' . $key;
  $value = ycf_get_setting($key);

  $placeholder = isset($args['placeholder']) ? esc_attr($args['placeholder']) : '';

  switch ($type) {
    case 'textarea':
      $rows = isset($args['rows']) ? (int) $args['rows'] : 8;
      printf(
        '<textarea id="%s" name="%s" rows="%d" class="large-text code" placeholder="%s">%s</textarea>',
        esc_attr($id),
        esc_attr($name),
        $rows,
        $placeholder,
        esc_textarea($value)
      );
      $form_key = $args['form_key'] ?? '';
      $tokens   = ycf_get_template_tokens_html($form_key);
      echo '<p class="description">利用可能なプレースホルダ: ' . $tokens . '</p>';
      break;

    case 'select':
      $options = isset($args['options']) ? $args['options'] : [];
      printf('<select id="%s" name="%s">', esc_attr($id), esc_attr($name));
      foreach ($options as $val => $label) {
        printf(
          '<option value="%s" %s>%s</option>',
          esc_attr($val),
          selected($value, $val, false),
          esc_html($label)
        );
      }
      echo '</select>';
      break;

    case 'number':
      printf(
        '<input type="number" id="%s" name="%s" value="%s" placeholder="%s" class="small-text" />',
        esc_attr($id),
        esc_attr($name),
        esc_attr($value),
        $placeholder
      );
      break;

    case 'email':
    case 'text':
    default:
      // 件名フィールド（*_admin_subject / *_autoreply_subject）は
      // 通常テキスト欄の倍幅に広げる（プレースホルダ込みの長文が見切れないように）
      $css_class = preg_match('/_(admin|autoreply)_subject$/', $key)
        ? 'large-text'
        : 'regular-text';
      printf(
        '<input type="%s" id="%s" name="%s" value="%s" placeholder="%s" class="%s" />',
        esc_attr($type),
        esc_attr($id),
        esc_attr($name),
        esc_attr($value),
        $placeholder,
        esc_attr($css_class)
      );
      break;
  }
}

/**
 * テンプレートで使えるプレースホルダ一覧の HTML を生成。
 * フォームキーが渡されればそのフォームのフィールドキーも含める。
 */
function ycf_get_template_tokens_html($form_key = '') {
  $common = ['{form_key}', '{form_label}', '{site_name}', '{sent_at}', '{remote_ip}'];

  $field_tokens = [];
  if ($form_key !== '') {
    foreach (ycf_get_form_fields($form_key) as $key => $field) {
      $field_tokens[] = '{' . $key . '}';
      $type = $field['type'] ?? '';
      if (in_array($type, ['radio', 'select', 'checkbox'], true)) {
        $field_tokens[] = '{' . $key . '_label}';
      }
    }
  }

  $all = array_merge($common, $field_tokens);
  $html = array_map(function ($token) {
    return '<code>' . esc_html($token) . '</code>';
  }, $all);
  return implode(' ', $html);
}

function ycf_section_smtp_intro() {
  echo '<p>管理者通知メールの受信先と <strong>同じメールサービス</strong> の SMTP 情報を入力してください。<br>';
  echo '受信先と異なるサーバーの SMTP を使うと、配送経路が噛み合わず行方不明になる場合があります（プラグインの MX プリフライト機能で検出されます）。</p>';
  ?>
  <details style="margin:0.5em 0 1em;background:#f6f7f7;border-left:4px solid #2271b1;padding:0.5em 1em;">
    <summary style="cursor:pointer;font-weight:600;">よく使われる SMTP プロバイダの設定値</summary>
    <table class="widefat striped" style="margin-top:0.5em;">
      <thead>
        <tr><th>プロバイダ</th><th>ホスト</th><th>ポート</th><th>暗号化</th><th>備考</th></tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>Google Workspace（リレー）</strong></td>
          <td><code>smtp-relay.gmail.com</code></td>
          <td>587 / 465</td>
          <td>STARTTLS / SSL</td>
          <td>管理コンソール → アプリ → Gmail → ルーティング で「SMTP リレー サービス」を有効化</td>
        </tr>
        <tr>
          <td><strong>Google Workspace（アプリパスワード）</strong></td>
          <td><code>smtp.gmail.com</code></td>
          <td>587 / 465</td>
          <td>STARTTLS / SSL</td>
          <td>個人ユーザーの2段階認証 → アプリパスワード発行（16桁）</td>
        </tr>
        <tr>
          <td><strong>Microsoft 365</strong></td>
          <td><code>smtp.office365.com</code></td>
          <td><strong>587 のみ</strong></td>
          <td><strong>STARTTLS のみ</strong></td>
          <td>テナント＋ユーザー単位で SMTP AUTH を有効化。MFA 有効ならアプリパスワード必須</td>
        </tr>
        <tr>
          <td><strong>共用サーバー（Xserver/さくら等）</strong></td>
          <td>各社の <code>sv*.example.jp</code> 等</td>
          <td>465 / 587</td>
          <td>SSL / STARTTLS</td>
          <td>受信先のドメインが同じサーバー上にある必要あり（MX プリフライト確認）</td>
        </tr>
      </tbody>
    </table>
    <p style="margin-top:0.5em;font-size:0.9em;">パスワード／アプリパスワードは <code>wp-config.php</code> の <code>YCF_SMTP_PASSWORD</code> 定数に書きます（下のセクション参照）。</p>
  </details>
  <?php
}

function ycf_section_secrets_intro() {
  $smtp_pw_status = ycf_get_smtp_password() !== '' ? '<strong style="color:#0a7c2f">設定済み</strong>' : '<strong style="color:#c00">未設定</strong>';
  echo '<p>SMTPパスワード（<code>wp-config.php</code> の <code>YCF_SMTP_PASSWORD</code> 定数）：' . $smtp_pw_status . '</p>';
  echo '<p class="description">未設定の場合はページ上部のセットアップ案内（STEP 3）を参照してください。</p>';
}

function ycf_section_turnstile_intro() {
  echo '<p><a href="https://www.cloudflare.com/products/turnstile/" target="_blank" rel="noopener">Cloudflare Turnstile</a> のサイトキーとシークレットキーを入力してください。両方が空の場合は Turnstile 検証はスキップされます（フォームはそのまま動作します）。</p>';
}

function ycf_section_no_forms_intro() {
  echo '<p>フォームが登録されていません。ページ上部のセットアップ案内に従って、Claude Code にフォーム構築を依頼してください。</p>';
  echo '<p class="description">Claude Code を使わない場合は <a href="https://github.com/yuino-Yuji/yuino-contact-form#readme" target="_blank" rel="noopener">README の手動セットアップ手順</a> を参照してください（テーマの <code>functions.php</code> に <code>ycf_register_forms</code> フィルターでフォーム定義を登録します）。</p>';
}

function ycf_sanitize_settings($input) {
  $clean = [];
  $current = get_option(YCF_OPTION_KEY, []);
  if (!is_array($current)) {
    $current = [];
  }

  $globals = ycf_get_default_global_settings();
  foreach ($globals as $key => $default) {
    $value = isset($input[$key]) ? $input[$key] : '';
    switch ($key) {
      case 'smtp_port':
        $port = (int) $value;
        $clean[$key] = ($port >= 1 && $port <= 65535) ? (string) $port : $default;
        break;

      case 'smtp_encryption':
        $allowed = ['ssl', 'tls', 'starttls', 'none'];
        $clean[$key] = in_array($value, $allowed, true) ? $value : $default;
        break;

      case 'smtp_from_email':
        $email = sanitize_email($value);
        $clean[$key] = $email ? $email : '';
        break;

      default:
        $clean[$key] = sanitize_text_field($value);
        break;
    }
  }

  // フォーム単位のメール設定
  $defaults_mail = ycf_get_default_form_mail_settings();
  foreach (ycf_get_registered_forms() as $form_key => $config) {
    foreach ($defaults_mail as $sub_key => $default_value) {
      $field_key = $form_key . '_' . $sub_key;
      $value     = isset($input[$field_key]) ? $input[$field_key] : '';

      if (in_array($sub_key, ['admin_body', 'autoreply_body'], true)) {
        $value = wp_kses_post($value);
        $clean[$field_key] = sanitize_textarea_field($value);
      } elseif (in_array($sub_key, ['admin_to', 'admin_cc', 'admin_bcc'], true)) {
        $clean[$field_key] = sanitize_text_field($value);
      } else {
        $clean[$field_key] = sanitize_text_field($value);
      }
    }
  }

  // 既存値で未保存のキーは保持
  $candidate = array_merge($current, $clean);

  // MX プリフライト：宛先と SMTP ホストのドメイン整合性を検査。
  // 問題があれば設定エラーを蓄積し、保存をキャンセルする。
  $mx_issues = ycf_collect_mx_issues($candidate);
  if (!empty($mx_issues)) {
    foreach ($mx_issues as $i => $msg) {
      add_settings_error(
        YCF_OPTION_KEY,
        'ycf_mx_issue_' . $i,
        $msg,
        'error'
      );
    }
    add_settings_error(
      YCF_OPTION_KEY,
      'ycf_mx_block',
      '上記の MX ミスマッチが検出されたため、設定は保存されませんでした。SMTPホストか宛先アドレスを見直してください（受信先のメールが実際に住んでいるサーバーの SMTP を使う必要があります）。',
      'error'
    );
    return $current;
  }

  return $candidate;
}

/**
 * 設定ページを開いたタイミングで、現在保存されている設定に対しても MX チェックを実行。
 * （保存時の check だけでは "クライアントが直接DBを書き換えた" 等の経路を拾えないため）
 */
function ycf_settings_page_preflight_notice() {
  if (!function_exists('get_current_screen')) {
    return;
  }
  $screen = get_current_screen();
  if (!$screen || $screen->id !== 'settings_page_' . YCF_SETTINGS_PAGE_SLUG) {
    return;
  }

  $issues = ycf_collect_mx_issues(ycf_get_settings());
  if (empty($issues)) {
    return;
  }

  echo '<div class="notice notice-error"><p><strong>MX ミスマッチが検出されました（このまま運用するとフォームの管理者通知が届かない可能性があります）：</strong></p><ul style="margin-left:1.5em;list-style:disc;">';
  foreach ($issues as $msg) {
    echo '<li>' . esc_html($msg) . '</li>';
  }
  echo '</ul><p>SMTPホストか宛先アドレスを見直してください。受信先メールアドレスのドメインの MX が指す先と一致する SMTP サーバーを使う必要があります。</p></div>';
}

/**
 * セットアップ進捗の現在状態を判定する。
 * 戻り値：'forms_unregistered' | 'smtp_unset' | 'smtp_password_unset' | 'mail_body_default' | 'ready'
 */
function ycf_get_onboarding_state() {
  $forms = ycf_get_registered_forms();
  if (empty($forms)) {
    return 'forms_unregistered';
  }

  $smtp_host = trim((string) ycf_get_setting('smtp_host'));
  if ($smtp_host === '') {
    return 'smtp_unset';
  }

  if (ycf_get_smtp_password() === '') {
    return 'smtp_password_unset';
  }

  // メール本文がプラグイン同梱のデモ初期値のままかどうか
  $defaults_mail = ycf_get_default_form_mail_settings();
  foreach (array_keys($forms) as $form_key) {
    $admin_body     = (string) ycf_get_setting($form_key . '_admin_body');
    $autoreply_body = (string) ycf_get_setting($form_key . '_autoreply_body');
    if ($admin_body === $defaults_mail['admin_body'] || $autoreply_body === $defaults_mail['autoreply_body']) {
      return 'mail_body_default';
    }
  }

  return 'ready';
}

/**
 * 状態に応じたセットアップ案内パネルを出力する。
 * 「次にユーザーが取るべき行動」を、Claude Code への引き継ぎを主役にして提示。
 */
function ycf_render_onboarding_panel() {
  $state = ycf_get_onboarding_state();
  $panels = [
    'forms_unregistered' => [
      'class'   => 'notice notice-info',
      'title'   => '🚀 セットアップ STEP 1 / 4：お問い合わせフォームを構築する',
      'body'    => '<p>Claude Code に次のように依頼してください：</p>'
                 . '<blockquote style="background:#f6f7f7;border-left:4px solid #2271b1;padding:12px 16px;margin:8px 0;"><strong>「Yuino Contact Form を使って、お問い合わせフォームを作って」</strong></blockquote>'
                 . '<p>CC が <code>functions.php</code> へのフォーム定義追加、テンプレートファイル作成、固定ページ作成までを自動で行います。</p>'
                 . '<p class="description">CC を使わない場合は <a href="https://github.com/yuino-Yuji/yuino-contact-form#readme" target="_blank" rel="noopener">README の手動セットアップ手順</a> を参照してください。</p>',
    ],
    'smtp_unset' => [
      'class'   => 'notice notice-info',
      'title'   => '📧 セットアップ STEP 2 / 4：SMTP 情報を設定する',
      'body'    => '<p>レンタルサーバーから提供されている SMTP アカウント情報を、下の「SMTP設定」セクションに入力してください。</p>'
                 . '<p class="description">Claude Code に <strong>「SMTP 情報を設定して」</strong> と依頼することも可能です（CC が必要な情報を尋ねます）。</p>',
    ],
    'smtp_password_unset' => [
      'class'   => 'notice notice-warning',
      'title'   => '🔐 セットアップ STEP 3 / 4：SMTP パスワードを wp-config.php に定義する',
      'body'    => '<p>SMTP パスワードは DB ではなく <code>wp-config.php</code> に定数として直接記述します。Claude Code のチャット履歴に実パスワードが残らないよう、<strong>あなた自身が <code>wp-config.php</code> を直接編集</strong>してください（CC 経由で書かないことが推奨フローです）。</p>'
                 . '<p>以下の1行を <code>wp-config.php</code> の <code>/* That\'s all, stop editing! */</code> の上に追記：</p>'
                 . '<blockquote style="background:#fcf9e8;border-left:4px solid #dba617;padding:12px 16px;margin:8px 0;font-family:monospace;">'
                 . 'define( \'YCF_SMTP_PASSWORD\', \'実際のSMTPパスワード\' );'
                 . '</blockquote>'
                 . '<p>編集後にこのページを再読込すると次の STEP に進みます。</p>'
                 . '<p class="description">Cloudflare Turnstile のシークレットキーは管理画面下部の「Cloudflare Turnstile」セクションで入力します（高機密ではないため DB 保存）。</p>',
    ],
    'mail_body_default' => [
      'class'   => 'notice notice-info',
      'title'   => '📝 セットアップ STEP 4 / 4：メール件名・本文を実フォームに合わせて編集する',
      'body'    => '<p>現在、メール件名・本文がプラグイン同梱のデモ初期値のままです。実際のフォーム内容や運用サイト名に合わせて編集してください。</p>'
                 . '<p>Claude Code に次のように依頼することも可能です：</p>'
                 . '<blockquote style="background:#f6f7f7;border-left:4px solid #2271b1;padding:12px 16px;margin:8px 0;"><strong>「フォーム内容とサイト情報に合わせて、管理者通知と自動返信のメール件名・本文を整えて」</strong></blockquote>',
    ],
    'ready' => [
      'class'   => 'notice notice-success',
      'title'   => '✅ 運用準備完了',
      'body'    => ycf_render_ready_form_list(),
    ],
  ];

  $panel = $panels[$state] ?? $panels['ready'];
  printf(
    '<div class="%s" style="padding:16px 20px;margin:20px 0;"><h2 style="margin-top:0;font-size:16px;">%s</h2>%s</div>',
    esc_attr($panel['class']),
    esc_html($panel['title']),
    $panel['body']
  );
}

/**
 * 「運用準備完了」パネル本体：登録済みフォームの一覧とURL。
 */
function ycf_render_ready_form_list() {
  $forms = ycf_get_registered_forms();
  if (empty($forms)) {
    return '<p>登録済みフォームはありません。</p>';
  }
  $items = '';
  foreach ($forms as $form_key => $config) {
    $url = ycf_get_form_url($form_key);
    $items .= sprintf(
      '<li><strong>%s</strong>（%s）：<a href="%s" target="_blank" rel="noopener">%s</a></li>',
      esc_html($config['label']),
      esc_html($form_key),
      esc_url($url),
      esc_html($url)
    );
  }
  return '<p>セットアップは完了しています。登録済みフォーム：</p><ul style="list-style:disc;padding-left:24px;">' . $items . '</ul>'
       . '<p class="description">設定を変更したい場合は、下のセクションから直接編集、または Claude Code に依頼してください。</p>';
}

function ycf_render_settings_page() {
  if (!current_user_can('manage_options')) {
    return;
  }
  ?>
  <div class="wrap">
    <h1>お問い合わせ設定</h1>

    <?php ycf_render_onboarding_panel(); ?>

    <form method="post" action="options.php">
      <?php
      settings_fields(YCF_SETTINGS_GROUP);
      do_settings_sections(YCF_SETTINGS_PAGE_SLUG);
      submit_button('変更を保存');
      ?>
    </form>
  </div>
  <?php
}
