# PriceTier

**Cost-based pricing tiers for selected WooCommerce users.**

PriceTier allows you to implement complex pricing strategies based on your WooCommerce product costs and user roles/meta. Perfect for B2B stores, wholesale programs, or membership sites that need cost-plus pricing.

## Key Features

*   **Cost-Based Pricing**: Dynamically calculate prices based on a product's Cost of Goods (compatible with any meta key, e.g., `_wc_cog_cost`).
*   **Flexible Rules Logic**:
    *   Target specific Products, Categories, or Tags.
    *   Target specific Users or Roles.
    *   Target specific Product Attributes (e.g., "Brand: Nike").
*   **Dynamic Calculations**: Support for Fixed Adjustments, Percentage Markups, or Discounts.
*   **Smart Rounding & Bounds**: Round prices up/down/nearest and set strict Minimum/Maximum price limits.
*   **Cost Lookup Tool**: Instantly verify a product's cost and calculated price directly from the settings page.

## Developers

This plugin is built with modern standards in mind:
*   Completely modular architecture.
*   Vite + SCSS build pipeline.
*   Strict security practices (Nonce verification, Input sanitization, Directory protection).

## Installation

1.  Upload the `pricetier` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to `WooCommerce > PriceTier` to configure your rules.

## Frequently Asked Questions

**Does this modify the database prices?**
No. PriceTier filters prices on the fly using standard WooCommerce hooks. Your database prices remain untouched.

**Can I use this with other pricing plugins?**
Likely yes, but it depends on priority. PriceTier uses standard price filters. You can adjust the rule priority within the plugin settings.

**Where do I set the "Cost" of a product?**
PriceTier doesn't add a cost field itself. It reads from existing meta keys found in popular Cost of Goods plugins (or custom fields). You can define which meta key to use in the Global Settings.

## Changelog

**1.0.0**
*   Initial release.
*   Implemented robust Rule Engine, Cost Lookup Tool, and Admin Interface.
*   Secured with PRG pattern options and rigorous input validation.
