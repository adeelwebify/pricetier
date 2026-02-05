
/**
 * Select2 and Dependency Initialization
 */
export function initSelects(ajaxUrl, nonce) {
  /* Products (Woo native search) */
  $('.wc-product-search')
    .filter(':not(.enhanced)')
    .each(function () {
      $(this).addClass('enhanced').select2();
    });

  /* Generic enhanced selects */
  $('.wc-enhanced-select').not('select[name*="[users][users]"]').select2();

  /* Attribute → terms dependency */
  $('select[name*="[products][attribute][taxonomy]"]')
    .off('change.pricetier')
    .on('change.pricetier', function () {
      const $taxonomySelect = $(this);
      const taxonomy = $taxonomySelect.val();
      const $rule = $taxonomySelect.closest('.pricetier-rule');
      const $termsSelect = $rule.find('select[name*="[products][attribute][terms]"]');

      $termsSelect.empty();

      if (!taxonomy) return;

      $.ajax({
        url: ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'pricetier_get_attribute_terms',
          taxonomy: taxonomy,
          nonce: nonce,
        },
        success: function (response) {
          if (!response.success) return;

          response.data.forEach(function (term) {
            const option = new Option(term.text, term.id, false, false);
            $termsSelect.append(option);
          });

          $termsSelect.trigger('change');
        },
      });
    });
}
