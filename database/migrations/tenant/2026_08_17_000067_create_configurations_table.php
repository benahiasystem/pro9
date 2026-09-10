<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `configurations` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `formats` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default'
 * - `cron` tinyint(1) NOT NULL DEFAULT '1'
 * - `stock` tinyint(1) NOT NULL DEFAULT '1'
 * - `limit_documents` bigint(20) NOT NULL DEFAULT '0'
 * - `limit_users` bigint(20) NOT NULL DEFAULT '10'
 * - `locked_emission` tinyint(1) NOT NULL DEFAULT '0'
 * - `restrict_sales_limit` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'habilitar restricción de límite de ventas mensual'
 * - `locked_create_establishments` tinyint(1) NOT NULL DEFAULT '0'
 * - `permission_to_edit_cpe` tinyint(1) NOT NULL DEFAULT '0'
 * - `customer_filter_by_seller` tinyint(1) NOT NULL DEFAULT '0'
 * - `set_address_by_establishment` tinyint(1) NOT NULL DEFAULT '0'
 * - `plan` json DEFAULT NULL
 * - `enable_whatsapp` tinyint(1) NOT NULL DEFAULT '1'
 * - `phone_whatsapp` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `apk_url` text COLLATE utf8mb4_unicode_ci
 * - `visual` json DEFAULT NULL
 * - `sidebar_mode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'light' COMMENT 'Modo del sidebar: light o dark'
 * - `product_default_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `decimal_quantity` tinyint(4) NOT NULL DEFAULT '2'
 * - `locked_users` tinyint(1) NOT NULL DEFAULT '0'
 * - `date_time_start` datetime DEFAULT NULL
 * - `quantity_documents` int(11) NOT NULL
 * - `quantity_sales_notes` int(11) NOT NULL
 * - `locked_tenant` tinyint(1) NOT NULL DEFAULT '0'
 * - `compact_sidebar` tinyint(1) NOT NULL DEFAULT '0'
 * - `colums_grid_item` tinyint(4) DEFAULT '4'
 * - `options_pos` tinyint(1) NOT NULL DEFAULT '1'
 * - `edit_name_product` tinyint(1) NOT NULL DEFAULT '0'
 * - `restrict_receipt_date` tinyint(1) NOT NULL DEFAULT '1'
 * - `shipping_time_days` int(11) NOT NULL DEFAULT '4'
 * - `affectation_igv_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '10'
 * - `global_discount_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '02'
 * - `include_igv` tinyint(1) DEFAULT NULL
 * - `global_igv_handling` tinyint(1) NOT NULL DEFAULT '1'
 * - `percentage_allowance_charge` decimal(12,2) DEFAULT '0.00'
 * - `igv_retention_percentage` decimal(8,5) NOT NULL DEFAULT '3.00000'
 * - `active_allowance_charge` tinyint(1) DEFAULT '0'
 * - `active_warehouse_prices` tinyint(1) DEFAULT '0'
 * - `product_only_location` tinyint(1) DEFAULT NULL
 * - `terms_condition` text COLLATE utf8mb4_unicode_ci
 * - `terms_condition_sale` text COLLATE utf8mb4_unicode_ci
 * - `legend_footer_sale` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `cotizaction_finance` tinyint(1) NOT NULL DEFAULT '1'
 * - `quotation_allow_seller_generate_sale` tinyint(1) NOT NULL DEFAULT '0'
 * - `allow_edit_unit_price_to_seller` tinyint(1) NOT NULL DEFAULT '0'
 * - `header_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `destination_sale` tinyint(1) NOT NULL DEFAULT '1'
 * - `default_document_type_80` tinyint(1) NOT NULL DEFAULT '0'
 * - `search_item_by_barcode` tinyint(1) NOT NULL DEFAULT '0'
 * - `login` text COLLATE utf8mb4_unicode_ci
 * - `navbar` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed'
 * - `finances` text COLLATE utf8mb4_unicode_ci
 * - `smtp_encryption` text COLLATE utf8mb4_unicode_ci COMMENT 'Tipo de cifrado de correo'
 * - `smtp_password` text COLLATE utf8mb4_unicode_ci COMMENT 'contraseña de usuario para el envio de correo'
 * - `smtp_user` text COLLATE utf8mb4_unicode_ci COMMENT 'Nombre de usuario para el envio de correo'
 * - `smtp_port` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Puerto de correo del cliente'
 * - `smtp_host` text COLLATE utf8mb4_unicode_ci COMMENT 'Host de correo del cliente'
 * - `ticket_58` tinyint(1) NOT NULL DEFAULT '0'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `url_apiruc` text COLLATE utf8mb4_unicode_ci
 * - `token_apiruc` text COLLATE utf8mb4_unicode_ci
 * - `seller_can_create_product` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Define si los vendedores pueden crear productos'
 * - `seller_can_view_balance` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Define si los vendedores pueden ver el balance en finanzas'
 * - `seller_can_generate_sale_opportunities` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Define si los vendedores pueden crear productos'
 * - `update_document_on_dispaches` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si esta activo, al momento de crear una guia, se actualiza el pdf de documentos.'
 * - `is_pharmacy` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Establece si se activa el modulo de farmacia'
 * - `search_item_by_series` tinyint(1) NOT NULL DEFAULT '0'
 * - `mi_tienda_pe` tinyint(1) NOT NULL DEFAULT '0'
 * - `group_items_generate_document` tinyint(1) NOT NULL DEFAULT '1'
 * - `change_free_affectation_igv` tinyint(1) NOT NULL DEFAULT '0'
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'VES' COMMENT 'Id de cat_currency_types_id'
 * - `select_available_price_list` tinyint(1) NOT NULL DEFAULT '0'
 * - `show_extra_info_to_item` tinyint(3) unsigned DEFAULT '0' COMMENT 'Habilita datos extra para item'
 * - `enabled_global_igv_to_purchase` tinyint(3) unsigned DEFAULT '0' COMMENT 'Habilita el igv global en la compra. Sobreescribe has_igv del item'
 * - `show_pdf_name` tinyint(3) unsigned DEFAULT '0' COMMENT 'Muestra el nombre de pdf en vez del producto'
 * - `dispatches_address_text` tinyint(3) unsigned DEFAULT '0' COMMENT 'En guias, habilita colocar la direccion de destino como texto'
 * - `show_items_only_user_stablishment` int(10) unsigned DEFAULT '1' COMMENT 'permite mostrar stock del alamcen de usuario'
 * - `item_name_pdf_description` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Si esta activado, el nombre de pdf será por defecto la descripcion del item'
 * - `auto_print` tinyint(1) NOT NULL DEFAULT '0'
 * - `printer_name_documents` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `show_service_on_pos` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Permite listar al inicio, los servicios en pos'
 * - `pos_cost_price` tinyint(1) NOT NULL DEFAULT '1'
 * - `pos_history` tinyint(1) NOT NULL DEFAULT '1'
 * - `show_totals_on_cpe_list` tinyint(1) NOT NULL DEFAULT '0'
 * - `show_terms_condition_pos` tinyint(1) NOT NULL DEFAULT '0'
 * - `show_ticket_80` tinyint(1) NOT NULL DEFAULT '1'
 * - `show_ticket_58` tinyint(1) NOT NULL DEFAULT '0'
 * - `show_ticket_50` tinyint(1) NOT NULL DEFAULT '0'
 * - `show_complete_name_pos` tinyint(1) NOT NULL DEFAULT '0'
 * - `show_last_price_sale` tinyint(1) NOT NULL DEFAULT '0'
 * - `show_logo_by_establishment` tinyint(1) NOT NULL DEFAULT '0'
 * - `print_new_line_to_observation` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Añade la posiblidad de colocar salto de linea en observación de pdf'
 * - `new_validator_pagination` mediumint(8) unsigned DEFAULT '0'
 * - `validate_purchase_sale_unit_price` tinyint(1) NOT NULL DEFAULT '0'
 * - `checked_global_igv_to_purchase` tinyint(1) NOT NULL DEFAULT '0'
 * - `checked_update_purchase_price` tinyint(1) NOT NULL DEFAULT '0'
 * - `set_global_purchase_currency_items` tinyint(1) NOT NULL DEFAULT '0'
 * - `set_unit_price_dispatch_related_record` tinyint(1) NOT NULL DEFAULT '0'
 * - `restrict_voided_send` tinyint(1) NOT NULL DEFAULT '1'
 * - `shipping_time_days_voided` int(11) NOT NULL DEFAULT '7'
 * - `enabled_tips_pos` tinyint(1) NOT NULL DEFAULT '0'
 * - `top_menu_a_id` int(10) unsigned DEFAULT NULL
 * - `top_menu_b_id` int(10) unsigned DEFAULT NULL
 * - `top_menu_c_id` int(10) unsigned DEFAULT NULL
 * - `top_menu_d_id` int(10) unsigned DEFAULT NULL
 * - `top_menu_extra_one` json DEFAULT NULL
 * - `top_menu_extra_two` json DEFAULT NULL
 * - `skin_id` int(10) unsigned NOT NULL DEFAULT '1'
 * - `change_currency_item` tinyint(1) NOT NULL DEFAULT '0'
 * - `enabled_advanced_records_search` tinyint(1) NOT NULL DEFAULT '0'
 * - `change_decimal_quantity_unit_price_pdf` tinyint(1) NOT NULL DEFAULT '0'
 * - `decimal_quantity_unit_price_pdf` int(11) NOT NULL DEFAULT '2'
 * - `separate_cash_transactions` tinyint(1) NOT NULL DEFAULT '0'
 * - `order_cash_income` tinyint(1) NOT NULL DEFAULT '0'
 * - `generate_order_note_from_quotation` tinyint(1) NOT NULL DEFAULT '0'
 * - `list_items_by_warehouse` tinyint(1) NOT NULL DEFAULT '0'
 * - `hide_pdf_view_documents` tinyint(1) NOT NULL DEFAULT '0'
 * - `affect_all_documents` tinyint(1) NOT NULL DEFAULT '0'
 * - `dashboard_sales` tinyint(1) NOT NULL DEFAULT '1'
 * - `dashboard_general` tinyint(1) NOT NULL DEFAULT '1'
 * - `dashboard_clients` tinyint(1) NOT NULL DEFAULT '1'
 * - `dashboard_products` tinyint(1) NOT NULL DEFAULT '0'
 * - `dashboard_goal_enabled` tinyint(1) NOT NULL DEFAULT '0'
 * - `dashboard_goal_amount` decimal(15,2) NOT NULL DEFAULT '0.00'
 * - `restrict_series_selection_seller` tinyint(1) NOT NULL DEFAULT '0'
 * - `regex_password_user` tinyint(1) NOT NULL DEFAULT '0'
 * - `enabled_remember_change_password` tinyint(1) NOT NULL DEFAULT '0'
 * - `quantity_month_remember_change_password` int(11) NOT NULL DEFAULT '1'
 * - `enabled_point_system` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'sistema por puntos'
 * - `point_system_sale_amount` decimal(12,2) NOT NULL DEFAULT '1.00' COMMENT 'sistema por puntos'
 * - `quantity_of_points` decimal(12,2) NOT NULL DEFAULT '1.00' COMMENT 'sistema por puntos'
 * - `round_points_of_sale` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'sistema por puntos'
 * - `enable_categories_products_view` tinyint(1) NOT NULL DEFAULT '0'
 * - `restrict_seller_discount` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'limitar descuento a los vendedores'
 * - `sellers_discount_limit` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'limitar descuento a los vendedores'
 * - `enabled_sales_agents` tinyint(1) NOT NULL DEFAULT '0'
 * - `change_affectation_exonerated_igv` tinyint(1) NOT NULL DEFAULT '0'
 * - `search_factory_code_items` tinyint(1) NOT NULL DEFAULT '0'
 * - `show_load_voucher` tinyint(1) NOT NULL DEFAULT '0'
 * - `register_series_invoice_xml` tinyint(1) NOT NULL DEFAULT '0'
 * - `enable_discount_by_customer` tinyint(1) NOT NULL DEFAULT '0'
 * - `enabled_dispatch_ticket_pdf` tinyint(1) NOT NULL DEFAULT '0'
 * - `enabled_dispatch_ticket_pdf_individual` tinyint(1) NOT NULL DEFAULT '0'
 * - `show_price_barcode_ticket` tinyint(1) DEFAULT NULL
 * - `price_selected_add_product` tinyint(1) NOT NULL DEFAULT '0'
 * - `pdf_footer_images` json DEFAULT NULL
 * - `restrict_sale_items_cpe` tinyint(1) NOT NULL DEFAULT '0'
 * - `show_convert_cpe_pos` tinyint(1) NOT NULL DEFAULT '0'
 * - `order_node_advanced` tinyint(1) NOT NULL DEFAULT '0'
 * - `remove_validation_email_establishments` tinyint(1) NOT NULL DEFAULT '0'
 * - `select_establishment_bank_account` tinyint(1) NOT NULL DEFAULT '0'
 * - `change_values_preview_document` tinyint(1) NOT NULL DEFAULT '0'
 * - `session_lifetime` decimal(4,2) NOT NULL DEFAULT '2.00'
 * - `search_items_main_form` tinyint(1) NOT NULL DEFAULT '0'
 * - `show_all_item_details` tinyint(1) NOT NULL DEFAULT '0'
 * - `add_description_to_document_item` tinyint(1) NOT NULL DEFAULT '0'
 * - `show_item_description_pack` tinyint(1) NOT NULL DEFAULT '0'
 * - `show_weighted_cost_purchase` tinyint(1) NOT NULL DEFAULT '0'
 * - `show_unify_amount_items` tinyint(1) NOT NULL DEFAULT '0'
 * - `condition_sale_purchase_price_to_item` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'precio de venta no debe ser menor a costo'
 * - `price1_label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Precio 1'
 * - `price2_label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Precio 2'
 * - `price3_label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Precio 3'
 * - `qrchat_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'datos de acceso para envio gratuito mediante whatsapp'
 * - `qrchat_app_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'datos de acceso para envio gratuito mediante whatsapp'
 * - `qrchat_auth_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'datos de acceso para envio gratuito mediante whatsapp'
 * - `qrchat_enable` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'falso mostrara boton de wsapp web'
 * - `enable_list_product` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'habilitar listado de precios de productos'
 * - `is_migrated_address` tinyint(1) NOT NULL DEFAULT '0'
 * - `qr_api_url` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `qr_api_apiKey` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `qr_api_enable` tinyint(1) NOT NULL DEFAULT '0'
 * - `show_seller_in_pdf` tinyint(1) NOT NULL DEFAULT '1'
 * - `show_bank_accounts_in_pdf` tinyint(1) NOT NULL DEFAULT '1'
 * - `enabled_price_items_dispatch` tinyint(1) NOT NULL DEFAULT '0'
 * - `exact_discount` tinyint(1) NOT NULL DEFAULT '1'
 * - `enabled_guarantee_fund` tinyint(1) NOT NULL DEFAULT '0'
 * - `from_guest_register` tinyint(1) NOT NULL DEFAULT '0'
 * - `was_verified_guest_user` tinyint(1) NOT NULL DEFAULT '0'
 * - `available_cash_report_seller` tinyint(1) NOT NULL DEFAULT '0'
 * - `restaurant_tip_factor` tinyint(4) NOT NULL DEFAULT '0'
 * - `enable_consigned` tinyint(1) NOT NULL DEFAULT '0'
 * - `enable_weight_in_dispatches` tinyint(1) NOT NULL DEFAULT '0'
 * - `public_search_bg_color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `public_search_bg_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `auto_send_pdf_email` tinyint(1) NOT NULL DEFAULT '0'
 * - `enable_help_center` tinyint(1) NOT NULL DEFAULT '1'
 * - `enable_interactive_tours` tinyint(1) NOT NULL DEFAULT '1'
 * - `enable_guided_tours` tinyint(1) NOT NULL DEFAULT '0'
 * - `before_day_creation_suscription_order` int(11) NOT NULL DEFAULT '1'
 * - `message_notify_to_suscription_orders` text COLLATE utf8mb4_unicode_ci
 * - `send_link_subscription` tinyint(1) NOT NULL DEFAULT '0'
 * - `count_send_link_subscription` int(11) NOT NULL DEFAULT '0'
 * - `show_item_discounts_charges_attributes` tinyint(1) NOT NULL DEFAULT '0'
 * - `disable_users` tinyint(1) NOT NULL DEFAULT '0'
 * - `enable_bank_accounts` tinyint(1) NOT NULL DEFAULT '0'
 * - `item_name_autocomplete` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'autocompleta nombre y codigo de barra con atributos'
 * - `search_by_click` tinyint(1) NOT NULL DEFAULT '0'
 * - `disable_retention_for_amount` tinyint(1) NOT NULL DEFAULT '0'
 * - `evolution_instance` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `evolution_instance_adopted` tinyint(1) NOT NULL DEFAULT '0'
 * - `evolution_use_qr_api_instance` tinyint(1) NOT NULL DEFAULT '0'
 * - `evolution_connected_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `evolution_profile_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `evolution_instance_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `evolution_connection_state` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disconnected'
 * - `evolution_connected_at` timestamp NULL DEFAULT NULL
 * - `evolution_enabled` tinyint(1) NOT NULL DEFAULT '0'
 * - `evolution_owner_user_id` int(10) unsigned DEFAULT NULL
 * - `whatsapp_bot_enabled` tinyint(1) NOT NULL DEFAULT '1'
 * - `evolution_webhook_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `bot_trigger_command` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '/bot'
 * - `bot_exit_command` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '/fin'
 * - `bot_pause_command` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '/p'
 * - `bot_session_ttl_minutes` int(10) unsigned NOT NULL DEFAULT '30'
 * - `qr_api_instance` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `qr_api_instance_adopted` tinyint(1) NOT NULL DEFAULT '0'
 * - `qr_api_connected_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `qr_api_profile_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `qr_api_instance_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `qr_api_connection_state` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disconnected'
 * - `qr_api_connected_at` timestamp NULL DEFAULT NULL
 * - `qr_api_webhook_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `qr_api_use_bot_instance` tinyint(1) NOT NULL DEFAULT '0'
 * - `date_format` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DD-MM-YYYY' COMMENT 'Plantilla de formato de fecha para mostrar en listados y formularios. Ej: DD-MM-YYYY=27-05-2026, DD/MM/YYYY=27/05/2026, YYYY-MM-DD=2026-05-27. Se guarda con la sintaxis de moment.js.'
 * - `time_format` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'HH:mm:ss' COMMENT 'Plantilla de formato de hora para mostrar en listados y formularios. Ej: HH:mm=14:30, HH:mm:ss=14:30:45, hh:mm A=02:30 PM. Se guarda con la sintaxis de moment.js.'
 * - `date_of_due_test_days` date DEFAULT NULL
 * - `enable_dedicated_series` tinyint(1) NOT NULL DEFAULT '0'
 * - `qr_api_pdf_format` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ticket'
 * - `enable_global_discount` tinyint(1) NOT NULL DEFAULT '0'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `configurations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `formats` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `cron` tinyint(1) NOT NULL DEFAULT '1',
  `stock` tinyint(1) NOT NULL DEFAULT '1',
  `limit_documents` bigint(20) NOT NULL DEFAULT '0',
  `limit_users` bigint(20) NOT NULL DEFAULT '10',
  `locked_emission` tinyint(1) NOT NULL DEFAULT '0',
  `restrict_sales_limit` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'habilitar restricción de límite de ventas mensual',
  `locked_create_establishments` tinyint(1) NOT NULL DEFAULT '0',
  `permission_to_edit_cpe` tinyint(1) NOT NULL DEFAULT '0',
  `customer_filter_by_seller` tinyint(1) NOT NULL DEFAULT '0',
  `set_address_by_establishment` tinyint(1) NOT NULL DEFAULT '0',
  `plan` json DEFAULT NULL,
  `enable_whatsapp` tinyint(1) NOT NULL DEFAULT '1',
  `phone_whatsapp` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apk_url` text COLLATE utf8mb4_unicode_ci,
  `visual` json DEFAULT NULL,
  `sidebar_mode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'light' COMMENT 'Modo del sidebar: light o dark',
  `product_default_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decimal_quantity` tinyint(4) NOT NULL DEFAULT '2',
  `locked_users` tinyint(1) NOT NULL DEFAULT '0',
  `date_time_start` datetime DEFAULT NULL,
  `quantity_documents` int(11) NOT NULL,
  `quantity_sales_notes` int(11) NOT NULL,
  `locked_tenant` tinyint(1) NOT NULL DEFAULT '0',
  `compact_sidebar` tinyint(1) NOT NULL DEFAULT '0',
  `colums_grid_item` tinyint(4) DEFAULT '4',
  `options_pos` tinyint(1) NOT NULL DEFAULT '1',
  `edit_name_product` tinyint(1) NOT NULL DEFAULT '0',
  `restrict_receipt_date` tinyint(1) NOT NULL DEFAULT '1',
  `shipping_time_days` int(11) NOT NULL DEFAULT '4',
  `affectation_igv_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '10',
  `global_discount_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '02',
  `include_igv` tinyint(1) DEFAULT NULL,
  `global_igv_handling` tinyint(1) NOT NULL DEFAULT '1',
  `percentage_allowance_charge` decimal(12,2) DEFAULT '0.00',
  `igv_retention_percentage` decimal(8,5) NOT NULL DEFAULT '3.00000',
  `active_allowance_charge` tinyint(1) DEFAULT '0',
  `active_warehouse_prices` tinyint(1) DEFAULT '0',
  `product_only_location` tinyint(1) DEFAULT NULL,
  `terms_condition` text COLLATE utf8mb4_unicode_ci,
  `terms_condition_sale` text COLLATE utf8mb4_unicode_ci,
  `legend_footer_sale` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cotizaction_finance` tinyint(1) NOT NULL DEFAULT '1',
  `quotation_allow_seller_generate_sale` tinyint(1) NOT NULL DEFAULT '0',
  `allow_edit_unit_price_to_seller` tinyint(1) NOT NULL DEFAULT '0',
  `header_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_sale` tinyint(1) NOT NULL DEFAULT '1',
  `default_document_type_80` tinyint(1) NOT NULL DEFAULT '0',
  `search_item_by_barcode` tinyint(1) NOT NULL DEFAULT '0',
  `login` text COLLATE utf8mb4_unicode_ci,
  `navbar` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed',
  `finances` text COLLATE utf8mb4_unicode_ci,
  `smtp_encryption` text COLLATE utf8mb4_unicode_ci COMMENT 'Tipo de cifrado de correo',
  `smtp_password` text COLLATE utf8mb4_unicode_ci COMMENT 'contraseña de usuario para el envio de correo',
  `smtp_user` text COLLATE utf8mb4_unicode_ci COMMENT 'Nombre de usuario para el envio de correo',
  `smtp_port` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Puerto de correo del cliente',
  `smtp_host` text COLLATE utf8mb4_unicode_ci COMMENT 'Host de correo del cliente',
  `ticket_58` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `url_apiruc` text COLLATE utf8mb4_unicode_ci,
  `token_apiruc` text COLLATE utf8mb4_unicode_ci,
  `seller_can_create_product` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Define si los vendedores pueden crear productos',
  `seller_can_view_balance` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Define si los vendedores pueden ver el balance en finanzas',
  `seller_can_generate_sale_opportunities` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Define si los vendedores pueden crear productos',
  `update_document_on_dispaches` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si esta activo, al momento de crear una guia, se actualiza el pdf de documentos.',
  `is_pharmacy` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Establece si se activa el modulo de farmacia',
  `search_item_by_series` tinyint(1) NOT NULL DEFAULT '0',
  `mi_tienda_pe` tinyint(1) NOT NULL DEFAULT '0',
  `group_items_generate_document` tinyint(1) NOT NULL DEFAULT '1',
  `change_free_affectation_igv` tinyint(1) NOT NULL DEFAULT '0',
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'VES' COMMENT 'Id de cat_currency_types_id',
  `select_available_price_list` tinyint(1) NOT NULL DEFAULT '0',
  `show_extra_info_to_item` tinyint(3) unsigned DEFAULT '0' COMMENT 'Habilita datos extra para item',
  `enabled_global_igv_to_purchase` tinyint(3) unsigned DEFAULT '0' COMMENT 'Habilita el igv global en la compra. Sobreescribe has_igv del item',
  `show_pdf_name` tinyint(3) unsigned DEFAULT '0' COMMENT 'Muestra el nombre de pdf en vez del producto',
  `dispatches_address_text` tinyint(3) unsigned DEFAULT '0' COMMENT 'En guias, habilita colocar la direccion de destino como texto',
  `show_items_only_user_stablishment` int(10) unsigned DEFAULT '1' COMMENT 'permite mostrar stock del alamcen de usuario',
  `item_name_pdf_description` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Si esta activado, el nombre de pdf será por defecto la descripcion del item',
  `auto_print` tinyint(1) NOT NULL DEFAULT '0',
  `printer_name_documents` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `show_service_on_pos` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Permite listar al inicio, los servicios en pos',
  `pos_cost_price` tinyint(1) NOT NULL DEFAULT '1',
  `pos_history` tinyint(1) NOT NULL DEFAULT '1',
  `show_totals_on_cpe_list` tinyint(1) NOT NULL DEFAULT '0',
  `show_terms_condition_pos` tinyint(1) NOT NULL DEFAULT '0',
  `show_ticket_80` tinyint(1) NOT NULL DEFAULT '1',
  `show_ticket_58` tinyint(1) NOT NULL DEFAULT '0',
  `show_ticket_50` tinyint(1) NOT NULL DEFAULT '0',
  `show_complete_name_pos` tinyint(1) NOT NULL DEFAULT '0',
  `show_last_price_sale` tinyint(1) NOT NULL DEFAULT '0',
  `show_logo_by_establishment` tinyint(1) NOT NULL DEFAULT '0',
  `print_new_line_to_observation` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Añade la posiblidad de colocar salto de linea en observación de pdf',
  `new_validator_pagination` mediumint(8) unsigned DEFAULT '0',
  `validate_purchase_sale_unit_price` tinyint(1) NOT NULL DEFAULT '0',
  `checked_global_igv_to_purchase` tinyint(1) NOT NULL DEFAULT '0',
  `checked_update_purchase_price` tinyint(1) NOT NULL DEFAULT '0',
  `set_global_purchase_currency_items` tinyint(1) NOT NULL DEFAULT '0',
  `set_unit_price_dispatch_related_record` tinyint(1) NOT NULL DEFAULT '0',
  `restrict_voided_send` tinyint(1) NOT NULL DEFAULT '1',
  `shipping_time_days_voided` int(11) NOT NULL DEFAULT '7',
  `enabled_tips_pos` tinyint(1) NOT NULL DEFAULT '0',
  `top_menu_a_id` int(10) unsigned DEFAULT NULL,
  `top_menu_b_id` int(10) unsigned DEFAULT NULL,
  `top_menu_c_id` int(10) unsigned DEFAULT NULL,
  `top_menu_d_id` int(10) unsigned DEFAULT NULL,
  `top_menu_extra_one` json DEFAULT NULL,
  `top_menu_extra_two` json DEFAULT NULL,
  `skin_id` int(10) unsigned NOT NULL DEFAULT '1',
  `change_currency_item` tinyint(1) NOT NULL DEFAULT '0',
  `enabled_advanced_records_search` tinyint(1) NOT NULL DEFAULT '0',
  `change_decimal_quantity_unit_price_pdf` tinyint(1) NOT NULL DEFAULT '0',
  `decimal_quantity_unit_price_pdf` int(11) NOT NULL DEFAULT '2',
  `separate_cash_transactions` tinyint(1) NOT NULL DEFAULT '0',
  `order_cash_income` tinyint(1) NOT NULL DEFAULT '0',
  `generate_order_note_from_quotation` tinyint(1) NOT NULL DEFAULT '0',
  `list_items_by_warehouse` tinyint(1) NOT NULL DEFAULT '0',
  `hide_pdf_view_documents` tinyint(1) NOT NULL DEFAULT '0',
  `affect_all_documents` tinyint(1) NOT NULL DEFAULT '0',
  `dashboard_sales` tinyint(1) NOT NULL DEFAULT '1',
  `dashboard_general` tinyint(1) NOT NULL DEFAULT '1',
  `dashboard_clients` tinyint(1) NOT NULL DEFAULT '1',
  `dashboard_products` tinyint(1) NOT NULL DEFAULT '0',
  `dashboard_goal_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `dashboard_goal_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `restrict_series_selection_seller` tinyint(1) NOT NULL DEFAULT '0',
  `regex_password_user` tinyint(1) NOT NULL DEFAULT '0',
  `enabled_remember_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `quantity_month_remember_change_password` int(11) NOT NULL DEFAULT '1',
  `enabled_point_system` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'sistema por puntos',
  `point_system_sale_amount` decimal(12,2) NOT NULL DEFAULT '1.00' COMMENT 'sistema por puntos',
  `quantity_of_points` decimal(12,2) NOT NULL DEFAULT '1.00' COMMENT 'sistema por puntos',
  `round_points_of_sale` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'sistema por puntos',
  `enable_categories_products_view` tinyint(1) NOT NULL DEFAULT '0',
  `restrict_seller_discount` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'limitar descuento a los vendedores',
  `sellers_discount_limit` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'limitar descuento a los vendedores',
  `enabled_sales_agents` tinyint(1) NOT NULL DEFAULT '0',
  `change_affectation_exonerated_igv` tinyint(1) NOT NULL DEFAULT '0',
  `search_factory_code_items` tinyint(1) NOT NULL DEFAULT '0',
  `show_load_voucher` tinyint(1) NOT NULL DEFAULT '0',
  `register_series_invoice_xml` tinyint(1) NOT NULL DEFAULT '0',
  `enable_discount_by_customer` tinyint(1) NOT NULL DEFAULT '0',
  `enabled_dispatch_ticket_pdf` tinyint(1) NOT NULL DEFAULT '0',
  `enabled_dispatch_ticket_pdf_individual` tinyint(1) NOT NULL DEFAULT '0',
  `show_price_barcode_ticket` tinyint(1) DEFAULT NULL,
  `price_selected_add_product` tinyint(1) NOT NULL DEFAULT '0',
  `pdf_footer_images` json DEFAULT NULL,
  `restrict_sale_items_cpe` tinyint(1) NOT NULL DEFAULT '0',
  `show_convert_cpe_pos` tinyint(1) NOT NULL DEFAULT '0',
  `order_node_advanced` tinyint(1) NOT NULL DEFAULT '0',
  `remove_validation_email_establishments` tinyint(1) NOT NULL DEFAULT '0',
  `select_establishment_bank_account` tinyint(1) NOT NULL DEFAULT '0',
  `change_values_preview_document` tinyint(1) NOT NULL DEFAULT '0',
  `session_lifetime` decimal(4,2) NOT NULL DEFAULT '2.00',
  `search_items_main_form` tinyint(1) NOT NULL DEFAULT '0',
  `show_all_item_details` tinyint(1) NOT NULL DEFAULT '0',
  `add_description_to_document_item` tinyint(1) NOT NULL DEFAULT '0',
  `show_item_description_pack` tinyint(1) NOT NULL DEFAULT '0',
  `show_weighted_cost_purchase` tinyint(1) NOT NULL DEFAULT '0',
  `show_unify_amount_items` tinyint(1) NOT NULL DEFAULT '0',
  `condition_sale_purchase_price_to_item` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'precio de venta no debe ser menor a costo',
  `price1_label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Precio 1',
  `price2_label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Precio 2',
  `price3_label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Precio 3',
  `qrchat_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'datos de acceso para envio gratuito mediante whatsapp',
  `qrchat_app_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'datos de acceso para envio gratuito mediante whatsapp',
  `qrchat_auth_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'datos de acceso para envio gratuito mediante whatsapp',
  `qrchat_enable` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'falso mostrara boton de wsapp web',
  `enable_list_product` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'habilitar listado de precios de productos',
  `is_migrated_address` tinyint(1) NOT NULL DEFAULT '0',
  `qr_api_url` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_api_apiKey` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_api_enable` tinyint(1) NOT NULL DEFAULT '0',
  `show_seller_in_pdf` tinyint(1) NOT NULL DEFAULT '1',
  `show_bank_accounts_in_pdf` tinyint(1) NOT NULL DEFAULT '1',
  `enabled_price_items_dispatch` tinyint(1) NOT NULL DEFAULT '0',
  `exact_discount` tinyint(1) NOT NULL DEFAULT '1',
  `enabled_guarantee_fund` tinyint(1) NOT NULL DEFAULT '0',
  `from_guest_register` tinyint(1) NOT NULL DEFAULT '0',
  `was_verified_guest_user` tinyint(1) NOT NULL DEFAULT '0',
  `available_cash_report_seller` tinyint(1) NOT NULL DEFAULT '0',
  `restaurant_tip_factor` tinyint(4) NOT NULL DEFAULT '0',
  `enable_consigned` tinyint(1) NOT NULL DEFAULT '0',
  `enable_weight_in_dispatches` tinyint(1) NOT NULL DEFAULT '0',
  `public_search_bg_color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `public_search_bg_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_send_pdf_email` tinyint(1) NOT NULL DEFAULT '0',
  `enable_help_center` tinyint(1) NOT NULL DEFAULT '1',
  `enable_interactive_tours` tinyint(1) NOT NULL DEFAULT '1',
  `enable_guided_tours` tinyint(1) NOT NULL DEFAULT '0',
  `before_day_creation_suscription_order` int(11) NOT NULL DEFAULT '1',
  `message_notify_to_suscription_orders` text COLLATE utf8mb4_unicode_ci,
  `send_link_subscription` tinyint(1) NOT NULL DEFAULT '0',
  `count_send_link_subscription` int(11) NOT NULL DEFAULT '0',
  `show_item_discounts_charges_attributes` tinyint(1) NOT NULL DEFAULT '0',
  `disable_users` tinyint(1) NOT NULL DEFAULT '0',
  `enable_bank_accounts` tinyint(1) NOT NULL DEFAULT '0',
  `item_name_autocomplete` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'autocompleta nombre y codigo de barra con atributos',
  `search_by_click` tinyint(1) NOT NULL DEFAULT '0',
  `disable_retention_for_amount` tinyint(1) NOT NULL DEFAULT '0',
  `evolution_instance` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `evolution_instance_adopted` tinyint(1) NOT NULL DEFAULT '0',
  `evolution_use_qr_api_instance` tinyint(1) NOT NULL DEFAULT '0',
  `evolution_connected_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `evolution_profile_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `evolution_instance_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `evolution_connection_state` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disconnected',
  `evolution_connected_at` timestamp NULL DEFAULT NULL,
  `evolution_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `evolution_owner_user_id` int(10) unsigned DEFAULT NULL,
  `whatsapp_bot_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `evolution_webhook_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bot_trigger_command` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '/bot',
  `bot_exit_command` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '/fin',
  `bot_pause_command` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '/p',
  `bot_session_ttl_minutes` int(10) unsigned NOT NULL DEFAULT '30',
  `qr_api_instance` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_api_instance_adopted` tinyint(1) NOT NULL DEFAULT '0',
  `qr_api_connected_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_api_profile_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_api_instance_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_api_connection_state` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disconnected',
  `qr_api_connected_at` timestamp NULL DEFAULT NULL,
  `qr_api_webhook_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_api_use_bot_instance` tinyint(1) NOT NULL DEFAULT '0',
  `date_format` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DD-MM-YYYY' COMMENT 'Plantilla de formato de fecha para mostrar en listados y formularios. Ej: DD-MM-YYYY=27-05-2026, DD/MM/YYYY=27/05/2026, YYYY-MM-DD=2026-05-27. Se guarda con la sintaxis de moment.js.',
  `time_format` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'HH:mm:ss' COMMENT 'Plantilla de formato de hora para mostrar en listados y formularios. Ej: HH:mm=14:30, HH:mm:ss=14:30:45, hh:mm A=02:30 PM. Se guarda con la sintaxis de moment.js.',
  `date_of_due_test_days` date DEFAULT NULL,
  `enable_dedicated_series` tinyint(1) NOT NULL DEFAULT '0',
  `qr_api_pdf_format` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ticket',
  `enable_global_discount` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `configurations`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########
