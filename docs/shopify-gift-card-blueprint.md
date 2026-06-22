# Shopify ICTECH Gift Card Blueprint

This document maps the Shopware 6 `ICTECHGiftCard` plugin into a Shopify app built with Laravel, Shopify Polaris, and React.

## 1) Feature Parity Goal

The Shopify app should preserve every meaningful capability from the Shopware plugin:

- Sell gift cards as products
- Support branded templates and media
- Capture recipient, sender, message, and send date
- Generate unique voucher codes
- Email the gift card immediately or on a scheduled date
- Allow partial/full balance redemption
- Track transactions and audit history
- Provide admin dashboards and CSV exports
- Provide PDF preview/configuration
- Expose a customer-facing gift card account page

## 2) What The Shopware Plugin Actually Does

### Product creation and sync

- A gift card record automatically creates a product on insert.
- Product data stays synced when the gift card changes.
- The product is configured as a shippable gift card item with price, stock, visibility, image, and custom fields.
- The plugin also stores a `product_id` link back to the gift card.

### Voucher pool

- Every gift card creates a pool of 25 pre-generated voucher codes.
- Those codes start in a “waiting valid order” state.
- One code is assigned when a customer actually buys the gift card.

### Purchase flow

- Checkout order placement detects gift-card products.
- The plugin reads recipient/sender/message/date from cart payload.
- It attaches one unused pooled voucher to the order.
- It sets the expiry based on validity days.
- If the send date is today or earlier, it sends the email immediately.

### Redemption flow

- Customers can enter gift-card codes during checkout.
- The plugin validates the code, expiry, status, and remaining balance.
- It applies the voucher as a negative cart line item.
- After order placement, it records a transaction and reduces remaining balance.

### Scheduled email sending

- A scheduled task finds vouchers due for delivery.
- It sends emails for pending vouchers with a recipient email.

### Admin tools

- Dashboard stats
- Purchased voucher export
- Used voucher export
- PDF preview
- Settings screen
- Gift card list/detail pages
- Template list/detail pages
- Order list page

### Storefront tools

- Customer account page listing purchased gift cards
- Storefront labels and display customization

## 3) Shopify Feature Mapping

### Shopware concept → Shopify concept

- Gift card entity → Laravel `gift_cards` table + Shopify product/metafield link
- Voucher entity → Laravel `gift_card_vouchers` table
- Transaction entity → Laravel `gift_card_transactions` table
- Audit log entity → Laravel `gift_card_audit_logs` table
- Template entity → Laravel `gift_card_templates` table
- Shopware admin module → Shopify embedded admin app with Polaris + React
- Storefront Twig page → Shopify theme app extension / app block
- Scheduled task → Laravel queue job + scheduler
- Shopware event subscribers → Shopify webhooks

## 4) Recommended Shopify App Architecture

### Backend

- Laravel API app
- MySQL/PostgreSQL database
- Redis queue for scheduled emails and webhook processing
- Shopify API client for products, metafields, orders, customers, and webhooks
- Auth via Shopify session storage

### Frontend

- Shopify Polaris for admin UI
- React for app screens and forms
- Embedded app inside Shopify Admin
- Theme app extension for storefront/gift-card product configuration UI

### Core integrations

- Shopify Admin API for product creation and metafields
- Shopify Webhooks for order/create, order/paid, order/cancelled, refunds, app/uninstalled, and product/update
- Shopify Customer API or Storefront API where needed for customer-facing views

## 5) Laravel Data Model

### `gift_cards`

Stores the sellable gift card configuration.

Suggested fields:

- `id`
- `shop_id`
- `shopify_product_id`
- `shopify_product_variant_id`
- `name`
- `amount`
- `code_prefix`
- `validity_days`
- `quantity`
- `quantity_issued`
- `active`
- `template_id`
- `image_url` or `media_id`
- `metafields` / JSON payload
- timestamps

### `gift_card_templates`

Stores visual templates for emails/PDFs.

Suggested fields:

- `id`
- `shop_id`
- `name`
- `tag`
- `media_id`
- `active`
- `body_html`
- `custom_fields`
- timestamps

### `gift_card_vouchers`

Represents each issued code.

Suggested fields:

- `id`
- `gift_card_id`
- `shopify_order_id`
- `shopify_order_line_item_id`
- `shopify_customer_id`
- `code`
- `original_amount`
- `remaining_balance`
- `currency`
- `sender_name`
- `recipient_name`
- `recipient_email`
- `personal_message`
- `scheduled_send_date`
- `sent_at`
- `expires_at`
- `status`
- `used_in_order_number`
- `metadata`
- timestamps

### `gift_card_transactions`

Stores each redemption.

Suggested fields:

- `id`
- `voucher_id`
- `shopify_order_id`
- `shopify_customer_id`
- `amount_used`
- `balance_before`
- `balance_after`
- timestamps

### `gift_card_audit_logs`

Tracks admin actions.

Suggested fields:

- `id`
- `voucher_id`
- `admin_user_id`
- `action`
- `old_value`
- `new_value`
- `reason`
- timestamps

### `gift_card_settings`

Store app configuration per shop.

Suggested fields:

- storefront copy
- email subjects
- PDF template HTML
- image sizes
- prefix values
- send mode settings
- default timezone

## 6) Status Model

Use a clean Shopify-native status model, but preserve the Shopware lifecycle semantics.

Recommended voucher statuses:

- `pending_issuance` — voucher code exists in pool but is not attached to a sold order
- `reserved` — optional transitional state if you want lock-on-purchase
- `active` — assigned to a valid gift-card purchase and usable after delivery rules pass
- `scheduled` — assigned and waiting for future send date
- `sent` — email has been sent
- `partially_used` — balance is greater than zero after redemption
- `used` — balance is fully consumed
- `expired` — date passed and it is no longer usable
- `revoked` — manually disabled by admin

If you want strict parity, keep the Shopware meanings but normalize the labels in UI.

## 7) Webhook-Driven Business Flow

### Order paid / order created

When an order includes a gift card product:

- Identify the gift-card line item
- Read recipient/sender/message/send date from line item properties or cart attributes
- Allocate one voucher from the pool
- Mark voucher as attached to the order
- Calculate expiry using validity days
- Send immediately if the scheduled date is today or earlier
- Otherwise queue a scheduled send job

### Order cancelled / refunded

- If the gift-card purchase is cancelled before delivery, mark voucher revoked or return it to pool
- If a gift card is refunded after being sent, revoke or flag it according to store policy
- If a redeemed order is refunded, restore balance only if policy allows it

### Product updated

- If the underlying Shopify product changes, sync price/title/image/metafields back into the app record

### App uninstalled

- Disable future jobs and webhooks
- Retain or anonymize data according to your retention policy

## 8) Gift Card Purchase Flow

### Admin setup

1. Merchant creates a gift card in the app.
2. App creates/updates a Shopify product with the correct price, image, and visibility.
3. App generates a voucher pool of 25 codes.
4. The product is published to the chosen sales channel / online store.

### Storefront buyer flow

1. Customer selects a gift card product.
2. Customer enters recipient name, recipient email, sender name, message, and send date.
3. Data is stored on the cart line item or cart attributes.
4. Customer checks out.
5. Webhook issues a voucher, saves the order link, and sends or schedules email.

## 9) Redemption Flow

Shopify does not have the same native negative-line-item cart processor that Shopware has, so we should implement this carefully.

Recommended approach:

- Provide a gift-card redemption form in a cart drawer/app block or a dedicated account page.
- Validate code server-side against the app database.
- Apply redemption using the best Shopify-supported mechanism for your plan:
  - discount code / gift card code if compatible with your business rules
  - app-managed balance tracking if you are issuing product-based gift cards
  - checkout extensions or post-purchase logic where available

Important:

- Store the full redemption ledger in Laravel regardless of Shopify surface used.
- Never rely on client-side validation for balance checks.

## 10) Admin Screens In Polaris

### Dashboard

- Total vouchers
- Total sold amount
- Total redeemed amount
- Expired count
- Pending count
- Status breakdown
- CSV export actions

### Gift Cards

- List
- Create/edit
- Product link
- Template selection
- Voucher pool status
- Activation toggle

### Vouchers

- Search by code, email, order, status
- View details
- Manually resend email
- Revoke
- Balance adjustment
- Audit history

### Templates

- Create/edit HTML template
- Assign media
- Activate/deactivate
- Preview output

### Settings

- Storefront labels
- Email subjects
- PDF content template
- Card dimensions
- Prefixes
- Send mode

### Reports

- Purchased vouchers export
- Used vouchers export
- Date filters

## 11) Storefront / Theme Extension

We should mirror the Shopware storefront experience with a Shopify theme app extension:

- Gift card details block on the product page
- Recipient name field
- Recipient email field
- Sender name field
- Personal message field
- Send date picker
- Optional template selector
- Inline validation
- Translatable labels
- Customizable copy from app settings

Also add a customer account section:

- Purchased gift cards
- Remaining balance
- Expiry date
- Status
- Download PDF if enabled

## 12) Email And PDF

### Email

- Use queued mail jobs.
- Build one template for purchaser and one for recipient if needed.
- Inject voucher code, amount, sender, recipient, message, and validity date.
- Mark `sent_at` only after successful send.

### PDF

- Store the PDF HTML template in settings.
- Render sample values in preview mode.
- Allow image rendering from local or remote source.
- Keep the output deterministic for merchant previewing.

## 13) Missing Or Risky Areas To Decide Early

These are the only places where Shopify differs materially from Shopware:

- Native checkout balance redemption is not the same as Shopware line-item deduction.
- Auto-creating a Shopify product for every gift card may need guardrails around product publishing and variants.
- Scheduled gift-card email delivery should be queue-backed and timezone-aware.
- If you need refunds/reversals, the policy must be defined before implementation.

## 14) Suggested Build Order

1. Database schema and models
2. Shopify auth + app shell
3. Gift card product sync
4. Voucher issuance on order webhook
5. Scheduled send job
6. Admin dashboard and CRUD screens
7. Theme app extension for product page fields
8. Redemption flow
9. CSV exports and audit logs
10. PDF preview and customization

## 15) Next Deliverables I Can Build

I can now help with any of these:

- Laravel migration set
- Eloquent models and relationships
- Shopify webhook handlers
- Polaris UI route map
- API endpoint contract
- Theme app extension schema
- Queue/job design

