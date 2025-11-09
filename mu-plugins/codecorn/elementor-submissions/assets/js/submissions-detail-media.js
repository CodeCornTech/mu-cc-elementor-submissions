// codecorn/elementor-submissions/assets/js/submissions-detail-media.js
jQuery(function ($) {
  const THIS_DBG = false;

  const imgRegex = /\.(jpe?g|png|gif|webp|avif|svg)$/i;
  const vidRegex = /\.(mp4|webm|ogg|mov|m4v)$/i;

  const interval = setInterval(() => {
    const $table = $(".e-form-submissions-item-table");
    if ($table.length && $table.find("tr").length > 0) {
      clearInterval(interval);
      THIS_DBG && console.log("✅ Table ready , initializing media preview");
      initMediaPreview();
    }
  }, 300);

  /**
   * Richiede al server la secure URL per un media di Elementor forms.
   * Se qualcosa va storto, torna comunque l’URL originale.
   *
   * @param {string} originalUrl
   * @param {function(string)} cb
   */
  function getSecureUrl(originalUrl, cb) {
    if (
      typeof CCSUB_SEC === "undefined" ||
      !CCSUB_SEC.ajax ||
      !CCSUB_SEC.nonce
    ) {
      // fallback , ma in produzione con htaccess attivo la preview non funzionerà
      THIS_DBG &&
        console.warn(
          "[CC_ES] CCSUB_SEC non definito , uso URL originale (no hash)"
        );
      cb(originalUrl);
      return;
    }

    $.get(
      CCSUB_SEC.ajax,
      {
        action: "cc_sub_secure_url",
        nonce: CCSUB_SEC.nonce,
        url: originalUrl,
      },
      function (resp) {
        if (resp && resp.success && resp.data && resp.data.secure) {
          cb(resp.data.secure);
        } else {
          THIS_DBG &&
            console.warn("[CC_ES] secure url fallita , uso originale", resp);
          cb(originalUrl);
        }
      }
    );
  }
  function initMediaPreview() {
    const $rows = $(".e-form-submissions-item-table tr");

    $rows.each(function () {
      const $valueCell = $(this).find("td").eq(1);
      const $links = $valueCell.find('a[href^="http"]');
      if (!$links.length) return;

      let cleared = false;
      let appended = 0;

      $links.each(function () {
        const $link = $(this);
        const href = $link.attr("href") || "";
        if (!href || href.indexOf("uploads/") === -1) return;

        const isImg = imgRegex.test(href);
        const isVid = vidRegex.test(href);
        if (!isImg && !isVid) return;

        getSecureUrl(href, function (secureUrl) {
          if (!cleared) {
            $valueCell.empty().addClass("cc-ef-media-multi");
            cleared = true;
          }

          if (isImg) {
            $valueCell.append(makeImagePreviewElement(secureUrl));
          } else if (isVid) {
            $valueCell.append(makeVideoPreviewElement(secureUrl));
          }

          appended++;
          THIS_DBG && console.log("Appended media", appended);
        });
      });
    });
  }

  function makeImagePreviewElement(url) {
    const $wrapper = $('<div class="cc-ef-media-wrapper" />');
    const $thumbLink = $('<a class="cc-ef-media-thumb-link" href="#" />');
    const $thumb = $(
      '<img class="cc-ef-media-thumb" loading="lazy" alt="Anteprima" />'
    ).attr("src", url);
    $thumbLink.append($thumb);
    $wrapper.append($thumbLink);

    const $meta = $('<div class="cc-ef-media-meta" />');
    const $openLink = $(
      '<a class="cc-ef-media-download button button-small button-primary" target="_blank" rel="noreferrer">Scarica Foto</a>'
    ).attr("href", url);
    $meta.append($openLink);
    $wrapper.append($meta);

    $thumbLink.on("click", function (e) {
      e.preventDefault();
      openLightbox("image", url);
    });

    return $wrapper;
  }

  function makeVideoPreviewElement(url) {
    const $wrapper = $('<div class="cc-ef-media-wrapper" />');
    const $thumbLink = $('<a class="cc-ef-media-thumb-link" href="#" />');
    const $video = $('<video class="cc-ef-media-thumb" muted />').attr(
      "src",
      url
    );
    $thumbLink.append($video);
    $wrapper.append($thumbLink);

    const $meta = $('<div class="cc-ef-media-meta" />');
    const $openLink = $(
      '<a class="cc-ef-media-download button button-small button-primary" target="_blank" rel="noreferrer">Scarica Video</a>'
    ).attr("href", url);
    $meta.append($openLink);
    $wrapper.append($meta);

    $thumbLink.on("click", function (e) {
      e.preventDefault();
      openLightbox("video", url);
    });

    return $wrapper;
  }

  function ensureLightbox() {
    let $overlay = $(".cc-ef-lightbox");
    if (!$overlay.length) {
      $overlay = $(`
        <div class="cc-ef-lightbox">
          <div class="cc-ef-lightbox-inner">
            <div class="cc-ef-lightbox-header">
              <a href="#" class="button button-secondary button-small button-primary cc-ef-lightbox-download" download>Scarica</a>
              <button type="button" class="button button-secondary cc-ef-lightbox-close">Chiudi</button>
            </div>
            <div class="cc-ef-lightbox-content"></div>
          </div>
        </div>
      `);
      $("body").append($overlay);

      $overlay.on("click", function (e) {
        if ($(e.target).is(".cc-ef-lightbox , .cc-ef-lightbox-close")) {
          closeLightbox();
        }
      });
    }
    return $overlay;
  }

  function openLightbox(type, url) {
    const $overlay = ensureLightbox();
    const $content = $overlay.find(".cc-ef-lightbox-content").empty();
    const $download = $overlay.find(".cc-ef-lightbox-download");

    // aggiorna href / download ogni volta
    $download.attr("href", url).attr("download", "");

    if (type === "image") {
      $('<img alt="Anteprima" />').attr("src", url).appendTo($content);
    } else if (type === "video") {
      $("<video controls autoplay />").attr("src", url).appendTo($content);
    }
    $overlay.addClass("is-visible");
  }

  function closeLightbox() {
    $(".cc-ef-lightbox").removeClass("is-visible");
  }
});
