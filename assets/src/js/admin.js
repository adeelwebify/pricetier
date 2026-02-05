
import { initRules } from './modules/rules.js';
import { initSelects } from './modules/selects.js';
import { initLookup } from './modules/lookup.js';

jQuery(function ($) {
  const config = window.PriceTierAdmin || {};
  const { ajaxUrl, nonce, ruleTemplate } = config;

  if (!ajaxUrl) return;

  // Initialize Modules
  initRules(ajaxUrl, nonce);
  initSelects(ajaxUrl, nonce);
  initLookup(ajaxUrl, nonce);

  // Dynamic Rule Addition
  const container = document.getElementById('pricetier-rules');
  const addBtn = document.getElementById('pricetier-add-rule');

  if (container && addBtn) {
    addBtn.addEventListener('click', () => {
      const index = 'rule_' + Date.now();
      const html = ruleTemplate.replace(/__INDEX__/g, index);
      const template = document.createElement('template');
      template.innerHTML = html.trim();
      
      const newNode = template.content.firstElementChild;
      container.appendChild(newNode);
      
      $(document.body).trigger('wc-enhanced-select-init');
      
      // Re-init for new node
      if (window.PriceTierInitSingleRule) window.PriceTierInitSingleRule($(newNode));
      initSelects(ajaxUrl, nonce);
    });
  }

  // Rule Deletion
  document.addEventListener('click', function (e) {
    if (!e.target.classList.contains('pricetier-delete-rule')) return;
    const rule = e.target.closest('.pricetier-rule');
    if (rule && confirm('Delete this rule? This cannot be undone.')) {
      rule.remove();
    }
  });
});
