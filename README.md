# Yuino Contact Form

確認画面付きのお問い合わせ／資料請求フォームを、コードベースで定義できる軽量プラグイン。

- **Claude Code（CC）ファースト設計**：フォーム定義・カスタム実装は CC に依頼する想定
- **フィールドはコードで定義**：管理画面ビルダーは持たない（CF7／SMF と棲み分け）
- **マークアップは完全カスタム可**：WooCommerce 流のテンプレート上書き対応
- **依存ゼロ**：他プラグイン不要、jQuery 不要
- **送信フロー**：入力 → 確認 → 送信 → サンクスページ
- **セキュリティ**：nonce／Turnstile（任意）／reply-to 自動付与／**MX プリフライト**
- **管理画面**：4 ステップのオンボーディング UI で「次に何をすればいいか」を可視化

---

## クイックスタート（CC を使う場合の標準フロー）

1. このリポジトリを `wp-content/plugins/yuino-contact-form/` に配置
2. WordPress 管理画面 → プラグイン → 「Yuino Contact Form」を有効化
3. 「設定 → お問い合わせ設定」を開く → **4 ステップ オンボーディング UI** が現在地を表示
4. UI に表示されている依頼文を **CC にコピペで投げるだけ** で各 STEP が進む：
   - STEP 1：`「Yuino Contact Form を使って、お問い合わせフォームを作って。fields の構成に合わせて、管理者通知と自動返信のメール件名・本文も option_ycf_settings フィルターで案件側 functions.php に書いて」` ← **fields とメール本文を同時に生成**
   - STEP 2：SMTP 情報を入力（共用サーバー／Workspace 等のプロバイダ別案内が表示される）
   - STEP 3：`wp-config.php` に SMTP パスワードの 1 行を **ユーザーが直接追記**（後述の理由により CC は触らない）
   - STEP 4：通常は STEP 1 で完了済み。必要なら `「フォーム内容とサイト情報に合わせて、管理者通知と自動返信のメール件名・本文を整えて」` で微調整
5. `✅ 運用準備完了` パネルが出れば運用開始

CC を使わない手動セットアップは末尾「10. Claude Code を利用しない場合の手動セットアップ」を参照。

---

## 1. フォーム定義（必須）

テーマの `functions.php` などで `ycf_register_forms` フィルターを使って登録する：

```php
add_filter('ycf_register_forms', function ($forms) {
  $forms['contact'] = [
    'label'       => 'お問い合わせ',
    'page_slug'   => 'contact',          // 入力ページのスラッグ（例：/contact/）
    'thanks_slug' => 'contact-thanks',   // サンクスページのスラッグ
    'privacy_url' => home_url('/privacy/'), // プライバシーポリシーURL（任意）
    'fields'      => [
      'purpose' => [
        'label'       => 'ご相談目的',
        'type'        => 'select',
        'required'    => true,
        'options'     => [
          'purchase' => '購入相談',
          'rent'     => '賃貸相談',
          'other'    => 'その他',
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
        'type'      => 'contact_value', // ← connection_method と連動する特殊型
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
    ],
  ];
  return $forms;
});
```

複数フォームを登録する場合は、`$forms['request'] = [...]` のように追加するだけ。

**CC への依頼例**：
- `「Yuino Contact Form を使って、お問い合わせフォームを作って」` → 上記のような定義を `functions.php` に書く
- `「お問い合わせフォームに『資料請求』も追加して」` → `$forms['request']` を増やす
- `「subject フィールドを複数チェックボックスに変えて」` → YCF 標準外なので独自正規化フィルターを追加

---

## 2. フォームの表示

固定ページのテンプレート（例：`page-contact.php`）から呼び出す：

```php
<?php ycf_render_form('contact'); ?>
```

または、ショートコードでも可：

```
[ycf_form id="contact"]
```

固定ページのスラッグは登録時に指定した `page_slug` と一致させること。

---

## 3. サポートされているフィールド型

| `type` | 用途 |
|---|---|
| `text` | 1行テキスト |
| `email` | メールアドレス（自動でフォーマット検証） |
| `tel` | 電話番号 |
| `textarea` | 複数行テキスト |
| `select` | プルダウン（`options` 必須） |
| `radio` | ラジオボタン（`options` 必須） |
| `checkbox` | チェックボックス（同意系に使う。`required` 必須なら必ずチェック） |
| `contact_value` | **特殊型**：同フォーム内の `connection_method`（radio）の選択値に応じて<br>placeholder／inputmode／pattern が自動切替される連絡先入力欄 |

### フィールド共通オプション

| キー | 意味 |
|---|---|
| `label` | 表示ラベル |
| `required` | 必須かどうか（true/false） |
| `placeholder` | プレースホルダ |
| `maxlength` | 最大文字数（バリデーションにも使用） |
| `autocomplete` | `autocomplete` 属性（`name`／`tel`／`email` 等） |
| `pattern` | HTML5 `pattern` 属性 |
| `default` | radio の初期選択値 |
| `options` | select／radio の選択肢（連想配列：`value => label`） |
| `rows` | textarea の行数（既定6） |

---

## 4. テンプレート上書き（マークアップ完全カスタム）

プラグインのテンプレートをテーマ側で差し替え可能（WooCommerce 流）：

```
wp-content/themes/your-theme/yuino-contact-form/
├── form-section.php   ← 入力or確認の出し分け（基本いじらない）
├── form-input.php     ← 入力画面のHTMLを完全に書き換え可能
└── form-confirm.php   ← 確認画面のHTMLを完全に書き換え可能
```

テーマに同名のファイルを置くと、プラグイン側のテンプレートより優先される。

> **JS 連動を維持したい場合は、以下の `data-*` 属性は維持してください**：
> - `[data-ycf-form][data-ycf-step="confirm"]` … 入力フォーム本体
> - `[data-ycf-method]` … 連絡方法ラジオボタン
> - `[data-ycf-contact-input]` … 連絡先入力欄
> - `[data-ycf-contact-help]` … 連絡先の補助テキスト
> - `[data-ycf-privacy-link]` … プライバシーポリシーへのリンク
> - `[data-ycf-agree="true"]` … 個人情報保護方針の同意チェックボックス
> - `[data-ycf-agree-note]` … 同意チェックの補助テキスト
> - `[data-ycf-submit-form]` … 確認画面の送信フォーム

CSS はプラグインに同梱していない。テーマ側で自由にスタイリングする。

---

## 5. 管理画面で設定する項目

「設定 → お問い合わせ設定」で以下を設定：

- **4 ステップ オンボーディング UI**（ページ上部）：現在地を表示し、次の一手を案内
- **SMTP 設定**：ホスト／ポート／暗号化方式／ユーザー名／From アドレス・名前
  - プロバイダ別の設定値案内テーブルが折りたたみで表示される（Google Workspace / Microsoft 365 / 共用サーバー）
- **高機密情報**（`wp-config.php` に直接記述）：
  ```php
  define('YCF_SMTP_PASSWORD', 'SMTPアカウントのパスワード');
  ```
  ※ **ユーザー自身が直接編集**（CC のチャット履歴に SMTP パスワードを残さないため、本プラグインの推奨フローでは CC に依頼せず手動編集）
- **Cloudflare Turnstile**：サイトキー＋シークレットキー（両方入力で有効化、任意）
- **フォームごとのメール設定**（登録済みフォーム数だけ自動生成）：
  - 管理者通知 宛先（To／CC／BCC）
  - 管理者通知 件名・本文
  - 自動返信 件名・本文
  - **メール雛形プリセット**：プラグイン同梱サンプル fields に整合する件名・本文がデフォルト投入される。fields を独自構成にする場合は `option_ycf_settings` フィルターで上書き（後述「6. メール雛形プリセット」）

### MX プリフライト

設定保存時に、宛先メールアドレスのドメインの MX レコードと SMTP ホストのドメインの整合性を自動検査する。ミスマッチがあれば保存をキャンセルし、警告を表示。

**目的**：「送信完了画面が出たのにメールが届かない」事故（宛先 Gmail × 送信元共用サーバー SMTP 等）を保存前に止める。

**既知の限界**：ローカルドメイン（`*.local`）や独自 MX 転送サービスでは偽陽性が出る場合あり。

### メール本文で使えるプレースホルダ

| 共通 | 説明 |
|---|---|
| `{form_key}` | フォームキー（例：`contact`） |
| `{form_label}` | フォーム表示名（例：お問い合わせ） |
| `{site_name}` | サイト名（`get_bloginfo('name')`） |
| `{sent_at}` | 送信日時 |
| `{remote_ip}` | 送信元 IP |

| フィールド | 説明 |
|---|---|
| `{<field_key>}` | 各フィールドの値（例：`{name}`、`{message}`） |
| `{<field_key>_label}` | radio／select／checkbox の **ラベル文字列** |

---

## 6. メール雛形プリセット（2 層構造）

メール件名・本文の初期値は **「プラグイン同梱雛形」+「案件側上書き」** の 2 層で管理されている。

### 層 1：プラグイン同梱雛形

プラグイン有効化時、同梱サンプル `examples/register-forms.php` の fields（`purpose` / `connection_method` / `contact_value` / `address` 等）に整合する件名・本文が `inc/config.php` の `ycf_get_default_form_mail_settings()` から自動で差し込まれる。

→ 管理画面のメール設定欄が空欄にならず、利用者は固有情報を書き換えるだけで運用に入れる。

### 層 2：案件側上書き

`functions.php` で fields を独自構成にした場合（例：`name` / `kana` / `email` / `tel` / `subject` / `message`）、同梱雛形のプレースホルダ（`{purpose_label}` 等）が噛み合わない。その場合は `option_ycf_settings` フィルターで「DB 空キーのみ案件固有雛形で埋める」方式で対応する：

```php
add_filter('option_ycf_settings', function ($value) {
  if (!is_array($value)) { $value = []; }
  $seeds = [
    'contact_admin_subject'     => '【{site_name}】お問い合わせを受信しました（{name} 様）',
    'contact_admin_body'        => "■ お名前\n{name}\n\n■ ご用件\n{subject}\n…",
    'contact_autoreply_subject' => '【{site_name}】お問い合わせを受け付けました',
    'contact_autoreply_body'    => "…",
  ];
  foreach ($seeds as $k => $v) {
    if (!isset($value[$k]) || $value[$k] === '') {
      $value[$k] = $v;
    }
  }
  return $value;
});
```

**注意**：管理者が管理画面で編集した値は **上書きされない**（DB に値があれば DB 優先）。案件側雛形は「初期投入」の役割のみ。

**CC への依頼例**：
- **同時実行（推奨）**：`「Yuino Contact Form を使って、お問い合わせフォームを作って。fields の構成に合わせて、管理者通知と自動返信のメール件名・本文も option_ycf_settings フィルターで案件側 functions.php に書いて」` — fields とメール雛形を 1 回の依頼で同時に生成
- **メール本文だけ後追いで書く場合**：`「フォーム fields の構成に合わせて、管理者通知と自動返信のメール雛形を option_ycf_settings フィルターで案件側に書いて」`

---

## 7. アクション・フィルター一覧

| 種別 | 名前 | 用途 |
|---|---|---|
| filter | `ycf_register_forms` | フォーム定義の登録 |
| filter | `ycf_validate` | カスタムバリデーション追加 |
| filter | `ycf_template_replacements` | メールテンプレートの置換マップを追加 |
| filter | `ycf_contact_value_meta` | 連絡先入力欄の placeholder 等をカスタマイズ |
| action | `ycf_before_send` | 送信前フック（`$form_key, $data`） |
| action | `ycf_after_send` | 送信後フック（`$form_key, $data`） |
| filter | `option_ycf_settings`（WP 標準） | メール雛形等の DB 値を読む直前に上書き（6 節参照） |

---

## 8. 動作フロー

1. 入力ページ（`page_slug`）にアクセス → `form-input.php` 表示
2. 「確認する」 → `admin-post.php` 経由で `ycf_handle_input` が受け、バリデーション
3. OK なら確認画面へ、エラーなら入力画面に戻る
4. 確認画面で「送信する」 → `ycf_handle_submit` が受ける
5. Turnstile 検証 → 管理者通知 → 自動返信 → サンクスページへリダイレクト

入力値はトランジェントで 5 分間保持され、Cookie のトークンで紐づけられる。

---

## 9. ライセンス

GPL-2.0-or-later

---

## 10. Claude Code を利用しない場合の手動セットアップ

本プラグインは CC ファースト設計だが、手動でも一通りセットアップ可能。

### セットアップ手順

1. プラグインを `wp-content/plugins/yuino-contact-form/` に配置 → 管理画面で有効化
2. テーマの `functions.php` に `ycf_register_forms` フィルターを書く（上記「1. フォーム定義」のコードをコピーして fields を編集）
3. 固定ページを作成（`contact` / `contact-thanks` / `privacy` 等、`page_slug` と一致させる）
4. 固定ページのテンプレートに `<?php ycf_render_form('contact'); ?>` を呼ぶ（または本文にショートコード）
5. 管理画面「設定 → お問い合わせ設定」で SMTP・宛先・Turnstile（任意）を入力
6. `wp-config.php` に SMTP パスワードを追記：
   ```php
   define('YCF_SMTP_PASSWORD', 'パスワード');
   ```
   挿入場所は `/* That's all, stop editing! */` の上。
7. テーマ側で CSS を書く（プラグインに同梱なし）
8. フォーム送信テスト

### 手動セットアップ時の注意

- 管理画面の 4 ステップ UI は「CC への依頼テキスト」を提示するが、これは無視して手動で進めて構わない（UI 側で「ready」と表示されれば運用可能）
- メール雛形プリセットは同梱サンプル fields 前提の文面なので、独自 fields を使う場合は管理画面で書き換えるか、`option_ycf_settings` フィルターで上書き（6 節参照）
- テンプレートのマークアップカスタムは `themes/<theme>/yuino-contact-form/` 配下に同名ファイルを置くだけ（4 節参照）
