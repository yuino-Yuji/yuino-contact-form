# Yuino Contact Form 引き継ぎメモ

> 作成日: 2026-05-10  / 最終更新: 2026-05-30  / バージョン: **0.2.3**
> 作業環境: `plugin-dev` (Local for Flywheel)
> 想定起点ディレクトリ: `/Volumes/BUFFALO HD-PCGU3-A/Local Sites/plugin-dev/app/public/wp-content`

このドキュメントは、別セッションでこのプラグインの開発を再開するための引き継ぎ。
README.md は **利用者向け** ドキュメント、本書は **開発・運用向け** ドキュメント。

---

## 0. 経緯（このプラグインが生まれた背景）

### 課題: 既存プラグインでは噛み合わなかった

- **Contact Form 7**: 長年使ってきた定番だが、**確認画面作成の煩わしさ** が解消されない。
- **Snow Monkey Forms**: 代替候補の筆頭。確認画面を標準装備している点は魅力だが、
  **カンプデザインに合わせ込むのが難しい** ため採用に踏み切れずにいた。

### 引き金: Claude Code とのワークフローとの相性

- 現状、コーディングは **Claude Code（以下 CC）** に任せる比率が高い。
- CF7 は **WP管理画面の中で `[text* your-name]` 等の独自タグを組む** 方式。
  CC は管理画面内の値を読めない／書けないため、構造的に CC ファーストのワークフローと噛み合わない。
- → デザイン自由度があっても、**フォーム定義そのものをコードで書ける** 方式が必要だと判断。

### 起点: everhome 案件でゼロから作ってみた

- everhome 案件で、CC にフォーム本体・確認画面・サンクス画面を
  **テーマ直書きで** ゼロから作ってもらったところ、想定以上に再現性が高かった。
- → 「これは他案件でも使い回せる」と判断し、再利用可能なプラグインとして切り出したのが本プラグイン。

### 設計方針: CC ファースト

このプラグインのあらゆる判断は **「CC が扱いやすいか」** を最優先する：

- フォーム定義は **PHP 配列**（`ycf_register_forms` フィルター）で行う ← CC が読み書きできる形
- マークアップは **テーマ側で完全に上書き可能** にしておく ← カンプ合わせを CC に任せられる
- 管理画面は **運用設定（宛先・本文・SMTP）のみ** に絞り、構造定義は管理画面に持ち込まない
- 機能追加を検討する際も、まず **「CC が扱いやすい形か？」** で判断する
- **管理画面 UI も「CC への依頼テキストをそのまま貼り付けられる形」で設計**（3-8 参照）

---

## 1. 現状サマリ（v0.2.0）

### 完成しているもの

- プラグイン本体 v0.2.0（このディレクトリ全体）
- 確認画面付きフォーム送信フロー一式（入力 → 確認 → 送信 → サンクス）
- 管理画面（設定 → お問い合わせ設定）
  - **4 ステップ オンボーディング UI**（forms_unregistered / smtp_unset / smtp_password_unset / mail_body_default / ready の状態判定。CC への依頼テキストを blockquote で提示。3-8 参照）
  - SMTP設定（ホスト・ポート・暗号化方式・ユーザー名・From）＋プロバイダ別設定値の案内テーブル（Google Workspace / M365 / 共用サーバー）
  - **MX プリフライト**：宛先と SMTP ホストのドメイン整合性を自動検査。ミスマッチ時は設定保存をキャンセル + `admin_notices` で警告（3-10 参照）
  - Cloudflare Turnstile サイトキー＋シークレットキー（v0.2 で **シークレットも DB 保存** に変更、3-5 参照）
  - 登録済みフォームを自動でループしてメール設定セクション生成
  - **メール雛形プリセット（プラグイン同梱版）**：プラグイン同梱サンプル fields（purpose / connection_method / contact_value / address）に整合する件名・本文のデモ初期値を `inc/config.php` の `ycf_get_default_form_mail_settings()` に内蔵。3-9 参照
- WooCommerce流のテンプレート上書き機能
  - `themes/<theme>/yuino-contact-form/form-input.php` 等に置けば差し替え可
- ショートコード `[ycf_form id="..."]` ／ PHPヘルパー `ycf_render_form('...')`
- アクション/フィルター API（README.md 「7. アクション・フィルター一覧」参照）

### ファイル構成

```
yuino-contact-form/
├─ yuino-contact-form.php      プラグインヘッダー＆ローダー
├─ README.md                   利用者向けドキュメント
├─ HANDOFF.md                  本書
├─ .gitignore
├─ inc/
│  ├─ config.php               設定ヘルパー（DB値＋wp-config.php定数）＋メール雛形プリセット
│  ├─ forms.php                フォーム登録API（ycf_register_forms フィルター）
│  ├─ sanitizer.php            入力サニタイズ
│  ├─ validator.php            バリデーション
│  ├─ turnstile.php            Cloudflare Turnstile 検証
│  ├─ mailer.php               PHPMailer設定／メール送信／プレースホルダ置換
│  ├─ handler.php              admin-post.php フック（input/submit）／状態保存
│  ├─ template-loader.php      ycf_locate_template / ycf_render_form / ショートコード
│  ├─ assets.php               フォームページ判定＆JSエンキュー
│  ├─ admin-settings.php       管理画面（4ステップUI＋登録済みフォームをループしてセクション生成）
│  └─ mx-preflight.php         MX プリフライト（宛先↔SMTPホスト整合性検査）
├─ templates/
│  ├─ form-section.php         入力 or 確認の出し分け
│  ├─ form-input.php           入力画面（data-ycf-* 属性で JS 連携）
│  └─ form-confirm.php         確認画面
├─ assets/js/contact-form.js   全 data-* 属性駆動・複数フォーム対応
└─ examples/register-forms.php フォーム登録のサンプル（contact / request 2種）
```

### git 状態（2026-05-28 時点）

- ローカル `main` ブランチで **v0.2.0 として再初期化**
- 初回コミット: 統合スナップショット（v0.1.0 → v0.2.0 の全変更を1コミットに集約）
- タグ: `v0.2.0`
- リモート: **未設定**（GitHub にプッシュするかは検討中）
- `.gitignore` 済み: `.DS_Store`, `node_modules/`, `vendor/`, `.vscode/`, `.idea/`, `*.log`

**経緯**: v0.1.0 時点で `git init` 済みだったが、5/10 → 5/28 のブラッシュアップ過程で plugin-dev 環境と 2504hannan 環境（ドッグフーディング先）の2系統に分岐して並行進化し、v0.1.0 の git リポジトリは喪失（初回コミット `f1a81cc` を持つリポジトリがどちらの環境にも残っていなかった）。v0.2.0 タイミングで再初期化し、両系統の統合スナップショットを正本として確立。詳細は 4-12 / 8 章参照。

### ユーザー（yuji）が plugin-dev 環境で完了している作業

- `examples/register-forms.php` の中身を plugin-dev サイトのテーマ `functions.php` に貼り付け済み

### 2504hannan 環境（ドッグフーディング先）で完了している作業

- プラグイン有効化、固定ページ作成、SMTP 情報入力、`YCF_SMTP_PASSWORD` 定義済み
- 管理画面 4 ステップ UI が `ready` 状態で表示されることを確認済み（2026-05-28）
- ハンナン固有フォーム（`subject` 複数チェック）に合わせて、案件側 `functions.php` で `option_ycf_settings` フィルターによるメール雛形上書きを実装（3-9 の「案件側雛形上書き」パターンの実証例）

### 残タスク（plugin-dev 環境内）

- プラグインの有効化
- 固定ページ作成（contact / contact-thanks / request / request-thanks / privacy）
- SMTP（Mailpit）設定
- 入力 → 確認 → 送信のフロー動作確認
- メール受信確認
- MX プリフライト機能の動作確認（Mailpit + ローカルドメインの組み合わせで MX 検査がどう振る舞うか）

---

## 2. 次の作業手順

### A. プラグイン有効化

1. `http://plugin-dev.local/wp-admin/` → プラグイン
2. 「Yuino Contact Form」を有効化
3. 有効化直後、「設定 → お問い合わせ設定」で **4 ステップ UI** が STEP 1（forms_unregistered）を表示するはず

### B. 固定ページ作成

| タイトル | スラッグ | 用途 |
|---|---|---|
| お問い合わせ | `contact` | 入力ページ。本文は空でも可（テーマ側 `page-contact.php` で `ycf_render_form('contact')` を呼ぶ場合） |
| お問い合わせ完了 | `contact-thanks` | サンクスページ |
| 資料請求 | `request` | 入力ページ |
| 資料請求完了 | `request-thanks` | サンクスページ |
| 個人情報保護方針 | `privacy` | プライバシーポリシー |

ショートコードで表示する場合は本文に `[ycf_form id="contact"]` を貼る。
テンプレートで表示する場合は `page-contact.php` 等を作って `ycf_render_form('contact')` を呼ぶ。

### C. SMTP（Mailpit）設定

Local for Flywheel は Mailpit が同梱されている（サイト管理画面 → Tools → Mailpit）。
ホスト名は `localhost`、ポートは Local の Mailpit パネルで確認できる（Site SMTP host/port）。

1. WP管理画面 → 設定 → お問い合わせ設定
2. SMTP設定：
   - SMTPホスト: `localhost`
   - ポート: Local が表示する SMTP ポート（多くは `1025`）
   - 暗号化方式: `なし`
   - 認証ユーザー名: 任意（Mailpit は認証不要）
   - 送信元メールアドレス: `noreply@plugin-dev.local` 等
   - 送信者名: 任意
3. `wp-config.php` に追記（**v0.2 以降、CC ではなくユーザーが直接編集**）：
   ```php
   define('YCF_SMTP_PASSWORD', ''); // Mailpit は空で可（ただし定数定義は必要）
   ```
   - **注意**：`mailer.php` は SMTP接続情報＋パスワードが揃っている場合のみ SMTP モードに切替える。Mailpit は認証不要なので、SMTP化させたい場合は何らかのダミー値（例：`'dummy'`）を入れる必要があるかも。動作確認結果を見て調整。
   - もしくは Local の Mailpit はデフォルト挙動（PHP の `mail()` ＝ `sendmail` 経由）でも捕まえられるので、SMTP設定を全て空にして試すのもアリ。
4. **MX プリフライトの挙動**：ローカルドメイン（`*.local`）は MX レコードを持たないため、宛先に `*.local` を指定すると検査が偽陽性で警告を出す可能性あり（4-13 参照）。

### D. フォーム別メール設定

「設定 → お問い合わせ設定」内で、登録済みフォームごとに以下を設定：

- 管理者通知 宛先（To）：`info@plugin-dev.local` 等
- 管理者通知 件名：`【{form_label}】{name} 様より` 等
- 管理者通知 本文：プレースホルダで組み立て
- 自動返信 件名・本文：必要なら設定

**メール雛形プリセットの仕様**（3-9 参照）：
- プラグイン同梱サンプル（`examples/register-forms.php` の `purpose` / `connection_method` / `contact_value` / `address` fields）に整合する件名・本文が `inc/config.php` の `ycf_get_default_form_mail_settings()` から自動で差し込まれる
- **fields 構成を独自に変えた案件**（例：2504hannan の `subject` 複数チェック構成）では、同梱雛形のプレースホルダが噛み合わないため、案件側 `functions.php` で `option_ycf_settings` フィルターを使って案件固有の雛形を上書きする運用（3-9「2層構造」参照）

利用可能なプレースホルダはセクション下部にコード表示される。

### E. 動作確認チェックリスト

- [ ] `/contact/` にアクセス、入力画面が表示される
- [ ] 必須項目を空のまま「確認する」 → 入力画面に戻り、エラーメッセージが表示される
- [ ] `connection_method` で「電話」を選択 → `contact_value` の placeholder/inputmode/pattern が切り替わる
- [ ] 「個人情報保護方針」リンクをクリックする前は同意チェックが disabled
- [ ] リンククリック後にチェック可能になる
- [ ] 全項目を入力して「確認する」 → 確認画面が表示され、入力内容が反映される
- [ ] 「修正する」 → 入力画面に戻り、入力値が保持されている
- [ ] 「送信する」 → サンクスページにリダイレクト
- [ ] Mailpit（または受信ボックス）で管理者通知メール受信
- [ ] 自動返信メール（連絡方法＝メール時）受信
- [ ] メール本文のプレースホルダが正しく置換されている
- [ ] `/request/` でも同様に確認
- [ ] ボタン二重押下防止が効く（送信ボタンクリック後、即座に disabled になる）
- [ ] 4 ステップ UI が「フォーム未登録 → SMTPホスト未入力 → SMTPパスワード未定義 → メール本文がデモのまま → ready」と段階的に進む
- [ ] 宛先と SMTP ホストのドメインがミスマッチする組み合わせを入力 → 保存がキャンセルされ、`admin_notices` で警告が表示される（MX プリフライト）

### F. CSS 未同梱の補完

プラグインに CSS は同梱していない。plugin-dev のテーマでスタイリングが必要：
- 最低限の整形は `.ContactForm__*` 系クラス向けに書く（everhome テーマの SCSS が参考になる）
- もし新サイトでクラス名を変えたい場合は `themes/<theme>/yuino-contact-form/form-input.php` を上書きしてクラス名を変更（`data-ycf-*` 属性は維持必須）

---

## 3. 設計意図（なぜそう作ったか）

### 3-1. フィルター駆動でフォーム登録

`ycf_register_forms` フィルターでテーマ側からコード登録する方式を採用。

**なぜ管理画面ビルダーにしないか**：
- CF7／SMF と同じ土俵に立つと差別化できない
- ビルダーを持つと「マークアップの自由度」というプラグインの最大の利点が失われる
- yuji さんの運用は **コードでフィールド定義 → 管理画面で運用設定（宛先・本文）** という割り切り

### 3-2. WooCommerce流のテンプレート上書き

`ycf_locate_template()` がテーマ → プラグインの順で探索する。

**メリット**：
- プラグイン側はマークアップを変えずに更新できる
- 各サイトで自由に HTML を書き直せる（CSS設計に合わせて）
- BEM クラス名すら変更可能（ただし `data-ycf-*` 属性は維持）

### 3-3. JS は完全に data-* 属性駆動

クラス名（`.ContactForm` 等）に依存せず、`data-ycf-form` `data-ycf-method` 等で動作。

**なぜ**：
- テンプレート上書きでクラス名を変えられても JS が壊れない
- 複数フォームが同一ページに存在しても動く（`form.querySelector` でスコープ限定）
- everhome 版は `.ContactForm` セレクタ依存だったので脱却

### 3-4. CSS は同梱しない

**なぜ**：
- 同梱するとサイトごとの装飾を上書きする手間が発生
- WCAG等のサイト全体ルール（フォントサイズ rem 統一など）と整合しないリスク
- マークアップ自由度を活かす設計と矛盾

→ 各サイトのテーマで `ContactForm__*` クラス向け SCSS を書く（everhome のものをコピーが手早い）

### 3-5. SMTPパスワードは wp-config.php

`YCF_SMTP_PASSWORD` は DB 保存せず、`wp-config.php` の定数で管理。

**なぜ**：
- WP のオプションテーブルが流出した場合のリスク低減
- 環境（本番／ステージング／ローカル）ごとに切替えやすい
- 流出時の被害が「外部メールサーバーへの永続認証奪取＝なりすまし送信／レピュテーション破壊」と桁違いに大きい

**書き込みフロー**: v0.2 以降、**CC は wp-config.php を編集しない**。理由は CC のチャット履歴に実 SMTP パスワードを残さないため。STEP 3 の管理画面 UI も「ユーザー自身が直接編集する」案内に統一済み（`admin-settings.php` `ycf_render_onboarding_panel()` の `'smtp_password_unset'` パネル参照）。

**Turnstile シークレットは v0.2 以降 DB 保存（管理画面入力）に変更**。理由：
- 流出時の被害が「当該サイト1つの captcha 検証無効化」に限定され、他システムへ波及しない
- 業界標準（CF7 / Forminator / Cloudflare 公式 WP プラグイン）も DB 保存方式
- セットアップ手順がシンプルになる（管理画面で完結）

ただし既存案件で `YCF_TURNSTILE_SECRET` 定数定義が残っているケース向けに互換実装あり（`ycf_get_turnstile_secret()` は「定数があれば優先、無ければ DB」のフォールバック）。

### 3-6. プレフィックス `ycf_`

everhome 版の `cf_*` は汎用すぎるため、プラグイン化に伴い `ycf_*`（Yuino Contact Form）に変更。

**注意**：定数も `YCF_*`、オプションキーも `ycf_settings`。grep する際はこの prefix で。

### 3-7. フィルター API（拡張ポイント）

| 名前 | 用途 |
|---|---|
| `ycf_register_forms` | フォーム登録 |
| `ycf_validate` | カスタムバリデーション追加 |
| `ycf_template_replacements` | メールテンプレートの置換マップを追加 |
| `ycf_contact_value_meta` | 連絡先入力欄の placeholder 等をカスタマイズ |
| `ycf_before_send` / `ycf_after_send` | 送信前後フック |
| `option_ycf_settings` （WP標準フィルター） | **案件側のメール雛形上書きに使用**（3-9 参照） |

### 3-8. CC ファースト UI：4 ステップ オンボーディング

プラグイン有効化直後の利用者が「次に何をすればいいか分からない」状態に陥らないよう、管理画面トップに 4 ステップの状態判定 UI を実装（`admin-settings.php` の `ycf_get_onboarding_state()` ／ `ycf_render_onboarding_panel()`）。

| 状態キー | 表示タイトル | 判定条件 | 案内内容 |
|---|---|---|---|
| `forms_unregistered` | STEP 1 / 4：お問い合わせフォームを構築する | `ycf_get_registered_forms()` が空 | CC への依頼テキスト `「Yuino Contact Form を使って、お問い合わせフォームを作って。fields の構成に合わせて、管理者通知と自動返信のメール件名・本文も option_ycf_settings フィルターで案件側 functions.php に書いて」` を blockquote で提示。**fields とメール雛形を 1 回の依頼で同時に生成させる**（v0.2.1 で同時実行化） |
| `smtp_unset` | STEP 2 / 4：SMTP 情報を設定する | `smtp_host` が空 | 下の SMTP セクションへ誘導。プロバイダ案内テーブルも参照案内 |
| `smtp_password_unset` | STEP 3 / 4：SMTP パスワードを wp-config.php に定義する | `ycf_get_smtp_password()` が空 | **ユーザーが直接編集**する手順（追記する 1 行と挿入位置）を提示。CC は wp-config.php に介入しない |
| `mail_body_default` | STEP 4 / 4：メール件名・本文を確認・微調整する | メール本文が同梱デモ初期値と完全一致 | **通常は STEP 1 で CC が案件版を書いているのでスキップされる**。書き忘れ／後追い対応のフォールバック。CC への依頼テキスト `「フォーム内容とサイト情報に合わせて、管理者通知と自動返信のメール件名・本文を整えて」` を提示 |
| `ready` | ✅ 運用準備完了 | 全条件クリア | 登録済みフォーム一覧 + URL を表示 |

**設計上の特徴**：
- CC に投げる依頼テキストを blockquote で**そのままコピペできる形**で提示する。これは「マニュアルに書いてあることを CC に翻訳して伝える」手間を削り、利用者の認知負荷を最小化するため。
- **STEP 1 と STEP 4 を同一指示に統合**（v0.2.1）：fields 実装と同時にメール本文も案件版で生成させ、利用者が CC に依頼する回数を最小化。STEP 4 は「念のための保険」として残し、CC が書き忘れたケース／後追いで微調整したいケース向けのフォールバックとして機能する。

### 3-9. メール雛形プリセット（2 層構造）

メール件名・本文の初期値を「**プラグイン同梱雛形** + **案件側上書き**」の 2 層構造で管理する。

**層1: プラグイン同梱雛形**（`inc/config.php` の `ycf_get_default_form_mail_settings()`）
- 同梱サンプル `examples/register-forms.php` の fields（`purpose` / `connection_method` / `contact_value` / `address` 等）に整合する件名・本文をハードコード
- プラグイン有効化直後でも、管理画面のメール設定欄が空欄にならず、利用者は「あとは固有情報を書き換えるだけ」で運用に入れる
- 4 ステップ UI の `mail_body_default` 状態は「この同梱雛形と完全一致しているか」で判定

**層2: 案件側上書き**（案件 `functions.php` での `option_ycf_settings` フィルター）
- fields 構成を独自に変えた案件（例：2504hannan の `name` / `kana` / `email` / `tel` / `subject`複数チェック / `message`）では、同梱雛形のプレースホルダ（`{purpose_label}` `{contact_value}` 等）が噛み合わない
- 案件側で `option_ycf_settings` フィルターを使い、「DB 空キーのみ案件固有雛形で埋める」方式で対応
- 2504hannan で実証済み。詳細実装は同案件の `functions.php` 参照

**標準パターン**（v0.2 で確立 / v0.2.1 で STEP 1 依頼文と統合）：

CC への依頼は **STEP 1 の依頼文に統合**されている。fields を作る指示と一緒に「fields の構成に合わせてメール雛形も `option_ycf_settings` フィルターで案件側に書いて」が同じ blockquote に含まれており、CC は 1 回の応答で `ycf_register_forms` フィルターと `option_ycf_settings` フィルターの両方をテーマの `functions.php` に書く。コード例：
```php
add_filter('option_ycf_settings', function ($value) {
  if (!is_array($value)) { $value = []; }
  $seeds = [
    'contact_admin_subject'     => '...',
    'contact_admin_body'        => '...',
    'contact_autoreply_subject' => '...',
    'contact_autoreply_body'    => '...',
  ];
  foreach ($seeds as $k => $v) {
    if (!isset($value[$k]) || $value[$k] === '') {
      $value[$k] = $v;
    }
  }
  return $value;
});
```

**注意**: 管理者が管理画面で編集した値は **上書きされない**（DB に値があれば DB 優先）。案件側雛形は「初期投入」の役割のみ。

### 3-10. MX プリフライト

宛先メールアドレスのドメインの MX レコードと、SMTP ホストのドメインの整合性を検査する機能（`inc/mx-preflight.php`）。

**なぜ**:
- フォームが正常に「送信完了」を返しても、メールが届かない事故が多発するパターンがある：宛先メールが Gmail で SMTP ホストが共用サーバーの SMTP、等
- ユーザーが管理画面で設定を保存する **その瞬間** に整合性を検査し、ミスマッチを保存前に止める
- 「保存後に届かないと気づく」→「原因を切り分けるためにログを掘る」というデバッグ地獄を予防

**動作**:
- 設定保存時（`ycf_sanitize_settings()`）：宛先と SMTP ホストの組み合わせを `ycf_collect_mx_issues()` で検査。問題があれば `add_settings_error()` で全件報告 + 保存キャンセル
- 設定ページ表示時（`ycf_settings_page_preflight_notice()`）：保存済み値に対しても再検査。`admin_notices` で警告（管理画面外で DB を直接書き換えた等の経路に対応）

**既知の限界**（4-13 参照）:
- ローカルドメイン（`*.local`）は MX を持たないため検査が機能しない
- 一部の正規プロバイダ（独自 MX のメール転送サービス等）で偽陽性が出る可能性あり

---

## 4. 既知の懸念点（実装中に気付いたが踏み込まなかった点）

### 4-1. `connection_method` のキーをカスタムした際の挙動

`validator.php` の `contact_value` 検証は `mail` / `tel` / `line` という **キー名** に依存している。

```php
// validator.php
switch ($method) {
  case 'mail':
    if (!is_email($value)) { ... }
    break;
  case 'tel':
    if (!preg_match('/\A[0-9]{10,13}\z/', $value)) { ... }
    break;
}
```

→ ユーザーが `options` で別キー（例：`'メール' => '...'`）を指定すると、フォーマットチェックが走らない。

**回避策**：`options` のキーは `mail` / `tel` / `line` を使う運用にする。
**改善案**（将来）：フィールド定義に `validate_as` のようなメタを持たせて任意のキー名でも対応できるように。

### 4-2. `agree_privacy` チェックの解除タイミング

`privacy_url` が空のフォームでは、チェックボックスが最初から `disabled` にならない（リンクが描画されないため）。

これは **意図した仕様**：
- `privacy_url` がないなら「リンクをクリックしてから同意可能」というUXは成立しない
- 単純に通常のチェックボックスとして機能する

**注意**：`agree_privacy` キーで `privacy_url` 未指定でも、checkbox自体は普通に動く。

### 4-3. CSS 未同梱

新規サイトでは見た目が崩れる（ブラウザデフォルトのフォーム要素が並ぶ）。
plugin-dev でも初回はスタイル無しの状態で動作確認することになる。

→ everhome の SCSS（`assets_yuino/scss/object/contact-form/` あたりに該当する記述があるはず）をコピーしてサイト個別に調整する運用。

### 4-4. プレースホルダ衝突

`mailer.php` の `ycf_render_template()` は **フィールドキー** をそのままトークンに使う：

```
{name} → name フィールドの値
{name_label} → name フィールドのラベル文字列（radio/select/checkbox のみ）
```

ユーザーが `name` というキーで「{form_label}」のような既存トークンと衝突する命名をしない限り問題ない。
**注意**：`form_key` `form_label` `site_name` `sent_at` `remote_ip` を **フィールドキー** として使うのは禁止（システムトークンと衝突する）。

### 4-5. Mailpit と SMTP モードの相性（要検証）

`mailer.php:32-40` で、SMTP接続必須項目＋パスワードが揃った場合のみ SMTP モードに切り替える：

```php
if (empty($password)) {
  return; // SMTP使わず通常の wp_mail() 経由
}
```

→ Mailpit は認証不要だが、`YCF_SMTP_PASSWORD` を空にしたままだと SMTP モードに入らない。
→ Local の Mailpit が `wp_mail()` 経由で受信できるなら問題なし。SMTPで明示的に Mailpit に送りたい場合は `YCF_SMTP_PASSWORD` にダミー値を入れる必要がある。

**動作確認時の挙動を見てから決める**項目。

### 4-6. examples/register-forms.php の運用

サンプルそのままだと、テーマの `functions.php` が肥大化する。
推奨：テーマ側で `inc/contact-form-fields.php` のようにパーシャル化して `require_once` する。

### 4-7. Cookie の SameSite

`handler.php` の Cookie は `samesite=Lax`。クロスオリジンで POST が必要な場面はないので問題ないが、将来 SPA 化等で iframe 埋め込みする場合は `None` に切替が必要。

### 4-8. 多言語対応（i18n）

`Text Domain: yuino-contact-form` をプラグインヘッダーに宣言済みだが、文字列はハードコードのまま（`__()` でラップしていない）。
日本語専用前提なので現状で問題ないが、将来的に多言語化する場合は文字列を `__()` で包む必要がある。

### 4-9. 添付ファイル非対応

ファイルアップロードフィールドは未実装。必要になったら `type: 'file'` を追加する（PHPMailer の addAttachment と組み合わせる）。

### 4-10. `is_page()` 判定タイミング

`assets.php` の `is_page()` は `wp_enqueue_scripts` フック時点では使えるが、固定ページのスラッグが登録時の `page_slug` と一致している前提。
**スラッグを変えたら `ycf_register_forms` フィルター側も書き換えること**。

### 4-11. メール雛形プリセット（同梱版）と独自 fields の不整合

3-9 で説明した「層1: プラグイン同梱雛形」は、同梱サンプル fields の構成にハードコード（`{purpose_label}` `{connection_method_label}` `{contact_value}` `{address}` 等）。

→ fields を完全に独自構成（例：2504hannan）にする案件では、同梱雛形のプレースホルダが空白で展開される事故が起きる。

**対策**：案件側で `option_ycf_settings` フィルター（3-9 層2）で必ず上書きする運用を徹底。4 ステップ UI の `mail_body_default` 判定で「同梱雛形と完全一致」を検出するため、上書きを忘れると STEP 4 が消えない仕掛けで自然に気付ける。

**改善案**（将来 v0.3 候補）：`ycf_register_forms` の配列に `mail_template` キーを追加し、fields とメール雛形を **同じ配列内**でコロケーションできるようにする。プラグイン本体側で「DB に値があれば DB、無ければ `mail_template` を初期値として表示」を処理。CC にとってさらに扱いやすくなる。実装中量、配布後に複数案件で実証してから API 設計を確定したい。

### 4-12. dev / hannan 系統分岐の再発防止

**過去事例**（v0.1.0 → v0.2.0 ブラッシュアップ期）:
- plugin-dev 環境と 2504hannan 環境の 2 系統で並行進化が起き、ファイル差分が累積（admin-settings.php / config.php / yuino-contact-form.php / mx-preflight.php / HANDOFF.md の 5 ファイル）
- 当初 git 管理されていたが、運用中のいずれかのタイミングで `.git` が消失（原因不明）
- 統合作業（2026-05-28）で plugin-dev を正本に再同期し、v0.2.0 として再初期化

**再発防止**:
- v0.2.0 以降は **plugin-dev の git リポジトリのみ** を正本とする
- 案件側コピー（2504hannan 等）に直接編集するのは原則禁止。やむを得ず編集した場合は **即座に plugin-dev に反映** して整合を保つ
- 案件固有の調整は **テーマの `functions.php`** で `option_ycf_settings` 等のフィルター経由で行い、プラグイン本体には触れない

### 4-13. MX プリフライトの偽陽性／偽陰性

`mx-preflight.php` は宛先メールドメインの MX レコードと SMTP ホストのドメインの一致を検査する。以下のケースで誤判定する可能性あり：

- **ローカルドメイン（`*.local`）**: MX レコードを持たないため、検査が成立しない（偽陽性）
- **メール転送サービス**: 宛先ドメインの MX が独自のメール転送サービスを指している場合、SMTP ホスト（実際の送信元）とは一致しないが運用上は問題ない（偽陽性）
- **CNAME 経由の MX 委譲**: 一部の DNS 構成で MX が CNAME 経由になっている場合、解決方法によっては取りこぼし（偽陰性）

**対策**:
- 4 ステップ UI を通過しても MX 警告が出るケースは、ユーザーが個別判断で「保存をキャンセル」せず強制保存する手段が必要かもしれない（将来検討）
- 現状は「警告を出すだけ、強制保存はできない」設計。明確な事故予防になっているが、偽陽性で詰まる可能性も認識しておく

---

## 5. everhome の扱い

### 絶対ルール

**`/Volumes/BUFFALO HD-PCGU3-A/Local Sites/2605everhome/` は Fix扱い。一切変更しない。**

### 経緯

- everhome で動いていた自前お問い合わせフォーム機能（`inc/contact-form/`、`template-parts/contact-form/`、`assets_yuino/js/contact-form.js`）をベースに、再利用可能なプラグインとして切り出したのが本プラグイン
- everhome 側のコードはそのまま残置（旧 `cf_*` プレフィックス）
- everhome の動作は、本プラグイン化作業によって一切影響を受けていない（最終コミット: `a6f8877`）

### everhome を将来プラグイン版に切り替える場合の手順（参考）

1. everhome の `functions.php` から `require_once get_template_directory() . '/inc/contact-form/init.php';` を削除
2. テーマ内の `inc/contact-form/` ディレクトリを削除
3. テーマ内の `template-parts/contact-form/` ディレクトリを削除（または `yuino-contact-form/` にリネームしてプラグインのテンプレート上書きとして利用）
4. `assets_yuino/js/contact-form.js` を削除（プラグインが自動エンキューする）
5. `page-contact.php` / `page-request.php` の `get_template_part('template-parts/contact-form/form-section', ...)` を `ycf_render_form('contact')` / `ycf_render_form('request')` に置換
6. テーマ `functions.php` で `ycf_register_forms` フィルターを使ってフィールド定義を登録（`examples/register-forms.php` を参考に）
7. WP管理画面で本プラグインを有効化、設定値を移行（旧オプションキー `my_contact_form_settings` から新 `ycf_settings` への変換が必要）

→ ただし **これは別案件として明示的に依頼があった場合に限る**。yuji さんから指示がない限り everhome は触らない。

### everhome 側の旧オプションキーと新オプションキーの対応

| 旧（`my_contact_form_settings`） | 新（`ycf_settings`） |
|---|---|
| `smtp_*` | `smtp_*`（同名） |
| `turnstile_site_key` | 同名 |
| `contact_admin_to` 等 | 同名（プレフィックスは form_key） |
| `request_admin_to` 等 | 同名 |
| 定数 `CF_SMTP_PASSWORD` | `YCF_SMTP_PASSWORD` |
| 定数 `CF_TURNSTILE_SECRET` | `turnstile_secret_key`（DB 保存・管理画面入力に変更）／互換のため `YCF_TURNSTILE_SECRET` 定数定義も継続サポート |

---

## 6. 開発再開時の典型コマンド

```bash
# 作業ディレクトリ
cd "/Volumes/BUFFALO HD-PCGU3-A/Local Sites/plugin-dev/app/public/wp-content/plugins/yuino-contact-form"

# 状態確認
git status
git log --oneline -5
git tag

# PHP構文チェック（全ファイル）
find . -type f -name "*.php" -exec php -l {} \;

# JS構文チェック
node --check assets/js/contact-form.js

# 案件側コピーとの差分検査（ドッグフーディング先と乖離していないか）
diff -rq . "/Volumes/BUFFALO HD-PCGU3-A/Local Sites/2504hannan/app/public/wp-content/plugins/yuino-contact-form" | grep -v ".DS_Store"

# Local for Flywheel の WP-CLI を使う場合
# ※ Local の Site Shell から実行
wp plugin list
wp plugin activate yuino-contact-form
```

---

## 7. 連絡

質問・要望があれば yuji さんに確認。
特に「フィールドの新しい型を増やす」「管理画面ビルダーを作る」など方針に関わる変更は **必ず事前確認**。

---

## 8. ブラッシュアップ履歴

git 履歴が v0.1.0 → v0.2.0 移行期に喪失したため、その間の主要変更をここに記録する。v0.2.0 以降は git ログを正本とする。

### v0.1.0 → v0.2.0（2026-05-10 → 2026-05-28）

#### plugin-dev 側で進めた変更（2026-05-14 前後）

- **MX プリフライト機能新規追加** — `inc/mx-preflight.php` (223行)、`ycf_sanitize_settings()` に組み込み + `admin_notices` フックで警告
- **SMTP プロバイダ案内テーブル** — Google Workspace（リレー / アプリパスワード）/ Microsoft 365 / 共用サーバーの設定値を `<details>` で表示
- **HANDOFF.md に「0. 経緯」セクション追加** — CF7/SMF を採用しなかった理由、CCファースト方針、everhome 起点

#### 2504hannan 側で進めた変更（2026-05-28、ドッグフーディング由来）

- **4 ステップ オンボーディング UI** — `ycf_get_onboarding_state()` で `forms_unregistered` / `smtp_unset` / `smtp_password_unset` / `mail_body_default` / `ready` を判定し、CC への依頼文を blockquote で提示
- **「準備完了」パネル** — `ycf_render_ready_form_list()` で登録済みフォーム一覧 + URL 表示
- **メール雛形プリセット（プラグイン同梱版）** — `inc/config.php` の `ycf_get_default_form_mail_settings()` に同梱サンプル fields 整合の雛形をハードコード
- **メール雛形プリセット（案件側上書きパターン）の実証** — 案件 `functions.php` で `option_ycf_settings` フィルター経由の上書き実装（3-9 標準パターン）
- **管理画面の冗長文言整理** — 「SMTPパスワードと Turnstile シークレットキーは wp-config.php 内の定数として管理しています」の説明文削除（次の一手の誘導を主役に）

#### 統合作業（2026-05-28 同日）

- plugin-dev / 2504hannan 両系統のファイル差分を集約し、両環境を完全同期（`diff -rq` 差分ゼロ）
- **STEP 3 UI 文言の修正** — 「CC が自動で wp-config.php を編集します」→「ユーザーが直接編集してください」（CC のチャット履歴に SMTP パスワードを残さない方針）
- **Turnstile シークレットの DB 化** — 機密度が低い + 業界標準と整合のため、`YCF_TURNSTILE_SECRET` 定数 → `turnstile_secret_key` DB 保存（管理画面入力）に変更。既存案件互換のためフォールバック実装（定数があれば優先）
- **管理画面の件名欄を倍幅化** — `*_admin_subject` / `*_autoreply_subject` フィールドのみ `large-text` クラス適用（プレースホルダ込みの長文が見切れないように）

#### v0.2.0 リリース時の正本確立

- plugin-dev で `git init` 再実行
- 統合スナップショットを初回コミットとして登録
- タグ `v0.2.0` 付与
- README.md / HANDOFF.md を v0.2.0 仕様に全面改訂

### v0.2.1（2026-05-28）

- **STEP 1 依頼文の統合** — fields 登録とメール雛形オーバーライドの依頼文を「同時実行版」に統合（管理画面オンボーディング UI、`ycf_get_onboarding_state()` 関連）

### v0.2.2（2026-05-30）

- **入力画面の Turnstile 重複表示を削除** — `templates/form-input.php` から Turnstile ウィジェットブロック削除。サーバ側で検証していたのは確認画面（`ycf_handle_submit`）のみで、入力画面の Turnstile は装飾化していた。Turnstile トークンは使い捨てのため二重表示は無意味＋ UX 悪化
- **バージョン文字列を同期** — Plugin Header と `YCF_VERSION` 定数を `0.1.0` → `0.2.2`（v0.2.0 / v0.2.1 タグ時のバンプ漏れを解消）

### v0.2.3（2026-05-30）

- **自動返信メールの到達性向上ヘッダ追加** — `inc/mailer.php` の `ycf_send_autoreply()` に以下 4 ヘッダを付与：
  - `Auto-Submitted: auto-replied`（RFC 3834：自動応答であることを宣言）
  - `Precedence: auto_reply`（慣習：応答ループ抑止）
  - `X-Auto-Response-Suppress: All`（Outlook/Exchange：応答抑制）
  - `Reply-To: <管理者通知 To の先頭アドレス>`（noreply で行き止まりにせず、返信を業務窓口に集約）
  - **背景**：2504hannan ドッグフーディングで `bause@mac.com`（iCloud）宛の自動返信が silent reject される事象を観測。SMTP リレー側ログでは送信成功扱いだが受信箱に届かない。iCloud 等の厳格なスパムフィルタが「返信不能な one-way 自動メール」をスコア減点する典型挙動だったため、業界標準の自動応答識別ヘッダ群と Reply-To を追加して deliverability 上の signal を改善
- **変数名 cleanup** — `ycf_send_autoreply` 内で受信者アドレスを保持する変数を `$reply_to` → `$recipient` に改名（Reply-To ヘッダ値と紛らわしいため）
- **README.md に「メール到達性のための DNS 整備チェックリスト」追加** — SPF/DKIM/DMARC は各サイトの送信元ドメインで運用者が整備する責任範囲であることを明示。プラグイン同梱ヘッダだけでは認証 DNS の代替にはならない

### v0.2.4（2026-05-30）

- **自動更新機構の組み込み** — [Plugin Update Checker（PUC）v5.7](https://github.com/YahnisElsts/plugin-update-checker)（MIT）を `vendor/plugin-update-checker/` に同梱。`yuino-contact-form.php` 末尾で初期化し、`https://github.com/yuino-Yuji/yuino-contact-form/`（main ブランチ）の Release を 12 時間ごとに監視する。配布先（実案件）の WP 管理画面 → プラグイン一覧に通常の「更新あり」通知が出るようになり、**ワンクリックで最新版に更新可能**（rsync→FTP 手動転送が不要になる）
- **方針：GitHub リポジトリは public 運用**で開始 — コード自体に機密はなく、private 運用にすると配布先で GitHub Personal Access Token の配布管理が必要になるため。private 化が必要になった時点で `wp-config.php` に `YCF_GITHUB_TOKEN` を定義してもらう設計（README 11 章に詳細）
- **`.gitignore` から `vendor/` を除外** — PUC ライブラリは配布物に同梱する必要があるため git 管理対象に変更。コメントで「Composer 由来の依存物は使用しない」旨を明記
- **README.md に「11. 自動更新（Plugin Update Checker）」セクション追加** — リリース手順（タグ push → GitHub Release 作成 → 自動配信）、バージョン番号 3 箇所同期ルール（Plugin Header / `YCF_VERSION` 定数 / git タグ）、動作確認方法（`?puc_check_for_updates=1` パラメータ）、private リポ運用時の Token 設定例
- **更新ペイロードは GitHub 自動生成 source archive（zipball）** を採用 — `enableReleaseAssets()` は呼ばない方針。YCF はソースリポジトリ自体が配布物なので、Release に ZIP を別途添付する手間が不要

#### 背景

2504hannan ドッグフーディングを起点として、v0.2.1 → v0.2.2 → v0.2.3 と連続でリリースする中で、配布先（works.yuino-design.com/066hannan）に対する rsync→FTP の手動転送が毎回発生していた。本体側のバージョンアップを必須とする理由（セキュリティパッチ・WP/PHP 進化追従・新フィルター API・案件間バグ修正横展開）は明確で、今後 YCF 利用案件が増えれば手動転送負担は指数関数的に膨らむ。PUC + GitHub Release 方式はこの構造的問題を一気に解消する。

#### 設計判断ポイント

- **PUC ライブラリ同梱範囲**：`Puc/`（コア）/ `vendor/`（Parsedown：changelog 表示用）/ `css/` `js/`（debug bar、4KB ずつ）/ `languages/`（284KB の翻訳）/ `load-v5p7.php` / `plugin-update-checker.php` / `license.txt` を含めた。除外：`.git/` / `build/` / `examples/` / `composer.json` / `phpcs.xml` / `README.md`。合計 ~664KB
- **PUC 初期化位置**：`yuino-contact-form.php` 末尾（`is_admin()` 分岐より後）。管理画面 / フロント両方で更新チェックが必要なため（WP の `update_plugins` トランジェントはフロント側でも参照される）
- **`file_exists()` ガード**：開発中に `vendor/plugin-update-checker/` を一時退避するケースを想定して防御的に組んだ。本番ではガード句を通る経路は発生しない

#### 残作業（v0.2.4 リリース完了に必要）

このプラグイン本体側のコード変更は完了している。残りは **メンテナ側（yuji）の GitHub 操作**：

1. GitHub に `yuino-Yuji/yuino-contact-form` リポジトリを作成（public）
2. ローカルリポにリモート追加 → `git push origin main --tags`
3. `gh release create v0.2.4` で初回 Release 作成
4. 配布先（2504hannan）で「更新あり（0.2.4）」通知が出ることを確認

### v0.2.5（2026-05-30）

- **Plugin Header に `Update URI` を追加** — `Update URI: https://github.com/yuino-Yuji/yuino-contact-form` を `yuino-contact-form.php` のメタブロックに追加。WP 5.8+ の仕様で、これがあると WordPress は **wordpress.org にある同名 slug プラグインによる誤上書きをスキップ**する。PUC 公式 README で「外部リポジトリ運用なら必須」とされている安全装置
- **`readme.txt` を新規同梱** — WP プラグインリポジトリの慣習形式（`=== Plugin Name ===` で始まる構造化テキスト）。WP プラグイン一覧の「**詳細を表示**」モーダルに、Description / Installation / FAQ / Changelog の各タブで内容が自動表示されるようになる。PUC が GitHub から取得して `plugins_api` フィルター経由で WP に流し込む仕組み
- **配布先案件担当者向けの導線が整備された** — これまで「使い方は GitHub の README.md を見て」と口頭・別チャネル誘導していたものを、WP 管理画面の「詳細を表示」モーダルで完結させられるようになった。FAQ には CC を使わない手動セットアップ・SMTP 設定・Turnstile・テンプレート上書き・MX プリフライト・メール到達性（iCloud 問題）等、運用で訊かれそうな項目を網羅

#### 背景

v0.2.4 を 2504hannan 本番（works.yuino-design.com/066hannan）に手動投入後、ユーザーから 2 件の質問が連続して上がった：

1. 「自動更新を有効化」リンクが出ていない → WP 5.5+ の「裏で勝手に自動更新」機能の UI で、PUC の手動ワンクリック更新とは別系統。出ていなくて正常（私の前回説明が誤誘導だった）
2. 「詳細を表示」リンクで使い方が見られないか → これは WP プラグイン標準の `readme.txt` 同梱で実現可能 → v0.2.5 で対応

`Update URI` ヘッダ追加は PUC ドキュメントで以前から推奨されていたが、v0.2.4 の段階では入れ忘れていた。同名 `yuino-contact-form` が wordpress.org に登録されるリスクは現状ほぼゼロだが、念のため安全装置として追加。

#### 設計判断ポイント

- **`readme.txt` を選んだ理由（vs Markdown）** — PUC は両形式対応（Parsedown 同梱の理由）だが、`readme.txt` は WP プラグインリポジトリの伝統的フォーマットで、modal 表示時に **Description / Installation / FAQ / Changelog のタブ構造**が自動で組まれる。Markdown だと 1 ペイン表示になる。配布先での閲覧 UX としては readme.txt 方式が明らかに優れる
- **README.md / HANDOFF.md と `readme.txt` の役割分担** — README.md は GitHub 上で開発者・案件担当 CC 向けの詳細ドキュメント、HANDOFF.md は引き継ぎ・経緯の集約。`readme.txt` は **WP 管理画面ユーザー向けの簡潔な使い方ガイド** — 重複は気にせず、配布先の閲覧導線最適化を優先する
- **`Update URI` の URL** — `Plugin URI` と同じ GitHub リポを指定（`https://github.com/yuino-Yuji/yuino-contact-form`）。PUC の VCS API URL（`buildUpdateChecker` 第 1 引数）と一致する形式

#### 残作業

このプラグイン本体側のコード変更は完了。残りは：

1. v0.2.5 タグ push → `gh release create v0.2.5`
2. 配布先（2504hannan）で `?puc_check_for_updates=1&puc_slug=yuino-contact-form` を踏む → プラグイン一覧で「新しいバージョン 0.2.5 が利用可能」が出るか確認
3. ワンクリック更新で 0.2.4 → 0.2.5 への遷移が成功するか確認 → **自動更新フローの実地検証完了**

### v0.2.6（2026-05-31）

- **「アップデートを確認」リンクを更新がある時だけ表示**するよう変更 — PUC が `plugin_row_meta` フックで常時追加していたリンクを、`puc_manual_check_link-yuino-contact-form` フィルタで動的制御。`$ycf_update_checker->getUpdate()` が更新ありを示すとき（= キャッシュに新しいバージョンが乗っているとき）だけリンクテキストを通し、それ以外は空文字列を返して非表示にする
- **背景**：v0.2.4 → v0.2.5 のワンクリック更新成功後も「アップデートを確認」リンクが残り、ユーザーから「更新終了後も消えない、更新がある時だけ表示できないか」と要望
- PUC v5p7 の `Puc/v5p7/Plugin/Ui.php` の `addCheckForUpdatesLink()` は **`puc_manual_check_link-<slug>` フィルタで返された文字列を使う仕様**（コメントに明記）— 空文字列を返せばリンク自体がメタ列から消える。PUC 公式の表示制御 API なので、ライブラリの内部実装に依存しない

#### 設計判断ポイント

- **静的クロージャを使用**（`static function`） — PHP 8+ で `use` キャプチャを伴うクロージャに `static` を付けることで、`$this` の暗黙バインドを抑止して微小だが軽量化。WP プラグイン慣習でも問題なし
- **`getUpdate()` が null を返すケースの扱い**：初回チェック前 / 最新版到達後 / GitHub 到達不能時に null。いずれも空文字列 → リンク非表示。「サーバが到達不能でも誤ってリンクを出さない」点で安全側に倒れた挙動
- **強制チェック URL は引き続き使える** — `?puc_check_for_updates=1&puc_slug=yuino-contact-form` を直接踏めば、リンク非表示時でも手動チェック可能。緊急で「今すぐ更新を見にいかせたい」ケースの抜け道として保持

#### 残作業

タグ push → `gh release create v0.2.6` → ローカル WP Admin で「アップデートを確認」が（v0.2.6 へ更新完了後に）消えていることを確認
