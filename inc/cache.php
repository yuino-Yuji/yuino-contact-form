<?php
/**
 * Yuino Contact Form: フォームページをキャッシュさせない
 *
 * 入力 → 確認 → 入力（修正）の遷移は、admin-post.php から同じフォームページへ
 * リダイレクトし、Cookie のトークンでトランジェントの状態を復元して出し分けている。
 * サーバーやプラグインのページキャッシュがフォームページの HTML を保存していると、
 * リダイレクト後に PHP が動かず、キャッシュの空の入力画面が返る。
 * 利用者には「確認画面に進まず、入力内容が消えた」ように見える。
 *
 * そこでフォームページを表示するときだけ、キャッシュさせないヘッダーを送る。
 * - nocache_headers()：Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private
 *   （nginx の proxy_cache / fastcgi_cache は既定でこれに従い、レスポンスを保存しない）
 * - DONOTCACHEPAGE：WP Super Cache・W3 Total Cache 等、WordPress のページキャッシュ用の慣例の定数
 */

if (!defined('ABSPATH')) {
  exit;
}

add_action('template_redirect', 'ycf_prevent_form_page_cache');
function ycf_prevent_form_page_cache() {
  if (!ycf_is_any_form_page()) {
    return;
  }

  /**
   * フォームページをキャッシュさせないか。
   * キャッシュを別の方法で除外している等で不要な場合は false を返す。
   *
   * @param bool $prevent 既定 true
   */
  if (!apply_filters('ycf_prevent_form_page_cache', true)) {
    return;
  }

  if (!defined('DONOTCACHEPAGE')) {
    define('DONOTCACHEPAGE', true);
  }
  if (!headers_sent()) {
    nocache_headers();
  }
}
