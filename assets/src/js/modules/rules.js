/**
 * Rules UI Logic
 * Handles toggling conditions and users
 */

/* ---------------------------------------------------------------------
 * Helpers
 * -------------------------------------------------------------------*/

function updateProductConditions($rule) {
  const type = $rule.find('input[type=radio][name*="[products][type]"]:checked').val();
  $rule.find('.pricetier-condition').hide();

  if (!type || type === 'all') return;

  $rule.find('.pricetier-condition-' + type).show();
  if (type === 'attribute') {
    $rule.find('.pricetier-condition-attribute-terms').show();
  }
}

function updatePricingConditions($rule) {
  const type = $rule.find('input[name*="[pricing][type]"]:checked').val();
  $rule.find('.pricetier-label-percent').hide();
  $rule.find('.pricetier-label-fixed').hide();

  if (type === 'percent') $rule.find('.pricetier-label-percent').show();
  if (type === 'fixed') $rule.find('.pricetier-label-fixed').show();
}

function initUserSelect($select, ajaxUrl, nonce) {
  if ($select.hasClass('enhanced')) return;

  $select.addClass('enhanced').selectWoo({
    ajax: {
      url: ajaxUrl,
      dataType: 'json',
      delay: 250,
      data: function (params) {
        return {
          action: 'pricetier_search_users',
          search: params.term || '',
          nonce: nonce,
        };
      },
      processResults: function (response) {
        return { results: response.success ? response.data : [] };
      },
    },
    placeholder: 'Search users…',
    minimumInputLength: 1,
    width: '100%',
  });
}

function updateUserConditions($rule, ajaxUrl, nonce) {
  const type = $rule.find('input[name*="[users][type]"]:checked').val();
  $rule.find('.pricetier-user-condition').hide();

  if (!type || type === 'all') return;

  const $container = $rule.find('.pricetier-user-condition-' + type);
  $container.show();

  if (type === 'users') {
    const $select = $container.find('.pricetier-user-select');
    requestAnimationFrame(() => {
      initUserSelect($select, ajaxUrl, nonce);
    });
  }
}

/* ---------------------------------------------------------------------
 * Main Init
 * -------------------------------------------------------------------*/

export function initRules(ajaxUrl, nonce) {
  const $ = window.jQuery;

  // Logic to update a single specific rule block
  function initSingleRule($rule) {
    updateProductConditions($rule);
    updateUserConditions($rule, ajaxUrl, nonce);
    updatePricingConditions($rule);

    $rule
      .find('input[type=radio]')
      .off('change.pricetier')
      .on('change.pricetier', function () {
        updateProductConditions($rule);
        updateUserConditions($rule, ajaxUrl, nonce);
        updatePricingConditions($rule);
      });

    // Specific user type toggle listener (redundancy check kept from original)
    $rule
      .find('input[name*="[users][type]"]')
      .off('change.pricetier_user')
      .on('change.pricetier_user', function () {
        updateUserConditions($rule, ajaxUrl, nonce);
      });
  }

  // Init all existing
  $('.pricetier-rule').each(function () {
    initSingleRule($(this));
  });

  // Export for dynamic adding
  window.PriceTierInitSingleRule = initSingleRule;
}
