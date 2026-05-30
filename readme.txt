=== Yuino Contact Form ===
Contributors: yuino
Tags: contact form, mail, claude code, cc-first
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.2.5
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

確認画面付きの軽量お問い合わせ／資料請求フォームを、コードベースで定義できるプラグイン。Claude Code（CC）への依頼でフォームを組み立てる「CC ファースト設計」。

== Description ==

Yuino 内製の軽量コンタクトフォームプラグイン。Contact Form 7 / Smart Custom Fields のような汎用ビルダーではなく、**フィールド定義をコード（テーマの `functions.php`）に書く**ことを前提とした、CC（Claude Code）への依頼で組み立てる運用に特化した設計。

**主な特徴：**

* **Claude Code（CC）ファースト設計** — フォーム定義・カスタム実装は CC に依頼する想定
* **フィールドはコードで定義** — 管理画面ビルダーは持たない（CF7／SMF と棲み分け）
* **マークアップは完全カスタム可** — WooCommerce 流のテンプレート上書き対応（`themes/<theme>/yuino-contact-form/` 配下にファイルを置くだけ）
* **依存ゼロ** — 他プラグイン不要、jQuery 不要
* **送信フロー** — 入力 → 確認 → 送信 → サンクスページ
* **セキュリティ** — nonce／Cloudflare Turnstile（任意）／reply-to 自動付与／MX プリフライト（管理者宛先のドメインに MX レコードがあるか送信前検証）
* **管理画面** — 4 ステップのオンボーディング UI で「次に何をすればいいか」を可視化
* **メール到達性ヘッダ** — `Auto-Submitted` / `Precedence` / `X-Auto-Response-Suppress` / `Reply-To` を自動付与し iCloud 等のスパム判定リスクを低減
* **GitHub Release 経由の自動更新** — Plugin Update Checker（PUC）を同梱、WP 管理画面に通常の「更新あり」通知が出る

**設計思想：**

CC を使った WordPress サイト制作で、案件ごとにフォームの fields とメール雛形を「ゼロから書く」のではなく、**「Yuino Contact Form を使ってフォーム作って」と CC に依頼すれば管理画面のオンボーディング UI が依頼文を提示する**形に最適化されている。

== Installation ==

1. プラグインを `wp-content/plugins/yuino-contact-form/` に配置
2. WP 管理画面 → プラグイン → 「Yuino Contact Form」を有効化
3. 「設定 → お問い合わせ設定」を開く → 4 ステップ オンボーディング UI が現在地を表示
4. UI に表示されている依頼文を CC にコピペで投げるだけで各 STEP が進む：
   * STEP 1：fields とメール本文を同時生成
   * STEP 2：SMTP 情報を入力
   * STEP 3：`wp-config.php` に SMTP パスワードの 1 行をユーザーが直接追記
   * STEP 4：メール件名・本文を微調整（必要なら）
5. 「運用準備完了」パネルが出れば運用開始

CC を使わない手動セットアップは GitHub の README.md「10. Claude Code を利用しない場合の手動セットアップ」を参照。

== Frequently Asked Questions ==

= フィールドはどう定義するの？ =

テーマの `functions.php` で `ycf_register_forms` フィルターを使って配列で登録します。Contact Form 7 のようなショートコード書式ではなく、PHP 連想配列で `type` / `name` / `label` / `required` 等を指定。詳細は GitHub の README.md「1. フォーム定義」参照。

= マークアップを完全に上書きしたい =

`themes/<theme>/yuino-contact-form/` 配下に同名ファイル（`form-input.php` / `form-confirm.php` / `form-complete.php`）を置くだけで上書きされます。WooCommerce のテンプレートオーバーライドと同じ仕組み。詳細は README.md「4. テンプレート上書き」参照。

= SMTP はどう設定するの？ =

管理画面「設定 → お問い合わせ設定」の STEP 2 で SMTP ホスト・ポート・暗号化方式・ユーザー名・送信元メールを入力。パスワードだけは `wp-config.php` に `define('YCF_SMTP_PASSWORD', '...');` の形で書きます（CC のチャット履歴に SMTP パスワードを残さないため）。Google Workspace / Microsoft 365 / 共用サーバーの設定例は管理画面の `<details>` パネルに表示されます。

= Cloudflare Turnstile は必須？ =

任意。管理画面で Site Key / Secret Key を入力すると有効化されます。Secret Key は v0.2.0 から DB 保存（管理画面入力）に変更（業界標準と整合）。

= 自動更新はどう動くの？ =

このプラグインには [Plugin Update Checker（PUC）](https://github.com/YahnisElsts/plugin-update-checker)（MIT）が同梱されており、`https://github.com/yuino-Yuji/yuino-contact-form/` の最新 Release を 12 時間ごとに監視します。新しい Release タグが登場すれば、WP 管理画面 → プラグイン一覧に通常の「更新あり」通知が出るので、ワンクリックで更新できます。GitHub Personal Access Token は不要（public リポジトリのため）。

= メールが届かない（特に iCloud 宛て自動返信） =

v0.2.3 で自動返信メールに `Auto-Submitted: auto-replied` 等の到達性向上ヘッダを 4 種追加していますが、それでも届かない場合は送信元ドメイン側の **SPF / DKIM / DMARC** が DNS で整備されているか確認してください。プラグイン同梱ヘッダだけでは認証 DNS の代替にはなりません。詳細は README.md「5. 管理画面で設定する項目 → メール到達性」参照。

= MX プリフライトとは？ =

管理者宛先（送信先メールアドレス）のドメインに MX レコードが存在するか、設定保存時にチェックする機能。MX が存在しないドメイン宛にしようとすると `admin_notices` で警告が出ます。「存在しないメールアドレスに送信する」事故を防止する目的。

= テンプレートはどこ？ =

プラグイン本体の `templates/` 配下に `form-input.php` / `form-confirm.php` / `form-complete.php` が同梱されています。テーマ側でこれらを上書きする場合は `themes/<theme>/yuino-contact-form/` 配下に同名ファイルを置きます。

= プラグイン本体を直接編集してもいい？ =

非推奨。`wp-config.php` に `DISALLOW_FILE_EDIT` 推奨。本体を編集すると次回の自動更新で上書きされます。カスタムが必要な場合は `ycf_register_forms` フィルターや `option_ycf_settings` フィルター、テーマ側のテンプレート上書きで対応してください。

== Changelog ==

= 0.2.5 =
* Plugin Header に `Update URI` を追加（同名の .org プラグインからの誤上書き防止）
* `readme.txt` を新規同梱（WP プラグイン一覧「詳細を表示」モーダルに使い方を表示）

= 0.2.4 =
* Plugin Update Checker（PUC）v5.7 を `vendor/plugin-update-checker/` に同梱
* GitHub Release 経由の自動更新を実装（main ブランチを 12 時間ごとに監視）
* `.gitignore` から `vendor/` を除外（PUC は配布物に同梱するため）
* README.md / HANDOFF.md に運用フロー追記

= 0.2.3 =
* 自動返信メールに到達性向上ヘッダ 4 種を追加：
  * `Auto-Submitted: auto-replied`（RFC 3834）
  * `Precedence: auto_reply`
  * `X-Auto-Response-Suppress: All`（Outlook/Exchange）
  * `Reply-To: <管理者通知 To の先頭>`
* iCloud 等の厳格なスパムフィルタによる silent reject を回避
* README.md に「メール到達性のための DNS 整備チェックリスト」追加

= 0.2.2 =
* 入力画面の Cloudflare Turnstile 重複表示を削除（確認画面のみで検証する設計に整理）
* Plugin Header / `YCF_VERSION` 定数を v0.2 系に同期（バンプ漏れ解消）

= 0.2.1 =
* オンボーディング UI の STEP 1 依頼文を、fields 登録とメール雛形オーバーライドの「同時実行版」に統合

= 0.2.0 =
* 4 ステップ オンボーディング UI を新規追加（`forms_unregistered` → `smtp_unset` → `smtp_password_unset` → `mail_body_default` → `ready` を判定）
* MX プリフライト機能を新規追加（管理者宛先ドメインの MX レコード検証）
* SMTP プロバイダ案内テーブル（Google Workspace / Microsoft 365 / 共用サーバー）を `<details>` で表示
* メール雛形プリセット（プラグイン同梱版）— `inc/config.php` の `ycf_get_default_form_mail_settings()` に標準雛形をハードコード
* Turnstile Secret Key を定数管理 → DB 保存に変更（管理画面入力、業界標準と整合）
* 管理画面の件名欄を倍幅化（`large-text` クラス適用）

== Upgrade Notice ==

= 0.2.4 =
このバージョンから WP 管理画面に「更新あり」通知が出るようになります。以降のバージョンアップはワンクリックで適用可能。

= 0.2.5 =
WP プラグイン一覧の「詳細を表示」モーダルでこの readme が表示されるようになります。
