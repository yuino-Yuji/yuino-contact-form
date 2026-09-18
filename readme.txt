=== Yuino Contact Form ===
Contributors: yuino
Tags: contact form, mail, claude code
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.2.13
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

確認画面付きの軽量お問い合わせ／資料請求フォームを、コードベースで定義できるプラグイン。Claude Code への依頼でフォームを組み立てる「Claude Code ファースト設計」。

== Description ==

Yuino 内製の軽量コンタクトフォームプラグイン。Contact Form 7 / Smart Custom Fields のような汎用ビルダーではなく、**フィールド定義をコード（テーマの `functions.php`）に書く**ことを前提とした、Claude Code への依頼で組み立てる運用に特化した設計。

**主な特徴：**

* **Claude Code ファースト設計** — フォーム定義・カスタム実装は Claude Code に依頼する想定
* **フィールドはコードで定義** — 管理画面ビルダーは持たない（CF7／SMF と棲み分け）
* **マークアップは完全カスタム可** — WooCommerce 流のテンプレート上書き対応（`themes/<theme>/yuino-contact-form/` 配下にファイルを置くだけ）
* **依存ゼロ** — 他プラグイン不要、jQuery 不要
* **送信フロー** — 入力 → 確認 → 送信 → サンクスページ
* **セキュリティ** — nonce／Cloudflare Turnstile（任意）／reply-to 自動付与／MX プリフライト（管理者宛先のドメインに MX レコードがあるか送信前検証）
* **管理画面** — 4 ステップのセットアップ案内で「次に何をすればいいか」を可視化
* **メール到達性ヘッダ** — `Auto-Submitted` / `Precedence` / `X-Auto-Response-Suppress` / `Reply-To` を自動付与し iCloud 等のスパム判定リスクを低減
* **GitHub Release 経由の自動更新** — Plugin Update Checker（PUC）を同梱、WP 管理画面に通常の「更新あり」通知が出る

**設計思想：**

Claude Code を使った WordPress サイト制作で、案件ごとにフォームの fields とメール雛形を「ゼロから書く」のではなく、**Claude Code に「Yuino Contact Form を使ってフォーム作って」と依頼して 1 回で組み立てる**形に最適化されている。依頼文は GitHub の README.md「クイックスタート」に掲載。

== Installation ==

1. プラグインを `wp-content/plugins/yuino-contact-form/` に配置
2. WP 管理画面 → プラグイン → 「Yuino Contact Form」を有効化
3. 「設定 → お問い合わせ設定」を開く → 4 ステップのセットアップ案内が現在地を表示
4. 案内に沿って各 STEP を進める（Claude Code への依頼文は GitHub の README.md「クイックスタート」参照）：
   * STEP 1：fields とメール本文を設定（Claude Code に依頼すれば同時生成）
   * STEP 2：SMTP 情報を入力
   * STEP 3：`wp-config.php` に SMTP パスワードの 1 行をユーザーが直接追記
   * STEP 4：メール件名・本文を微調整（必要なら）
5. 「運用準備完了」パネルが出れば運用開始

Claude Code を使わない手動セットアップは GitHub の README.md「10. Claude Code を利用しない場合の手動セットアップ」を参照。

== Frequently Asked Questions ==

= フィールドはどう定義するの？ =

テーマの `functions.php` で `ycf_register_forms` フィルターを使って配列で登録します。Contact Form 7 のようなショートコード書式ではなく、PHP 連想配列で `type` / `name` / `label` / `required` 等を指定。詳細は GitHub の README.md「1. フォーム定義」参照。

= マークアップを完全に上書きしたい =

`themes/<theme>/yuino-contact-form/` 配下に同名ファイル（`form-input.php` / `form-confirm.php` / `form-complete.php`）を置くだけで上書きされます。WooCommerce のテンプレートオーバーライドと同じ仕組み。詳細は README.md「4. テンプレート上書き」参照。

= SMTP はどう設定するの？ =

管理画面「設定 → お問い合わせ設定」の STEP 2 で SMTP ホスト・ポート・暗号化方式・ユーザー名・送信元メールを入力。パスワードだけは `wp-config.php` に `define('YCF_SMTP_PASSWORD', '...');` の形で書きます（Claude Code のチャット履歴に SMTP パスワードを残さないため）。Google Workspace / Microsoft 365 / 共用サーバーの設定例は管理画面の `<details>` パネルに表示されます。

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

= 0.2.13 =
* **管理画面から Claude Code への言及を削除**。設定画面は納品後にクライアントも目にするため、セットアップ案内（STEP 1〜4）・フォーム未登録時の案内・「運用準備完了」パネルを、開発ツールに依存しない手順の説明に書き換えた
* STEP 2 の「Claude Code に『SMTP 情報を設定して』と依頼することも可能」を削除。SMTP パスワードを Claude Code に渡さない方針（STEP 3）と矛盾していたため。代わりに「パスワードは次の STEP で wp-config.php に記述する」旨を案内
* Claude Code 向けの依頼文は README.md「クイックスタート」に一本化
* README.md / readme.txt の略記「CC」を「Claude Code」に統一（メールの CC 欄は除く）

= 0.2.12 =
* **自動返信と Reply-To の宛先解決を修正**（重要）。従来は `email` という名前のフィールドしか見ておらず、メール欄が `email_corp` / `email_personal` のように別名だったり複数あったりするフォームでは、**自動返信が一通も送られず、管理者通知に Reply-To も付かなかった**
* 解決順に「**フォーム定義で `type='email'` のフィールドを走査し、値が入っている最初のものを採用**」のフォールバックを追加。フィールド名に依存せず動作する
* 返信先を明示指定するための `ycf_user_email` フィルターを追加（`($email, $data, $form_key)`）
* 管理者通知の Reply-To を `ycf_resolve_user_email()` に一本化（自動返信の宛先と必ず一致するようになった）
* `ycf_resolve_user_email()` に第 2 引数 `$form_key` を追加（省略可。既存の呼び出しはそのまま動作）

= 0.2.11 =
* **確認画面に送信エラーが表示されない不具合を修正**。Turnstile 検証失敗・管理者通知の送信失敗は step=confirm で差し戻されるが、`form-section.php` が確認画面テンプレートに `errors` を渡しておらず、画面に何も出ないため利用者からは「送信ボタンを押しても無反応」に見えていた
* `form-confirm.php` に `_global` エラーの表示（`.ContactForm__GlobalError` / `role="alert"`）を追加。入力画面と同じマークアップ規約に揃えた
* README: `option_ycf_settings` は設定を一度も保存していない間は発火せず `default_option_ycf_settings` を通る点を注記（両方に登録が必要）

= 0.2.10 =
* フォーム定義に `anchor` オプションを追加。指定すると入力⇄確認のリダイレクト先に `#<id>` が付き、**確認画面がページ最上部ではなくフォーム位置で表示**される（未指定時は従来どおり）
* 固定ヘッダーのあるテーマでは、対象要素に `scroll-margin-top: <ヘッダー高>` を併せて指定すること

= 0.2.9 =
* `type='tel'` の入力を検証前に**半角へ自動正規化**（全角数字・全角/各種ハイフン・空白・丸括弧を吸収）。日本語環境で IME が全角のまま入力され確認画面へ進めなくなる問題を解消
* `type='contact_value'` も連絡方法が `tel` のときは同じ正規化を適用

= 0.2.8 =
* `type='tel'` フィールドに標準バリデーションを追加（**半角数字とハイフン(-)のみ許可**。それ以外の文字は確認画面へ進めずエラー表示）
* ラジオボタン（`type='radio'`）の初期選択を改善（送信値 > 明示 `default` > **最初の選択肢** の優先順で、常にいずれかを selected に）

= 0.2.7 =
* README.md に「12. 他の WordPress サイトに導入する」セクションを追加（Claude Code を使った導入のテンプレ依頼文・手動セットアップ手順・共有時チェックリストを整備）

= 0.2.6 =
* プラグイン一覧の「アップデートを確認」リンクを**更新がある時だけ表示**する挙動に変更（PUC の `puc_manual_check_link-<slug>` フィルタ経由）

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

= 0.2.6 =
「アップデートを確認」リンクが常時表示から「更新がある時だけ表示」に変わります。

= 0.2.7 =
他サイトへの導入手順（Claude Code 向けテンプレ依頼文・手動セットアップ手順）を README に整備しました。コードベースの変更はありません。
