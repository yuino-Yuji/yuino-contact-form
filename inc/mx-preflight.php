<?php
/**
 * Yuino Contact Form: MX プリフライトチェック
 *
 * SMTPホストと宛先メールアドレスのドメイン関係を検査し、
 * "ローカル配送ハイジャック" のリスクを保存前 / 表示時に検出する。
 *
 * 想定する典型ケース：
 *   - SMTP: sv12345.xserver.jp（XServer）
 *   - 宛先: contact@example.com（example.com の MX は Google Workspace）
 *   → XServer の Postfix が example.com を「自分のドメイン」と認識し
 *     ローカル配送して行方不明になる
 *
 * 判定ロジック：
 *   1. 宛先ドメインの apex（example.co.jp 等の二段TLDも考慮）と
 *      SMTPホストの apex が **異なれば** OK（純粋な外部配送）
 *   2. 同じ apex なら、宛先ドメインの MX を引いて、その中に SMTPホスト
 *      （または同じ apex）が含まれているかを確認
 *   3. MX が SMTP ホストと無関係なら "ローカル配送ハイジャック" 警告
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * 二段TLDのリスト（pragmatic：完全な PSL ではないが日本向けには十分）
 */
function ycf_two_level_tlds() {
  return [
    'co.jp', 'or.jp', 'ne.jp', 'ac.jp', 'ad.jp', 'ed.jp', 'go.jp', 'gr.jp', 'lg.jp',
    'co.uk', 'org.uk', 'ac.uk', 'gov.uk', 'me.uk',
    'com.au', 'net.au', 'org.au', 'edu.au',
    'co.kr', 'or.kr',
    'com.cn', 'net.cn', 'org.cn',
    'com.tw', 'org.tw',
    'co.nz',
  ];
}

/**
 * 主要メールサービスの apex エイリアスグループ。
 *   - SMTPホストと MXターゲットが「同じ運営者の別 apex」のケース（gmail.com / google.com 等）
 *     を誤検知しないようまとめる。
 */
function ycf_apex_alias_groups() {
  return [
    ['gmail.com', 'google.com', 'googlemail.com'],
    ['outlook.com', 'office365.com', 'microsoft.com', 'protection.outlook.com'],
    ['icloud.com', 'mac.com', 'me.com'],
  ];
}

/**
 * 2つのホストが「同じ運営者」とみなせるかを判定
 */
function ycf_hosts_are_siblings($host_a, $host_b) {
  $apex_a = ycf_extract_apex_domain($host_a);
  $apex_b = ycf_extract_apex_domain($host_b);
  if ($apex_a === '' || $apex_b === '') {
    return false;
  }
  if ($apex_a === $apex_b) {
    return true;
  }
  foreach (ycf_apex_alias_groups() as $group) {
    if (in_array($apex_a, $group, true) && in_array($apex_b, $group, true)) {
      return true;
    }
  }
  return false;
}

/**
 * ホスト名から apex ドメインを抽出
 *   sv12345.xserver.jp → xserver.jp
 *   smtp-relay.gmail.com → gmail.com
 *   smtp.office365.com → office365.com
 *   sub.example.co.jp → example.co.jp
 */
function ycf_extract_apex_domain($host) {
  $host = strtolower(trim((string) $host, ". \t\n\r\0\x0B"));
  if ($host === '') {
    return '';
  }
  $parts = explode('.', $host);
  $count = count($parts);
  if ($count < 2) {
    return $host;
  }
  // 二段TLDチェック
  $last_two = implode('.', array_slice($parts, -2));
  if (in_array($last_two, ycf_two_level_tlds(), true) && $count >= 3) {
    return implode('.', array_slice($parts, -3));
  }
  return $last_two;
}

/**
 * SMTP送信元アドレスと宛先メールアドレスの組み合わせを検査
 *
 * 判定基準：
 *   - From のドメイン ≠ 宛先のドメイン → 外部配送になるので OK
 *   - From のドメイン == 宛先のドメイン （= SMTP サーバーがこのドメインを所有していると認識する）
 *     → MX を引いて、SMTP ホスト（同一/同 apex）が含まれているか確認
 *     → 含まれていなければハイジャック警告
 *
 * @return array { ok: bool, reason: string, mx: string[] }
 */
function ycf_check_mx_match($smtp_host, $smtp_from_email, $recipient_email) {
  $smtp_host       = trim((string) $smtp_host);
  $smtp_from_email = trim((string) $smtp_from_email);
  $recipient_email = trim((string) $recipient_email);

  if ($smtp_host === '' || $smtp_from_email === '' || $recipient_email === ''
      || !is_email($smtp_from_email) || !is_email($recipient_email)) {
    return ['ok' => true, 'reason' => '', 'mx' => []];
  }

  $from_at = strrpos($smtp_from_email, '@');
  $to_at   = strrpos($recipient_email, '@');
  if ($from_at === false || $to_at === false) {
    return ['ok' => true, 'reason' => '', 'mx' => []];
  }
  $from_domain      = strtolower(substr($smtp_from_email, $from_at + 1));
  $recipient_domain = strtolower(substr($recipient_email, $to_at + 1));

  // 送受信が「同じ apex（親ドメイン）」を共有していなければ、ハイジャックは起きない。
  // サブドメインを変えても、SMTP サーバーが親ドメインを管理していれば、
  //   親ドメインへの RCPT は依然としてローカル配送されるため、apex 比較で判定する。
  $from_apex      = ycf_extract_apex_domain($from_domain);
  $recipient_apex = ycf_extract_apex_domain($recipient_domain);
  if ($from_apex === '' || $recipient_apex === '' || $from_apex !== $recipient_apex) {
    return ['ok' => true, 'reason' => '', 'mx' => []];
  }

  // 同じ apex を共有：SMTP サーバーがこの apex（あるいはそのサブドメイン）を
  // 「自分が所有」と認識してローカル配送するリスクがある
  $mx_records = @dns_get_record($recipient_domain, DNS_MX);
  if (!is_array($mx_records) || empty($mx_records)) {
    return [
      'ok'     => false,
      'reason' => sprintf('%s の MX レコードを取得できませんでした。同一ドメイン送受で MX 不明の場合は配送先が確定できないため危険です。', $recipient_domain),
      'mx'     => [],
    ];
  }

  $mx_targets = [];
  foreach ($mx_records as $r) {
    if (!empty($r['target'])) {
      $mx_targets[] = rtrim(strtolower($r['target']), '.');
    }
  }

  $smtp_host_lower = strtolower($smtp_host);
  foreach ($mx_targets as $target) {
    if ($target === $smtp_host_lower) {
      return ['ok' => true, 'reason' => '', 'mx' => $mx_targets];
    }
    if (ycf_hosts_are_siblings($smtp_host, $target)) {
      return ['ok' => true, 'reason' => '', 'mx' => $mx_targets];
    }
  }

  return [
    'ok'     => false,
    'reason' => sprintf(
      '送信元 %s と宛先 %s は同じ親ドメイン（%s）を共有しています（サブドメイン違いを含む）。一方その親ドメインの MX [%s] は SMTPホスト %s と無関係なので、SMTPサーバーがこの親ドメインを「自分のもの」と認識し、メールを MX ではなく自分のローカルメールボックスに配送（ハイジャック）して行方不明になる構成です。受信先メールが実際に住んでいるサーバーの SMTP を使う必要があります（例：GWS なら smtp-relay.gmail.com）。',
      $smtp_from_email,
      $recipient_email,
      $from_apex,
      implode(', ', $mx_targets),
      $smtp_host
    ),
    'mx'     => $mx_targets,
  ];
}

/**
 * 設定全体を走査して、すべての宛先メールに対する MX 問題を集める
 *
 * @return array  問題メッセージの配列（空ならOK）
 */
function ycf_collect_mx_issues(array $settings) {
  $issues    = [];
  $smtp_host = $settings['smtp_host'] ?? '';
  $smtp_from = $settings['smtp_from_email'] ?? '';
  if (trim((string) $smtp_host) === '' || trim((string) $smtp_from) === '') {
    return $issues;
  }

  $forms = ycf_get_registered_forms();
  if (empty($forms)) {
    return $issues;
  }

  $field_labels = [
    'admin_to'  => '管理者通知 宛先(To)',
    'admin_cc'  => '管理者通知 CC',
    'admin_bcc' => '管理者通知 BCC',
  ];

  foreach ($forms as $form_key => $config) {
    foreach ($field_labels as $sub => $sub_label) {
      $field_key = $form_key . '_' . $sub;
      $raw       = $settings[$field_key] ?? '';
      $emails    = ycf_parse_email_list($raw);
      foreach ($emails as $email) {
        $result = ycf_check_mx_match($smtp_host, $smtp_from, $email);
        if (!$result['ok']) {
          $issues[] = sprintf(
            '【%s】%s「%s」: %s',
            $config['label'] ?? $form_key,
            $sub_label,
            $email,
            $result['reason']
          );
        }
      }
    }
  }
  return $issues;
}
