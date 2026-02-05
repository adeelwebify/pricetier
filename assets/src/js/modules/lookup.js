/**
 * Cost Lookup Tool
 */
export function initLookup(ajaxUrl, nonce) {
  const $ = window.jQuery;
  const $input = $('#pricetier-lookup-input');
  const $result = $('#pricetier-lookup-result');

  if (!$input.length) return;

  $input.on('change', function () {
    const pid = $(this).val();
    if (!pid) {
      $result.slideUp();
      return;
    }

    $result.css('opacity', 0.5);

    $.ajax({
      url: ajaxUrl,
      type: 'POST',
      dataType: 'json',
      data: {
        action: 'pricetier_lookup_product',
        product_id: pid,
        nonce: nonce,
      },
      success: function (response) {
        $result.css('opacity', 1);
        if (!response.success) {
          alert(response.data.message || 'Error loading product');
          return;
        }

        const data = response.data;
        $('#pt-result-key').text(data.cost_key);
        $('#pt-result-cost').html(data.cost);
        $('#pt-result-regular').html(data.regular);
        $('#pt-result-sale').html(data.sale);

        $result.slideDown();
      },
      error: function () {
        $result.css('opacity', 1);
        alert('Request failed');
      },
    });
  });
}
