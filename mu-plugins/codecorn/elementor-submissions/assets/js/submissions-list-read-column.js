// codecorn/elementor-submissions/assets/js/submissions-list-read-column.js
jQuery(function ($) {
  var table = $(".wp-list-table");
  if (!table.length) return;

  // aggiungi TH in testa e coda
  var th = $(
    '<th class="column-cc-read" style="width:70px;text-align:center">Letta</th>'
  );
  table.find("thead tr, tfoot tr").each(function () {
    $(this).append(th.clone());
  });

  function rowId($tr) {
    var idCell = $tr.find("td[data-colname='ID'], td.column-id");
    var id = $.trim(idCell.text());
    if (id && /^\d+$/.test(id)) return id;

    var href = $tr.find("a[href*='e-form-submissions#/']").attr("href") || "";
    var m = href.match(/#\/(\d+)/);
    return m ? m[1] : "";
  }

  setTimeout(function () {
    table.find("tbody tr").each(function () {
      var $tr = $(this);
      if (!$tr.is(":visible")) return;

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
  }, 650);
});
