// codecorn/elementor-submissions/assets/js/submissions-list-read-column.js
jQuery(function ($) {
  var INIT_DELAY = 650;
  var REINIT_DELAY = 200;
  var initTimer = null;

  function scheduleInit(delay) {
    clearTimeout(initTimer);
    initTimer = setTimeout(initCcReadColumn, delay || REINIT_DELAY);
  }

  function rowId($tr) {
    var idCell = $tr.find("td[data-colname='ID'], td.column-id");
    var id = $.trim(idCell.text());
    if (id && /^\d+$/.test(id)) return id;

    var href = $tr.find("a[href*='e-form-submissions#/']").attr("href") || "";
    var m = href.match(/#\/(\d+)/);
    return m ? m[1] : "";
  }

  function initCcReadColumn() {
    var $return_on_focus = $(".e-form-submissions-main__header");
    if($return_on_focus?.length > 0) return; // non siamo nel loop
    var $table = $(".wp-list-table");
    if (!$table.length) return;

    // 🔹 TH : aggiungi una sola volta
    if (!$table.find("th.column-cc-read").length) {
      var th = $(
        '<th class="column-cc-read" style="width:70px;text-align:center">Letta</th>'
      );
      $table.find("thead tr, tfoot tr").each(function () {
        $(this).append(th.clone());
      });
    }

    // 🔹 TD per ogni riga , solo se non esiste ancora la colonna
    $table.find("tbody tr").each(function () {
      var $tr = $(this);
      if (!$tr.is(":visible")) return;

      // se la riga ha già la colonna , skippa
      if ($tr.find("td.column-cc-read").length) return;

      var id = rowId($tr);
      var $td = $(
        '<td class="column-cc-read" style="text-align:center"></td>'
      ).append('<span class="dashicons dashicons-update spin"></span>');
      $tr.append($td);

      if (!id) {
        $td.html(
          '<span class="dashicons dashicons-warning" title="ID non trovato"></span>'
        );
        return;
      }

      $.get(
        CCSUB.ajax,
        { action: "cc_sub_read", nonce: CCSUB.nonce, id: id },
        function (resp) {
          if (!resp || !resp.success) {
            $td.html(
              '<span class="dashicons dashicons-no" title="Errore"></span>'
            );
            return;
          }
          var on = parseInt(resp.data.is_read, 10) ? 1 : 0;
          var ic = $('<span class="dashicons"></span>').addClass(
            on ? "dashicons-yes-alt" : "dashicons-email"
          );

          $td
            .empty()
            .append(ic)
            .css("color", on ? "#16a34a" : "#ef4444")
            .attr("title", on ? "Letta" : "Non letta");
        }
      );
    });
  }

  // ▶️ prima init dopo il load della pagina
  scheduleInit(INIT_DELAY);

  // ▶️ quando clicchi sulle pagine ( ‹ › » ecc . ) chiediamo un re init
  $(document).on("click", ".tablenav-pages a", function () {
    scheduleInit(800);
  });

  // ▶️ fallback robusto : osserva cambi nel DOM e re init quando cambia la tabella
  var observer;
  try {
    observer = new MutationObserver(function (mutations) {
      var needs = false;
      for (var i = 0; i < mutations.length; i++) {
        if (mutations[i].type === "childList") {
          // se tra i nodi c’è dentro / vicino una wp-list-table , trigghiamo
          if (
            $(mutations[i].target).closest(".wp-list-table").length ||
            $(mutations[i].addedNodes).find(".wp-list-table").length
          ) {
            needs = true;
            break;
          }
        }
      }
      if (needs) {
        scheduleInit();
      }
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });
  } catch (e) {
    // se MutationObserver non esiste ( browser vecchissimi ) pazienza , viviamo solo di click + init iniziale
  }
});
