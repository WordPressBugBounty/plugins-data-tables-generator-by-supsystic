(function ($, app) {
  var TablesModel = app.Models._Tables;

  TablesModel.prototype.setWooSettings = function (id, settings) {
    if ((SDT_DATA.isWooCatalogEnabled || SDT_DATA.isWooPro) && settings.length) {
      if (isNaN((id = parseInt(id)))) {
        throw new Error('Invalid table id.');
      }

      return app.request(
        {
          module: 'woocommerce',
          action: 'saveWoocommerceSettings',
          nonce: DTGS_NONCE,
        },
        { id: id, settings: settings.serialize() }
      );
    }
  };
})(window.jQuery, window.supsystic.Tables);
