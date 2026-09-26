/**
 * Yuino Contact Form: フロントエンド連動処理
 *
 * 全て data-* 属性のみで動作する（CSSクラス名には依存しない）。
 * テーマ側でテンプレートを差し替えても、以下のフックさえ維持されれば動作する：
 *
 *   - 入力フォーム要素：[data-ycf-form][data-ycf-step="confirm"]
 *   - 連絡方法ラジオ ：[data-ycf-method]
 *   - 連絡先入力欄  ：[data-ycf-contact-input]
 *   - 連絡先ヘルプ文 ：[data-ycf-contact-help]
 *   - 個人情報リンク ：[data-ycf-privacy-link]
 *   - 同意チェック  ：[data-ycf-agree="true"]
 *   - 同意ヘルプ文  ：[data-ycf-agree-note]
 *   - 確認画面送信フォーム：[data-ycf-submit-form]
 */
(function () {
  "use strict";

  const CONTACT_VALUE_META = {
    mail: {
      placeholder: "例）sample@xxx.com",
      inputmode: "email",
      pattern: "",
      autocomplete: "email",
      description: "メールアドレスをご入力ください",
    },
    tel: {
      placeholder: "例）09012345678",
      inputmode: "numeric",
      pattern: "[0-9]{10,13}",
      autocomplete: "tel",
      description: "半角数字のみ（ハイフンなし）でご入力ください",
    },
    line: {
      placeholder: "例）@yourid",
      inputmode: "text",
      pattern: "",
      autocomplete: "off",
      description: "LINE ID をご入力ください",
    },
  };

  document.addEventListener("DOMContentLoaded", function () {
    initConnectionMethodSwitch();
    initPrivacyAgreeUnlock();
    initSubmitGuard();
  });

  function initConnectionMethodSwitch() {
    const forms = document.querySelectorAll('[data-ycf-form][data-ycf-step="confirm"]');
    if (!forms.length) return;

    forms.forEach(function (form) {
      const radios = form.querySelectorAll('input[type="radio"][data-ycf-method]');
      const input = form.querySelector("[data-ycf-contact-input]");
      const help = form.querySelector("[data-ycf-contact-help]");
      if (!radios.length || !input) return;

      radios.forEach(function (radio) {
        radio.addEventListener("change", function () {
          if (!radio.checked) return;
          applyContactMeta(radio.value, input, help);
        });
      });

      const checked = form.querySelector(
        'input[type="radio"][data-ycf-method]:checked'
      );
      if (checked) {
        applyContactMeta(checked.value, input, help);
      }
    });
  }

  function applyContactMeta(method, input, helpEl) {
    const meta = CONTACT_VALUE_META[method] || CONTACT_VALUE_META.mail;
    input.setAttribute("placeholder", meta.placeholder);
    input.setAttribute("inputmode", meta.inputmode);
    input.setAttribute("autocomplete", meta.autocomplete);
    if (meta.pattern) {
      input.setAttribute("pattern", meta.pattern);
    } else {
      input.removeAttribute("pattern");
    }
    if (helpEl) {
      helpEl.textContent = meta.description;
    }
  }

  function initPrivacyAgreeUnlock() {
    const links = document.querySelectorAll("[data-ycf-privacy-link]");
    if (!links.length) return;

    links.forEach(function (link) {
      const form = link.closest("[data-ycf-form]");
      const scope = form || document;

      const checkbox = scope.querySelector(
        'input[type="checkbox"][data-ycf-agree="true"]'
      );
      if (!checkbox) return;

      const note = scope.querySelector("[data-ycf-agree-note]");

      if (!checkbox.disabled) {
        hideAgreeNote(note);
        return;
      }

      link.addEventListener("click", function () {
        checkbox.disabled = false;
        checkbox.removeAttribute("aria-describedby");
        hideAgreeNote(note);
      });
    });
  }

  function hideAgreeNote(note) {
    if (!note) return;
    note.setAttribute("hidden", "hidden");
    note.style.display = "none";
  }

  function initSubmitGuard() {
    const forms = document.querySelectorAll(
      '[data-ycf-form][data-ycf-step="confirm"], [data-ycf-submit-form]'
    );
    // 送信ボタンは form の中とは限らない（テンプレートによっては form 属性で外から結び付ける）。
    // form.elements は form 属性で結び付いたボタンも含むので、こちらから探す。
    const submitButtonOf = function (form) {
      return Array.prototype.find.call(form.elements, function (el) {
        return el.type === "submit";
      });
    };

    forms.forEach(function (form) {
      form.addEventListener("submit", function (event) {
        const button = event.submitter || submitButtonOf(form);
        if (!button) return;
        // setTimeout の中では、この送信に登録された全ハンドラーの実行が終わっている。
        // テーマ側の追加検証などが preventDefault() で送信を止めた場合は、
        // ページ遷移が起きないので無効化しない（無効化すると入力を直しても再送信できなくなる）。
        setTimeout(function () {
          if (event.defaultPrevented) return;
          button.disabled = true;
          button.classList.add("is-loading");
        }, 0);
      });
    });

    // ブラウザの「戻る」でページがキャッシュ（bfcache）から復元されると、
    // 送信時に無効化したボタンがそのまま残る。復元時に元へ戻す。
    window.addEventListener("pageshow", function (event) {
      if (!event.persisted) return;
      forms.forEach(function (form) {
        const button = submitButtonOf(form);
        if (!button) return;
        button.disabled = false;
        button.classList.remove("is-loading");
      });
    });
  }
})();
