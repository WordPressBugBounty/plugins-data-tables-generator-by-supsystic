// console.log('DTGS_NONCE is enabled');
jQuery(document).ready(function () {
  jQuery(".dtsFrontendExport").each(function () {
    let link = jQuery(this).find("[class^='export-']");
    if (link.length) {
      let href = link.attr("href");
      if (href) {
        href = href.replace(
          /nonce=SUPNONCEFRONTEND/,
          "nonce=" + DTGS_NONCE_FRONTEND
        );
        link.attr("href", href);
      }
    }
  });
});
