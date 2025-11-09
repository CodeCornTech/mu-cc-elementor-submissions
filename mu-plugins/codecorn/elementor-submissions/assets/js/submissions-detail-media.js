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

  function initMediaPreview() {
    const $rows = $(".e-form-submissions-item-table tr");

    $rows.each(function () {
      const $valueCell = $(this).find("td").eq(1);
      const $link = $valueCell.find('a[href^="http"]').first();
      if (!$link.length) return;

      const href = $link.attr("href") || "";
      if (!href || href.indexOf("uploads/") === -1) return;

      if (imgRegex.test(href)) {
        makeImagePreview($valueCell, href);
      } else if (vidRegex.test(href)) {
        makeVideoPreview($valueCell, href);
      }
    });
  }
  function makeImagePreview($cell, url) {
    const $wrapper = $('<div class="cc-ef-media-wrapper" />');
    const $thumbLink = $('<a class="cc-ef-media-thumb-link" href="#" />');
    const $thumb = $(
      '<img class="cc-ef-media-thumb" loading="lazy" alt="Anteprima" />'
    ).attr("src", url);

    $thumbLink.append($thumb);
    $wrapper.append($thumbLink);

    const $meta = $('<div class="cc-ef-media-meta" />');
    const $openLink = $(
      '<a target="_blank" rel="noreferrer">Apri file originale</a>'
    ).attr("href", url);

    $meta.append($openLink);
    $wrapper.append($meta);

    $cell.empty().append($wrapper);

    $thumbLink.on("click", function (e) {
      e.preventDefault();
      openLightbox("image", url);
    });
  }

  function makeVideoPreview($cell, url) {
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
      '<a target="_blank" rel="noreferrer">Apri video originale</a>'
    ).attr("href", url);

    $meta.append($openLink);
    $wrapper.append($meta);

    $cell.empty().append($wrapper);

    $thumbLink.on("click", function (e) {
      e.preventDefault();
      openLightbox("video", url);
    });
  }

  function ensureLightbox() {
    let $overlay = $(".cc-ef-lightbox");
    if (!$overlay.length) {
      $overlay = $(`
                        <div class="cc-ef-lightbox">
                        <div class="cc-ef-lightbox-inner">
                            <a href="#" class="button button-secondary button-small cc-ef-lightbox-download" download>Scarica</a>
                            <button type="button" class="button button-secondary cc-ef-lightbox-close">Chiudi</button>
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
