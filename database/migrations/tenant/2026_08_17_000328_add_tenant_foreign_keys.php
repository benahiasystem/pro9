<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Agrega las claves foráneas después de crear todas las tablas.
 *
 * Este paso separado evita errores por dependencias circulares y permite que
 * el rollback retire primero todas las relaciones antes de borrar tablas.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $statements = [
            <<<'SQL'
ALTER TABLE `client_errors` ADD CONSTRAINT `client_errors_client_error_type_id_foreign` FOREIGN KEY (`client_error_type_id`) REFERENCES `client_error_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `companies` ADD CONSTRAINT `companies_identity_document_type_id_foreign` FOREIGN KEY (`identity_document_type_id`) REFERENCES `cat_identity_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `companies` ADD CONSTRAINT `companies_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `delivery_zone_locations` ADD CONSTRAINT `delivery_zone_locations_delivery_zone_id_foreign` FOREIGN KEY (`delivery_zone_id`) REFERENCES `delivery_zones` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `dispatchers` ADD CONSTRAINT `dispatchers_identity_document_type_id_foreign` FOREIGN KEY (`identity_document_type_id`) REFERENCES `cat_identity_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `drivers` ADD CONSTRAINT `drivers_identity_document_type_id_foreign` FOREIGN KEY (`identity_document_type_id`) REFERENCES `cat_identity_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `fixed_asset_items` ADD CONSTRAINT `fixed_asset_items_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `fixed_asset_items` ADD CONSTRAINT `fixed_asset_items_item_type_id_foreign` FOREIGN KEY (`item_type_id`) REFERENCES `item_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `fixed_asset_items` ADD CONSTRAINT `fixed_asset_items_purchase_affectation_igv_type_id_foreign` FOREIGN KEY (`purchase_affectation_igv_type_id`) REFERENCES `cat_affectation_igv_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `fixed_asset_items` ADD CONSTRAINT `fixed_asset_items_unit_type_id_foreign` FOREIGN KEY (`unit_type_id`) REFERENCES `cat_unit_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `inventories_transfer` ADD CONSTRAINT `inventories_transfer_document_type_id_foreign` FOREIGN KEY (`document_type_id`) REFERENCES `cat_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `inventories_transfer` ADD CONSTRAINT `inventories_transfer_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `inventories_transfer` ADD CONSTRAINT `inventories_transfer_transfer_collect_id_foreign` FOREIGN KEY (`transfer_collect_id`) REFERENCES `inventories_transfer` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `item_movement_rel_extra` ADD CONSTRAINT `item_movement_rel_extra_item_movement_id_foreign` FOREIGN KEY (`item_movement_id`) REFERENCES `item_movement` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `mill` ADD CONSTRAINT `mill_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `module_levels` ADD CONSTRAINT `module_levels_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `orders` ADD CONSTRAINT `orders_payment_status_order_id_foreign` FOREIGN KEY (`payment_status_order_id`) REFERENCES `status_orders` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `orders` ADD CONSTRAINT `orders_shipping_status_order_id_foreign` FOREIGN KEY (`shipping_status_order_id`) REFERENCES `status_orders` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `orders` ADD CONSTRAINT `orders_status_order_id_foreign` FOREIGN KEY (`status_order_id`) REFERENCES `status_orders` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `origin_addresses` ADD CONSTRAINT `origin_addresses_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `packaging` ADD CONSTRAINT `packaging_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `person_types` ADD CONSTRAINT `person_types_price_label_id_foreign` FOREIGN KEY (`price_label_id`) REFERENCES `price_labels` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `production` ADD CONSTRAINT `production_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `provinces` ADD CONSTRAINT `provinces_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `supplies` ADD CONSTRAINT `supplies_unit_type_id_foreign` FOREIGN KEY (`unit_type_id`) REFERENCES `cat_unit_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `tips` ADD CONSTRAINT `tips_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `webhook_deliveries` ADD CONSTRAINT `webhook_deliveries_webhook_subscription_id_foreign` FOREIGN KEY (`webhook_subscription_id`) REFERENCES `webhook_subscriptions` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `workers` ADD CONSTRAINT `workers_identity_document_type_id_foreign` FOREIGN KEY (`identity_document_type_id`) REFERENCES `cat_identity_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `districts` ADD CONSTRAINT `districts_province_id_foreign` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `establishments` ADD CONSTRAINT `establishments_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `establishments` ADD CONSTRAINT `establishments_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `establishments` ADD CONSTRAINT `establishments_district_id_foreign` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `establishments` ADD CONSTRAINT `establishments_province_id_foreign` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `persons` ADD CONSTRAINT `persons_address_type_id_foreign` FOREIGN KEY (`address_type_id`) REFERENCES `cat_address_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `persons` ADD CONSTRAINT `persons_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `persons` ADD CONSTRAINT `persons_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `persons` ADD CONSTRAINT `persons_district_id_foreign` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `persons` ADD CONSTRAINT `persons_identity_document_type_id_foreign` FOREIGN KEY (`identity_document_type_id`) REFERENCES `cat_identity_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `persons` ADD CONSTRAINT `persons_nationality_id_foreign` FOREIGN KEY (`nationality_id`) REFERENCES `countries` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `persons` ADD CONSTRAINT `persons_person_type_id_foreign` FOREIGN KEY (`person_type_id`) REFERENCES `person_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `persons` ADD CONSTRAINT `persons_province_id_foreign` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `bank_accounts` ADD CONSTRAINT `bank_accounts_bank_id_foreign` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `bank_accounts` ADD CONSTRAINT `bank_accounts_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `bank_accounts` ADD CONSTRAINT `bank_accounts_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `discount_coupon_usages` ADD CONSTRAINT `discount_coupon_usages_discount_coupon_id_foreign` FOREIGN KEY (`discount_coupon_id`) REFERENCES `discount_coupons` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `discount_coupon_usages` ADD CONSTRAINT `discount_coupon_usages_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
SQL,
            <<<'SQL'
ALTER TABLE `discount_coupon_usages` ADD CONSTRAINT `discount_coupon_usages_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL
SQL,
            <<<'SQL'
ALTER TABLE `dispatch_addresses` ADD CONSTRAINT `dispatch_addresses_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `hotel_categories` ADD CONSTRAINT `hotel_categories_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `hotel_floors` ADD CONSTRAINT `hotel_floors_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `hotel_rates` ADD CONSTRAINT `hotel_rates_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `person_address` ADD CONSTRAINT `person_address_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `person_address` ADD CONSTRAINT `person_address_district_id_foreign` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `person_address` ADD CONSTRAINT `person_address_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `person_address` ADD CONSTRAINT `person_address_province_id_foreign` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `person_addresses` ADD CONSTRAINT `person_addresses_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `person_addresses` ADD CONSTRAINT `person_addresses_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `person_addresses` ADD CONSTRAINT `person_addresses_district_id_foreign` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `person_addresses` ADD CONSTRAINT `person_addresses_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `person_addresses` ADD CONSTRAINT `person_addresses_province_id_foreign` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `plates` ADD CONSTRAINT `plates_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `series_device_groups` ADD CONSTRAINT `series_device_groups_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `tag_templates` ADD CONSTRAINT `tag_templates_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `template_columns_config` ADD CONSTRAINT `template_columns_config_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `users` ADD CONSTRAINT `users_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `users` ADD CONSTRAINT `users_restaurant_role_id_foreign` FOREIGN KEY (`restaurant_role_id`) REFERENCES `restaurant_roles` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `warehouses` ADD CONSTRAINT `warehouses_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `authorized_discount_users` ADD CONSTRAINT `authorized_discount_users_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `authorized_discount_users` ADD CONSTRAINT `authorized_discount_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `cash` ADD CONSTRAINT `cash_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `claims` ADD CONSTRAINT `claims_assigned_user_id_foreign` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
SQL,
            <<<'SQL'
ALTER TABLE `claims` ADD CONSTRAINT `claims_district_id_foreign` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `claims` ADD CONSTRAINT `claims_status_claim_id_foreign` FOREIGN KEY (`status_claim_id`) REFERENCES `status_claims` (`id`) ON DELETE SET NULL
SQL,
            <<<'SQL'
ALTER TABLE `columns_to_reports` ADD CONSTRAINT `columns_to_reports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `devolutions` ADD CONSTRAINT `devolutions_devolution_reason_id_foreign` FOREIGN KEY (`devolution_reason_id`) REFERENCES `devolution_reasons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `devolutions` ADD CONSTRAINT `devolutions_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `devolutions` ADD CONSTRAINT `devolutions_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `devolutions` ADD CONSTRAINT `devolutions_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `devolutions` ADD CONSTRAINT `devolutions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documentary_files` ADD CONSTRAINT `documentary_files_documentary_process_id_foreign` FOREIGN KEY (`documentary_process_id`) REFERENCES `documentary_processes` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `documentary_files` ADD CONSTRAINT `documentary_files_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `documentary_files` ADD CONSTRAINT `documentary_files_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `download_tray` ADD CONSTRAINT `download_tray_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `ejb_report_configurations` ADD CONSTRAINT `ejb_report_configurations_bank_account_pen_id_foreign` FOREIGN KEY (`bank_account_pen_id`) REFERENCES `bank_accounts` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `ejb_report_configurations` ADD CONSTRAINT `ejb_report_configurations_bank_account_usd_id_foreign` FOREIGN KEY (`bank_account_usd_id`) REFERENCES `bank_accounts` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `ejb_report_configurations` ADD CONSTRAINT `ejb_report_configurations_document_type_id_foreign` FOREIGN KEY (`document_type_id`) REFERENCES `cat_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `expenses` ADD CONSTRAINT `expenses_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `expenses` ADD CONSTRAINT `expenses_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `expenses` ADD CONSTRAINT `expenses_expense_reason_id_foreign` FOREIGN KEY (`expense_reason_id`) REFERENCES `expense_reasons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `expenses` ADD CONSTRAINT `expenses_expense_type_id_foreign` FOREIGN KEY (`expense_type_id`) REFERENCES `expense_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `expenses` ADD CONSTRAINT `expenses_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `expenses` ADD CONSTRAINT `expenses_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `expenses` ADD CONSTRAINT `expenses_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `expenses` ADD CONSTRAINT `expenses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `fixed_asset_purchases` ADD CONSTRAINT `fixed_asset_purchases_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `fixed_asset_purchases` ADD CONSTRAINT `fixed_asset_purchases_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `fixed_asset_purchases` ADD CONSTRAINT `fixed_asset_purchases_document_type_id_foreign` FOREIGN KEY (`document_type_id`) REFERENCES `cat_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `fixed_asset_purchases` ADD CONSTRAINT `fixed_asset_purchases_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `fixed_asset_purchases` ADD CONSTRAINT `fixed_asset_purchases_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `fixed_asset_purchases` ADD CONSTRAINT `fixed_asset_purchases_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `fixed_asset_purchases` ADD CONSTRAINT `fixed_asset_purchases_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `fixed_asset_purchases` ADD CONSTRAINT `fixed_asset_purchases_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `fixed_asset_purchases` ADD CONSTRAINT `fixed_asset_purchases_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `global_payments` ADD CONSTRAINT `global_payments_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `global_payments` ADD CONSTRAINT `global_payments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `guides` ADD CONSTRAINT `guides_document_type_id_foreign` FOREIGN KEY (`document_type_id`) REFERENCES `cat_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `guides` ADD CONSTRAINT `guides_inventory_transaction_id_foreign` FOREIGN KEY (`inventory_transaction_id`) REFERENCES `inventory_transactions` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `guides` ADD CONSTRAINT `guides_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `guides` ADD CONSTRAINT `guides_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `guides` ADD CONSTRAINT `guides_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `hotel_rents` ADD CONSTRAINT `hotel_rents_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `hotel_rents` ADD CONSTRAINT `hotel_rents_hotel_rate_id_foreign` FOREIGN KEY (`hotel_rate_id`) REFERENCES `hotel_rates` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `income` ADD CONSTRAINT `income_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `income` ADD CONSTRAINT `income_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `income` ADD CONSTRAINT `income_income_reason_id_foreign` FOREIGN KEY (`income_reason_id`) REFERENCES `income_reasons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `income` ADD CONSTRAINT `income_income_type_id_foreign` FOREIGN KEY (`income_type_id`) REFERENCES `income_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `income` ADD CONSTRAINT `income_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `income` ADD CONSTRAINT `income_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `income` ADD CONSTRAINT `income_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `items` ADD CONSTRAINT `items_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `items` ADD CONSTRAINT `items_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `items` ADD CONSTRAINT `items_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `items` ADD CONSTRAINT `items_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `items` ADD CONSTRAINT `items_item_type_id_foreign` FOREIGN KEY (`item_type_id`) REFERENCES `item_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `items` ADD CONSTRAINT `items_preparation_area_id_foreign` FOREIGN KEY (`preparation_area_id`) REFERENCES `restaurant_preparation_areas` (`id`) ON DELETE SET NULL
SQL,
            <<<'SQL'
ALTER TABLE `items` ADD CONSTRAINT `items_purchase_affectation_igv_type_id_foreign` FOREIGN KEY (`purchase_affectation_igv_type_id`) REFERENCES `cat_affectation_igv_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `items` ADD CONSTRAINT `items_sale_affectation_igv_type_id_foreign` FOREIGN KEY (`sale_affectation_igv_type_id`) REFERENCES `cat_affectation_igv_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `items` ADD CONSTRAINT `items_unit_type_id_foreign` FOREIGN KEY (`unit_type_id`) REFERENCES `cat_unit_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `items` ADD CONSTRAINT `items_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `items` ADD CONSTRAINT `items_web_platform_id_foreign` FOREIGN KEY (`web_platform_id`) REFERENCES `web_platforms` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_forms` ADD CONSTRAINT `order_forms_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_forms` ADD CONSTRAINT `order_forms_dispatcher_id_foreign` FOREIGN KEY (`dispatcher_id`) REFERENCES `dispatchers` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_forms` ADD CONSTRAINT `order_forms_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_forms` ADD CONSTRAINT `order_forms_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_forms` ADD CONSTRAINT `order_forms_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_forms` ADD CONSTRAINT `order_forms_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_forms` ADD CONSTRAINT `order_forms_transfer_reason_type_id_foreign` FOREIGN KEY (`transfer_reason_type_id`) REFERENCES `cat_transfer_reason_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_forms` ADD CONSTRAINT `order_forms_transport_mode_type_id_foreign` FOREIGN KEY (`transport_mode_type_id`) REFERENCES `cat_transport_mode_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_forms` ADD CONSTRAINT `order_forms_unit_type_id_foreign` FOREIGN KEY (`unit_type_id`) REFERENCES `cat_unit_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_forms` ADD CONSTRAINT `order_forms_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_notes` ADD CONSTRAINT `order_notes_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_notes` ADD CONSTRAINT `order_notes_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_notes` ADD CONSTRAINT `order_notes_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_notes` ADD CONSTRAINT `order_notes_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_notes` ADD CONSTRAINT `order_notes_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_notes` ADD CONSTRAINT `order_notes_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_notes` ADD CONSTRAINT `order_notes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `payment_links` ADD CONSTRAINT `payment_links_payment_link_type_id_foreign` FOREIGN KEY (`payment_link_type_id`) REFERENCES `payment_link_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `payment_links` ADD CONSTRAINT `payment_links_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL
SQL,
            <<<'SQL'
ALTER TABLE `payment_links` ADD CONSTRAINT `payment_links_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `payment_links` ADD CONSTRAINT `payment_links_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `pending_account_commissions` ADD CONSTRAINT `pending_account_commissions_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `perceptions` ADD CONSTRAINT `perceptions_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `perceptions` ADD CONSTRAINT `perceptions_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `perceptions` ADD CONSTRAINT `perceptions_document_type_id_foreign` FOREIGN KEY (`document_type_id`) REFERENCES `cat_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `perceptions` ADD CONSTRAINT `perceptions_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `perceptions` ADD CONSTRAINT `perceptions_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `perceptions` ADD CONSTRAINT `perceptions_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `perceptions` ADD CONSTRAINT `perceptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_quotations` ADD CONSTRAINT `purchase_quotations_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_quotations` ADD CONSTRAINT `purchase_quotations_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_quotations` ADD CONSTRAINT `purchase_quotations_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_quotations` ADD CONSTRAINT `purchase_quotations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_settlements` ADD CONSTRAINT `purchase_settlements_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_settlements` ADD CONSTRAINT `purchase_settlements_document_type_id_foreign` FOREIGN KEY (`document_type_id`) REFERENCES `cat_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_settlements` ADD CONSTRAINT `purchase_settlements_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_settlements` ADD CONSTRAINT `purchase_settlements_operation_type_id_foreign` FOREIGN KEY (`operation_type_id`) REFERENCES `cat_operation_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_settlements` ADD CONSTRAINT `purchase_settlements_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_settlements` ADD CONSTRAINT `purchase_settlements_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_settlements` ADD CONSTRAINT `purchase_settlements_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_settlements` ADD CONSTRAINT `purchase_settlements_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_settlements` ADD CONSTRAINT `purchase_settlements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `retentions` ADD CONSTRAINT `retentions_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `retentions` ADD CONSTRAINT `retentions_document_type_id_foreign` FOREIGN KEY (`document_type_id`) REFERENCES `cat_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `retentions` ADD CONSTRAINT `retentions_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `retentions` ADD CONSTRAINT `retentions_retention_type_id_foreign` FOREIGN KEY (`retention_type_id`) REFERENCES `cat_retention_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `retentions` ADD CONSTRAINT `retentions_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `retentions` ADD CONSTRAINT `retentions_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `retentions` ADD CONSTRAINT `retentions_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `retentions` ADD CONSTRAINT `retentions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_opportunities` ADD CONSTRAINT `sale_opportunities_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_opportunities` ADD CONSTRAINT `sale_opportunities_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_opportunities` ADD CONSTRAINT `sale_opportunities_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_opportunities` ADD CONSTRAINT `sale_opportunities_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_opportunities` ADD CONSTRAINT `sale_opportunities_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_opportunities` ADD CONSTRAINT `sale_opportunities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `series` ADD CONSTRAINT `series_document_type_id_foreign` FOREIGN KEY (`document_type_id`) REFERENCES `cat_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `series` ADD CONSTRAINT `series_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `series` ADD CONSTRAINT `series_series_device_group_id_foreign` FOREIGN KEY (`series_device_group_id`) REFERENCES `series_device_groups` (`id`) ON DELETE SET NULL
SQL,
            <<<'SQL'
ALTER TABLE `summaries` ADD CONSTRAINT `summaries_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `summaries` ADD CONSTRAINT `summaries_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `summaries` ADD CONSTRAINT `summaries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `system_activity_logs` ADD CONSTRAINT `system_activity_logs_system_activity_log_type_id_foreign` FOREIGN KEY (`system_activity_log_type_id`) REFERENCES `system_activity_log_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `system_activity_logs` ADD CONSTRAINT `system_activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `tag_template_fields` ADD CONSTRAINT `tag_template_fields_tag_template_id_foreign` FOREIGN KEY (`tag_template_id`) REFERENCES `tag_templates` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `technical_services` ADD CONSTRAINT `technical_services_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `technical_services` ADD CONSTRAINT `technical_services_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `technical_services` ADD CONSTRAINT `technical_services_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `user_commissions` ADD CONSTRAINT `user_commissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `voided` ADD CONSTRAINT `voided_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `voided` ADD CONSTRAINT `voided_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `voided` ADD CONSTRAINT `voided_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `cash_transactions` ADD CONSTRAINT `cash_transactions_cash_id_foreign` FOREIGN KEY (`cash_id`) REFERENCES `cash` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `cash_transactions` ADD CONSTRAINT `cash_transactions_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `devolution_items` ADD CONSTRAINT `devolution_items_devolution_id_foreign` FOREIGN KEY (`devolution_id`) REFERENCES `devolutions` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `devolution_items` ADD CONSTRAINT `devolution_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `expense_items` ADD CONSTRAINT `expense_items_expense_id_foreign` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `expense_payments` ADD CONSTRAINT `expense_payments_card_brand_id_foreign` FOREIGN KEY (`card_brand_id`) REFERENCES `card_brands` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `expense_payments` ADD CONSTRAINT `expense_payments_expense_id_foreign` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `expense_payments` ADD CONSTRAINT `expense_payments_expense_method_type_id_foreign` FOREIGN KEY (`expense_method_type_id`) REFERENCES `expense_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `fixed_asset_purchase_items` ADD CONSTRAINT `fixed_asset_purchase_items_affectation_igv_type_id_foreign` FOREIGN KEY (`affectation_igv_type_id`) REFERENCES `cat_affectation_igv_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `fixed_asset_purchase_items` ADD CONSTRAINT `fixed_asset_purchase_items_fixed_asset_item_id_foreign` FOREIGN KEY (`fixed_asset_item_id`) REFERENCES `fixed_asset_items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `fixed_asset_purchase_items` ADD CONSTRAINT `fixed_asset_purchase_items_fixed_asset_purchase_id_foreign` FOREIGN KEY (`fixed_asset_purchase_id`) REFERENCES `fixed_asset_purchases` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `fixed_asset_purchase_items` ADD CONSTRAINT `fixed_asset_purchase_items_price_type_id_foreign` FOREIGN KEY (`price_type_id`) REFERENCES `cat_price_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `guide_items` ADD CONSTRAINT `guide_items_guide_id_foreign` FOREIGN KEY (`guide_id`) REFERENCES `guides` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `guide_items` ADD CONSTRAINT `guide_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `hotel_rooms` ADD CONSTRAINT `hotel_rooms_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `hotel_rooms` ADD CONSTRAINT `hotel_rooms_hotel_category_id_foreign` FOREIGN KEY (`hotel_category_id`) REFERENCES `hotel_categories` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `hotel_rooms` ADD CONSTRAINT `hotel_rooms_hotel_floor_id_foreign` FOREIGN KEY (`hotel_floor_id`) REFERENCES `hotel_floors` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `hotel_rooms` ADD CONSTRAINT `hotel_rooms_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `income_items` ADD CONSTRAINT `income_items_income_id_foreign` FOREIGN KEY (`income_id`) REFERENCES `income` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `income_payments` ADD CONSTRAINT `income_payments_card_brand_id_foreign` FOREIGN KEY (`card_brand_id`) REFERENCES `card_brands` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `income_payments` ADD CONSTRAINT `income_payments_income_id_foreign` FOREIGN KEY (`income_id`) REFERENCES `income` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `income_payments` ADD CONSTRAINT `income_payments_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `inventories` ADD CONSTRAINT `inventories_inventories_transfer_id_foreign` FOREIGN KEY (`inventories_transfer_id`) REFERENCES `inventories_transfer` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `inventories` ADD CONSTRAINT `inventories_inventory_transaction_id_foreign` FOREIGN KEY (`inventory_transaction_id`) REFERENCES `inventory_transactions` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `inventories` ADD CONSTRAINT `inventories_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `inventories` ADD CONSTRAINT `inventories_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `inventory_kardex` ADD CONSTRAINT `inventory_kardex_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `inventory_kardex` ADD CONSTRAINT `inventory_kardex_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `item_images` ADD CONSTRAINT `item_images_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `item_lots` ADD CONSTRAINT `item_lots_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `item_lots` ADD CONSTRAINT `item_lots_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `item_lots_group` ADD CONSTRAINT `item_lots_group_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `item_modifier_group` ADD CONSTRAINT `item_modifier_group_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `item_modifier_group` ADD CONSTRAINT `item_modifier_group_modifier_group_id_foreign` FOREIGN KEY (`modifier_group_id`) REFERENCES `modifier_groups` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `item_sets` ADD CONSTRAINT `item_sets_individual_item_id_foreign` FOREIGN KEY (`individual_item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `item_sets` ADD CONSTRAINT `item_sets_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `item_supplies` ADD CONSTRAINT `item_supplies_individual_item_id_foreign` FOREIGN KEY (`individual_item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `item_supplies` ADD CONSTRAINT `item_supplies_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `item_tags` ADD CONSTRAINT `item_tags_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `item_tags` ADD CONSTRAINT `item_tags_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `item_unit_types` ADD CONSTRAINT `item_unit_types_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `item_unit_types` ADD CONSTRAINT `item_unit_types_unit_type_id_foreign` FOREIGN KEY (`unit_type_id`) REFERENCES `cat_unit_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `item_warehouse` ADD CONSTRAINT `item_warehouse_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `item_warehouse` ADD CONSTRAINT `item_warehouse_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `item_warehouse_prices` ADD CONSTRAINT `item_warehouse_prices_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `item_warehouse_prices` ADD CONSTRAINT `item_warehouse_prices_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `items_rating` ADD CONSTRAINT `items_rating_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `items_rating` ADD CONSTRAINT `items_rating_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_form_items` ADD CONSTRAINT `order_form_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_form_items` ADD CONSTRAINT `order_form_items_order_form_id_foreign` FOREIGN KEY (`order_form_id`) REFERENCES `order_forms` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `order_note_items` ADD CONSTRAINT `order_note_items_affectation_igv_type_id_foreign` FOREIGN KEY (`affectation_igv_type_id`) REFERENCES `cat_affectation_igv_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_note_items` ADD CONSTRAINT `order_note_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_note_items` ADD CONSTRAINT `order_note_items_order_note_id_foreign` FOREIGN KEY (`order_note_id`) REFERENCES `order_notes` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `order_note_items` ADD CONSTRAINT `order_note_items_price_type_id_foreign` FOREIGN KEY (`price_type_id`) REFERENCES `cat_price_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `order_note_items` ADD CONSTRAINT `order_note_items_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `payment_link_payments` ADD CONSTRAINT `payment_link_payments_payment_link_id_foreign` FOREIGN KEY (`payment_link_id`) REFERENCES `payment_links` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `perception_documents` ADD CONSTRAINT `perception_details_perception_id_foreign` FOREIGN KEY (`perception_id`) REFERENCES `perceptions` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `perception_documents` ADD CONSTRAINT `perception_documents_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `perception_documents` ADD CONSTRAINT `perception_documents_document_type_id_foreign` FOREIGN KEY (`document_type_id`) REFERENCES `cat_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `promotions` ADD CONSTRAINT `promotions_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `promotions` ADD CONSTRAINT `promotions_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL
SQL,
            <<<'SQL'
ALTER TABLE `purchase_orders` ADD CONSTRAINT `purchase_orders_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_orders` ADD CONSTRAINT `purchase_orders_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_orders` ADD CONSTRAINT `purchase_orders_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_orders` ADD CONSTRAINT `purchase_orders_purchase_quotation_id_foreign` FOREIGN KEY (`purchase_quotation_id`) REFERENCES `purchase_quotations` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_orders` ADD CONSTRAINT `purchase_orders_sale_opportunity_id_foreign` FOREIGN KEY (`sale_opportunity_id`) REFERENCES `sale_opportunities` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_orders` ADD CONSTRAINT `purchase_orders_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_orders` ADD CONSTRAINT `purchase_orders_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_orders` ADD CONSTRAINT `purchase_orders_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_orders` ADD CONSTRAINT `purchase_orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_quotation_items` ADD CONSTRAINT `purchase_quotation_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_quotation_items` ADD CONSTRAINT `purchase_quotation_items_purchase_quotation_id_foreign` FOREIGN KEY (`purchase_quotation_id`) REFERENCES `purchase_quotations` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `purchase_settlement_items` ADD CONSTRAINT `p_s_i_income_tax_affectation_igv_type_id_fk` FOREIGN KEY (`income_tax_affectation_igv_type_id`) REFERENCES `cat_affectation_igv_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_settlement_items` ADD CONSTRAINT `purchase_settlement_items_affectation_igv_type_id_foreign` FOREIGN KEY (`affectation_igv_type_id`) REFERENCES `cat_affectation_igv_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_settlement_items` ADD CONSTRAINT `purchase_settlement_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_settlement_items` ADD CONSTRAINT `purchase_settlement_items_price_type_id_foreign` FOREIGN KEY (`price_type_id`) REFERENCES `cat_price_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_settlement_items` ADD CONSTRAINT `purchase_settlement_items_purchase_settlement_id_foreign` FOREIGN KEY (`purchase_settlement_id`) REFERENCES `purchase_settlements` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `purchase_settlement_payments` ADD CONSTRAINT `purchase_settlement_payments_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_settlement_payments` ADD CONSTRAINT `purchase_settlement_payments_purchase_settlement_id_foreign` FOREIGN KEY (`purchase_settlement_id`) REFERENCES `purchase_settlements` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `quotations` ADD CONSTRAINT `quotations_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `quotations` ADD CONSTRAINT `quotations_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `quotations` ADD CONSTRAINT `quotations_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `quotations` ADD CONSTRAINT `quotations_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `quotations` ADD CONSTRAINT `quotations_sale_opportunity_id_foreign` FOREIGN KEY (`sale_opportunity_id`) REFERENCES `sale_opportunities` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `quotations` ADD CONSTRAINT `quotations_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `quotations` ADD CONSTRAINT `quotations_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `quotations` ADD CONSTRAINT `quotations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `restaurant_item_supplies` ADD CONSTRAINT `restaurant_item_supplies_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `restaurant_item_supplies` ADD CONSTRAINT `restaurant_item_supplies_supply_id_foreign` FOREIGN KEY (`supply_id`) REFERENCES `supplies` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `restaurant_stock_products` ADD CONSTRAINT `restaurant_stock_products_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `retention_documents` ADD CONSTRAINT `retention_documents_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `retention_documents` ADD CONSTRAINT `retention_documents_document_type_id_foreign` FOREIGN KEY (`document_type_id`) REFERENCES `cat_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `retention_documents` ADD CONSTRAINT `retention_documents_retention_id_foreign` FOREIGN KEY (`retention_id`) REFERENCES `retentions` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `sale_opportunity_files` ADD CONSTRAINT `sale_opportunity_files_sale_opportunity_id_foreign` FOREIGN KEY (`sale_opportunity_id`) REFERENCES `sale_opportunities` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `sale_opportunity_items` ADD CONSTRAINT `sale_opportunity_items_affectation_igv_type_id_foreign` FOREIGN KEY (`affectation_igv_type_id`) REFERENCES `cat_affectation_igv_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_opportunity_items` ADD CONSTRAINT `sale_opportunity_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_opportunity_items` ADD CONSTRAINT `sale_opportunity_items_price_type_id_foreign` FOREIGN KEY (`price_type_id`) REFERENCES `cat_price_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_opportunity_items` ADD CONSTRAINT `sale_opportunity_items_sale_opportunity_id_foreign` FOREIGN KEY (`sale_opportunity_id`) REFERENCES `sale_opportunities` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `series_configurations` ADD CONSTRAINT `series_configurations_document_type_id_foreign` FOREIGN KEY (`document_type_id`) REFERENCES `cat_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `series_configurations` ADD CONSTRAINT `series_configurations_series_id_foreign` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `technical_service_payments` ADD CONSTRAINT `technical_service_payments_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `technical_service_payments` ADD CONSTRAINT `technical_service_payments_technical_service_id_foreign` FOREIGN KEY (`technical_service_id`) REFERENCES `technical_services` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `transactions` ADD CONSTRAINT `transactions_payment_link_id_foreign` FOREIGN KEY (`payment_link_id`) REFERENCES `payment_links` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `transactions` ADD CONSTRAINT `transactions_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `transactions` ADD CONSTRAINT `transactions_transaction_state_id_foreign` FOREIGN KEY (`transaction_state_id`) REFERENCES `transaction_states` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `user_default_document_types` ADD CONSTRAINT `user_default_document_types_document_type_id_foreign` FOREIGN KEY (`document_type_id`) REFERENCES `cat_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `user_default_document_types` ADD CONSTRAINT `user_default_document_types_series_id_foreign` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `user_default_document_types` ADD CONSTRAINT `user_default_document_types_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `weighted_average_costs` ADD CONSTRAINT `weighted_average_costs_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `contracts` ADD CONSTRAINT `contracts_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `contracts` ADD CONSTRAINT `contracts_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `contracts` ADD CONSTRAINT `contracts_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `contracts` ADD CONSTRAINT `contracts_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `contracts` ADD CONSTRAINT `contracts_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `contracts` ADD CONSTRAINT `contracts_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `contracts` ADD CONSTRAINT `contracts_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `contract_state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `contracts` ADD CONSTRAINT `contracts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `hotel_room_rates` ADD CONSTRAINT `hotel_room_rates_hotel_rate_id_foreign` FOREIGN KEY (`hotel_rate_id`) REFERENCES `hotel_rates` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `hotel_room_rates` ADD CONSTRAINT `hotel_room_rates_hotel_room_id_foreign` FOREIGN KEY (`hotel_room_id`) REFERENCES `hotel_rooms` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `inventory_transfer_items` ADD CONSTRAINT `inventory_transfer_items_inventory_transfer_id_foreign` FOREIGN KEY (`inventory_transfer_id`) REFERENCES `inventories_transfer` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `inventory_transfer_items` ADD CONSTRAINT `inventory_transfer_items_item_lot_id_foreign` FOREIGN KEY (`item_lot_id`) REFERENCES `item_lots` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `inventory_transfer_items` ADD CONSTRAINT `inventory_transfer_items_item_lots_group_id_foreign` FOREIGN KEY (`item_lots_group_id`) REFERENCES `item_lots_group` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `item_unit_type_prices` ADD CONSTRAINT `item_unit_type_prices_item_unit_type_id_foreign` FOREIGN KEY (`item_unit_type_id`) REFERENCES `item_unit_types` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `item_unit_type_prices` ADD CONSTRAINT `item_unit_type_prices_price_label_id_foreign` FOREIGN KEY (`price_label_id`) REFERENCES `price_labels` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_order_items` ADD CONSTRAINT `purchase_order_items_affectation_igv_type_id_foreign` FOREIGN KEY (`affectation_igv_type_id`) REFERENCES `cat_affectation_igv_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_order_items` ADD CONSTRAINT `purchase_order_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_order_items` ADD CONSTRAINT `purchase_order_items_price_type_id_foreign` FOREIGN KEY (`price_type_id`) REFERENCES `cat_price_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_order_items` ADD CONSTRAINT `purchase_order_items_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `purchases` ADD CONSTRAINT `purchases_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchases` ADD CONSTRAINT `purchases_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchases` ADD CONSTRAINT `purchases_document_type_id_foreign` FOREIGN KEY (`document_type_id`) REFERENCES `cat_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchases` ADD CONSTRAINT `purchases_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchases` ADD CONSTRAINT `purchases_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchases` ADD CONSTRAINT `purchases_payment_condition_id_foreign` FOREIGN KEY (`payment_condition_id`) REFERENCES `general_payment_conditions` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchases` ADD CONSTRAINT `purchases_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchases` ADD CONSTRAINT `purchases_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchases` ADD CONSTRAINT `purchases_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchases` ADD CONSTRAINT `purchases_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchases` ADD CONSTRAINT `purchases_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `quotation_items` ADD CONSTRAINT `quotation_items_affectation_igv_type_id_foreign` FOREIGN KEY (`affectation_igv_type_id`) REFERENCES `cat_affectation_igv_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `quotation_items` ADD CONSTRAINT `quotation_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `quotation_items` ADD CONSTRAINT `quotation_items_price_type_id_foreign` FOREIGN KEY (`price_type_id`) REFERENCES `cat_price_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `quotation_items` ADD CONSTRAINT `quotation_items_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `quotation_payments` ADD CONSTRAINT `quotation_payments_card_brand_id_foreign` FOREIGN KEY (`card_brand_id`) REFERENCES `card_brands` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `quotation_payments` ADD CONSTRAINT `quotation_payments_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `quotation_payments` ADD CONSTRAINT `quotation_payments_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `sale_notes` ADD CONSTRAINT `sale_notes_agent_id_foreign` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_notes` ADD CONSTRAINT `sale_notes_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_notes` ADD CONSTRAINT `sale_notes_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_notes` ADD CONSTRAINT `sale_notes_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_notes` ADD CONSTRAINT `sale_notes_hotel_rent_id_foreign` FOREIGN KEY (`hotel_rent_id`) REFERENCES `hotel_rents` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_notes` ADD CONSTRAINT `sale_notes_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_notes` ADD CONSTRAINT `sale_notes_order_note_id_foreign` FOREIGN KEY (`order_note_id`) REFERENCES `order_notes` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_notes` ADD CONSTRAINT `sale_notes_payment_condition_id_foreign` FOREIGN KEY (`payment_condition_id`) REFERENCES `payment_conditions` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_notes` ADD CONSTRAINT `sale_notes_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_notes` ADD CONSTRAINT `sale_notes_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_notes` ADD CONSTRAINT `sale_notes_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_notes` ADD CONSTRAINT `sale_notes_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_notes` ADD CONSTRAINT `sale_notes_technical_service_id_foreign` FOREIGN KEY (`technical_service_id`) REFERENCES `technical_services` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_notes` ADD CONSTRAINT `sale_notes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `transaction_queries` ADD CONSTRAINT `transaction_queries_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `contract_items` ADD CONSTRAINT `contract_items_affectation_igv_type_id_foreign` FOREIGN KEY (`affectation_igv_type_id`) REFERENCES `cat_affectation_igv_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `contract_items` ADD CONSTRAINT `contract_items_contract_id_foreign` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `contract_items` ADD CONSTRAINT `contract_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `contract_items` ADD CONSTRAINT `contract_items_price_type_id_foreign` FOREIGN KEY (`price_type_id`) REFERENCES `cat_price_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `contract_payments` ADD CONSTRAINT `contract_payments_card_brand_id_foreign` FOREIGN KEY (`card_brand_id`) REFERENCES `card_brands` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `contract_payments` ADD CONSTRAINT `contract_payments_contract_id_foreign` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `contract_payments` ADD CONSTRAINT `contract_payments_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatch_sale_notes` ADD CONSTRAINT `dispatch_sale_notes_sale_note_id_foreign` FOREIGN KEY (`sale_note_id`) REFERENCES `sale_notes` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `hotel_rent_orders` ADD CONSTRAINT `hotel_rent_orders_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `hotel_rent_orders` ADD CONSTRAINT `hotel_rent_orders_hotel_rent_id_foreign` FOREIGN KEY (`hotel_rent_id`) REFERENCES `hotel_rents` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `hotel_rent_orders` ADD CONSTRAINT `hotel_rent_orders_sale_note_id_foreign` FOREIGN KEY (`sale_note_id`) REFERENCES `sale_notes` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_fee` ADD CONSTRAINT `purchase_fee_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_fee` ADD CONSTRAINT `purchase_fee_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_fee` ADD CONSTRAINT `purchase_fee_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `purchase_items` ADD CONSTRAINT `purchase_items_affectation_igv_type_id_foreign` FOREIGN KEY (`affectation_igv_type_id`) REFERENCES `cat_affectation_igv_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_items` ADD CONSTRAINT `purchase_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_items` ADD CONSTRAINT `purchase_items_price_type_id_foreign` FOREIGN KEY (`price_type_id`) REFERENCES `cat_price_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_items` ADD CONSTRAINT `purchase_items_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `purchase_items` ADD CONSTRAINT `purchase_items_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_payments` ADD CONSTRAINT `purchase_payments_card_brand_id_foreign` FOREIGN KEY (`card_brand_id`) REFERENCES `card_brands` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_payments` ADD CONSTRAINT `purchase_payments_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_payments` ADD CONSTRAINT `purchase_payments_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `purchase_supplies` ADD CONSTRAINT `purchase_supplies_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `purchase_supplies` ADD CONSTRAINT `purchase_supplies_supply_id_foreign` FOREIGN KEY (`supply_id`) REFERENCES `supplies` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_note_fees` ADD CONSTRAINT `sale_note_fees_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_note_fees` ADD CONSTRAINT `sale_note_fees_sale_note_id_foreign` FOREIGN KEY (`sale_note_id`) REFERENCES `sale_notes` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `sale_note_items` ADD CONSTRAINT `sale_note_items_affectation_igv_type_id_foreign` FOREIGN KEY (`affectation_igv_type_id`) REFERENCES `cat_affectation_igv_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_note_items` ADD CONSTRAINT `sale_note_items_inventory_kardex_id_foreign` FOREIGN KEY (`inventory_kardex_id`) REFERENCES `inventory_kardex` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_note_items` ADD CONSTRAINT `sale_note_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_note_items` ADD CONSTRAINT `sale_note_items_price_type_id_foreign` FOREIGN KEY (`price_type_id`) REFERENCES `cat_price_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_note_items` ADD CONSTRAINT `sale_note_items_sale_note_id_foreign` FOREIGN KEY (`sale_note_id`) REFERENCES `sale_notes` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `sale_note_items` ADD CONSTRAINT `sale_note_items_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_note_payments` ADD CONSTRAINT `sale_note_payments_card_brand_id_foreign` FOREIGN KEY (`card_brand_id`) REFERENCES `card_brands` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_note_payments` ADD CONSTRAINT `sale_note_payments_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `sale_note_payments` ADD CONSTRAINT `sale_note_payments_sale_note_id_foreign` FOREIGN KEY (`sale_note_id`) REFERENCES `sale_notes` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `hotel_rent_items` ADD CONSTRAINT `hotel_rent_items_hotel_rent_id_foreign` FOREIGN KEY (`hotel_rent_id`) REFERENCES `hotel_rents` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `hotel_rent_items` ADD CONSTRAINT `hotel_rent_items_hotel_rent_order_id_foreign` FOREIGN KEY (`hotel_rent_order_id`) REFERENCES `hotel_rent_orders` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `hotel_rent_items` ADD CONSTRAINT `hotel_rent_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `hotel_rent_item_payments` ADD CONSTRAINT `hotel_rent_item_payments_hotel_rent_item_id_foreign` FOREIGN KEY (`hotel_rent_item_id`) REFERENCES `hotel_rent_items` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `hotel_rent_item_payments` ADD CONSTRAINT `hotel_rent_item_payments_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `cash_documents` ADD CONSTRAINT `cash_documents_cash_id_foreign` FOREIGN KEY (`cash_id`) REFERENCES `cash` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `cash_documents` ADD CONSTRAINT `cash_documents_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `cash_documents` ADD CONSTRAINT `cash_documents_expense_payment_id_foreign` FOREIGN KEY (`expense_payment_id`) REFERENCES `expense_payments` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `cash_documents` ADD CONSTRAINT `cash_documents_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `cash_documents` ADD CONSTRAINT `cash_documents_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `cash_documents` ADD CONSTRAINT `cash_documents_sale_note_id_foreign` FOREIGN KEY (`sale_note_id`) REFERENCES `sale_notes` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `cash_documents` ADD CONSTRAINT `cash_documents_technical_service_id_foreign` FOREIGN KEY (`technical_service_id`) REFERENCES `technical_services` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `cash_document_credits` ADD CONSTRAINT `cash_document_credits_cash_id_foreign` FOREIGN KEY (`cash_id`) REFERENCES `cash` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `cash_document_credits` ADD CONSTRAINT `cash_document_credits_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `cash_document_credits` ADD CONSTRAINT `cash_document_credits_sale_note_id_foreign` FOREIGN KEY (`sale_note_id`) REFERENCES `sale_notes` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `cash_document_payments` ADD CONSTRAINT `cash_document_payments_cash_document_credit_id_foreign` FOREIGN KEY (`cash_document_credit_id`) REFERENCES `cash_document_credits` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `cash_document_payments` ADD CONSTRAINT `cash_document_payments_cash_document_id_foreign` FOREIGN KEY (`cash_document_id`) REFERENCES `cash_documents` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `cash_document_payments` ADD CONSTRAINT `cash_document_payments_cash_id_foreign` FOREIGN KEY (`cash_id`) REFERENCES `cash` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `cash_document_payments` ADD CONSTRAINT `cash_document_payments_document_payment_id_foreign` FOREIGN KEY (`document_payment_id`) REFERENCES `document_payments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `cash_document_payments` ADD CONSTRAINT `cash_document_payments_sale_note_payment_id_foreign` FOREIGN KEY (`sale_note_payment_id`) REFERENCES `sale_note_payments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatches` ADD CONSTRAINT `dispatches_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `buyers` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatches` ADD CONSTRAINT `dispatches_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatches` ADD CONSTRAINT `dispatches_dispatcher_id_foreign` FOREIGN KEY (`dispatcher_id`) REFERENCES `dispatchers` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatches` ADD CONSTRAINT `dispatches_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `dispatches` ADD CONSTRAINT `dispatches_document_type_id_foreign` FOREIGN KEY (`document_type_id`) REFERENCES `cat_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatches` ADD CONSTRAINT `dispatches_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatches` ADD CONSTRAINT `dispatches_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatches` ADD CONSTRAINT `dispatches_receiver_address_id_foreign` FOREIGN KEY (`receiver_address_id`) REFERENCES `dispatch_addresses` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatches` ADD CONSTRAINT `dispatches_receiver_id_foreign` FOREIGN KEY (`receiver_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatches` ADD CONSTRAINT `dispatches_sender_address_id_foreign` FOREIGN KEY (`sender_address_id`) REFERENCES `dispatch_addresses` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatches` ADD CONSTRAINT `dispatches_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatches` ADD CONSTRAINT `dispatches_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatches` ADD CONSTRAINT `dispatches_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatches` ADD CONSTRAINT `dispatches_transfer_reason_type_id_foreign` FOREIGN KEY (`transfer_reason_type_id`) REFERENCES `cat_transfer_reason_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatches` ADD CONSTRAINT `dispatches_transport_id_foreign` FOREIGN KEY (`transport_id`) REFERENCES `transports` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatches` ADD CONSTRAINT `dispatches_transport_mode_type_id_foreign` FOREIGN KEY (`transport_mode_type_id`) REFERENCES `cat_transport_mode_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatches` ADD CONSTRAINT `dispatches_unit_type_id_foreign` FOREIGN KEY (`unit_type_id`) REFERENCES `cat_unit_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatches` ADD CONSTRAINT `dispatches_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `dispatch_items` ADD CONSTRAINT `dispatch_items_dispatch_id_foreign` FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `dispatch_items` ADD CONSTRAINT `dispatch_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documents` ADD CONSTRAINT `documents_agent_id_foreign` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documents` ADD CONSTRAINT `documents_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documents` ADD CONSTRAINT `documents_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `persons` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documents` ADD CONSTRAINT `documents_dispatch_id_foreign` FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documents` ADD CONSTRAINT `documents_document_type_id_foreign` FOREIGN KEY (`document_type_id`) REFERENCES `cat_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documents` ADD CONSTRAINT `documents_establishment_id_foreign` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documents` ADD CONSTRAINT `documents_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documents` ADD CONSTRAINT `documents_hotel_rent_id_foreign` FOREIGN KEY (`hotel_rent_id`) REFERENCES `hotel_rents` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documents` ADD CONSTRAINT `documents_order_note_id_foreign` FOREIGN KEY (`order_note_id`) REFERENCES `order_notes` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documents` ADD CONSTRAINT `documents_payment_condition_id_foreign` FOREIGN KEY (`payment_condition_id`) REFERENCES `payment_conditions` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documents` ADD CONSTRAINT `documents_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documents` ADD CONSTRAINT `documents_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documents` ADD CONSTRAINT `documents_sale_note_id_foreign` FOREIGN KEY (`sale_note_id`) REFERENCES `sale_notes` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documents` ADD CONSTRAINT `documents_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documents` ADD CONSTRAINT `documents_soap_type_id_foreign` FOREIGN KEY (`soap_type_id`) REFERENCES `soap_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documents` ADD CONSTRAINT `documents_state_type_id_foreign` FOREIGN KEY (`state_type_id`) REFERENCES `state_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documents` ADD CONSTRAINT `documents_technical_service_id_foreign` FOREIGN KEY (`technical_service_id`) REFERENCES `technical_services` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `documents` ADD CONSTRAINT `documents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `document_fee` ADD CONSTRAINT `document_fee_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `document_fee` ADD CONSTRAINT `document_fee_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `document_hotels` ADD CONSTRAINT `document_hotels_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `document_hotels` ADD CONSTRAINT `document_hotels_identity_document_type_id_foreign` FOREIGN KEY (`identity_document_type_id`) REFERENCES `cat_identity_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `document_items` ADD CONSTRAINT `document_items_affectation_igv_type_id_foreign` FOREIGN KEY (`affectation_igv_type_id`) REFERENCES `cat_affectation_igv_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `document_items` ADD CONSTRAINT `document_items_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `document_items` ADD CONSTRAINT `document_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `document_items` ADD CONSTRAINT `document_items_price_type_id_foreign` FOREIGN KEY (`price_type_id`) REFERENCES `cat_price_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `document_items` ADD CONSTRAINT `document_items_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `document_payments` ADD CONSTRAINT `document_payments_card_brand_id_foreign` FOREIGN KEY (`card_brand_id`) REFERENCES `card_brands` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `document_payments` ADD CONSTRAINT `document_payments_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `document_payments` ADD CONSTRAINT `document_payments_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `document_transports` ADD CONSTRAINT `document_transports_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `document_transports` ADD CONSTRAINT `document_transports_identity_document_type_id_foreign` FOREIGN KEY (`identity_document_type_id`) REFERENCES `cat_identity_document_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `invoices` ADD CONSTRAINT `invoices_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `invoices` ADD CONSTRAINT `invoices_operation_type_id_foreign` FOREIGN KEY (`operation_type_id`) REFERENCES `cat_operation_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `kardex` ADD CONSTRAINT `kardex_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `kardex` ADD CONSTRAINT `kardex_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `kardex` ADD CONSTRAINT `kardex_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `kardex` ADD CONSTRAINT `kardex_purchase_settlement_id_foreign` FOREIGN KEY (`purchase_settlement_id`) REFERENCES `purchase_settlements` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `kardex` ADD CONSTRAINT `kardex_sale_note_id_foreign` FOREIGN KEY (`sale_note_id`) REFERENCES `sale_notes` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `notes` ADD CONSTRAINT `notes_affected_document_id_foreign` FOREIGN KEY (`affected_document_id`) REFERENCES `documents` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `notes` ADD CONSTRAINT `notes_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `notes` ADD CONSTRAINT `notes_note_credit_type_id_foreign` FOREIGN KEY (`note_credit_type_id`) REFERENCES `cat_note_credit_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `notes` ADD CONSTRAINT `notes_note_debit_type_id_foreign` FOREIGN KEY (`note_debit_type_id`) REFERENCES `cat_note_debit_types` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `summary_documents` ADD CONSTRAINT `summary_documents_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `summary_documents` ADD CONSTRAINT `summary_documents_summary_id_foreign` FOREIGN KEY (`summary_id`) REFERENCES `summaries` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `voided_documents` ADD CONSTRAINT `voided_documents_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`)
SQL,
            <<<'SQL'
ALTER TABLE `voided_documents` ADD CONSTRAINT `voided_documents_voided_id_foreign` FOREIGN KEY (`voided_id`) REFERENCES `voided` (`id`) ON DELETE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `restaurant_tables` ADD CONSTRAINT `restaurant_tables_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `restaurant_table_groups` (`id`) ON DELETE SET NULL
SQL,
            <<<'SQL'
ALTER TABLE `restaurant_table_groups` ADD CONSTRAINT `restaurant_table_groups_main_table_id_foreign` FOREIGN KEY (`main_table_id`) REFERENCES `restaurant_tables` (`id`) ON DELETE SET NULL
SQL
        ];

        foreach ($statements as $statement) {
            DB::unprepared($statement);
        }
    }

    public function down(): void
    {
        $foreignKeys = [
            ['table' => 'restaurant_table_groups', 'name' => 'restaurant_table_groups_main_table_id_foreign'],
            ['table' => 'restaurant_tables', 'name' => 'restaurant_tables_group_id_foreign'],
            ['table' => 'voided_documents', 'name' => 'voided_documents_voided_id_foreign'],
            ['table' => 'voided_documents', 'name' => 'voided_documents_document_id_foreign'],
            ['table' => 'summary_documents', 'name' => 'summary_documents_summary_id_foreign'],
            ['table' => 'summary_documents', 'name' => 'summary_documents_document_id_foreign'],
            ['table' => 'notes', 'name' => 'notes_note_debit_type_id_foreign'],
            ['table' => 'notes', 'name' => 'notes_note_credit_type_id_foreign'],
            ['table' => 'notes', 'name' => 'notes_document_id_foreign'],
            ['table' => 'notes', 'name' => 'notes_affected_document_id_foreign'],
            ['table' => 'kardex', 'name' => 'kardex_sale_note_id_foreign'],
            ['table' => 'kardex', 'name' => 'kardex_purchase_settlement_id_foreign'],
            ['table' => 'kardex', 'name' => 'kardex_purchase_id_foreign'],
            ['table' => 'kardex', 'name' => 'kardex_item_id_foreign'],
            ['table' => 'kardex', 'name' => 'kardex_document_id_foreign'],
            ['table' => 'invoices', 'name' => 'invoices_operation_type_id_foreign'],
            ['table' => 'invoices', 'name' => 'invoices_document_id_foreign'],
            ['table' => 'document_transports', 'name' => 'document_transports_identity_document_type_id_foreign'],
            ['table' => 'document_transports', 'name' => 'document_transports_document_id_foreign'],
            ['table' => 'document_payments', 'name' => 'document_payments_payment_method_type_id_foreign'],
            ['table' => 'document_payments', 'name' => 'document_payments_document_id_foreign'],
            ['table' => 'document_payments', 'name' => 'document_payments_card_brand_id_foreign'],
            ['table' => 'document_items', 'name' => 'document_items_warehouse_id_foreign'],
            ['table' => 'document_items', 'name' => 'document_items_price_type_id_foreign'],
            ['table' => 'document_items', 'name' => 'document_items_item_id_foreign'],
            ['table' => 'document_items', 'name' => 'document_items_document_id_foreign'],
            ['table' => 'document_items', 'name' => 'document_items_affectation_igv_type_id_foreign'],
            ['table' => 'document_hotels', 'name' => 'document_hotels_identity_document_type_id_foreign'],
            ['table' => 'document_hotels', 'name' => 'document_hotels_document_id_foreign'],
            ['table' => 'document_fee', 'name' => 'document_fee_document_id_foreign'],
            ['table' => 'document_fee', 'name' => 'document_fee_currency_type_id_foreign'],
            ['table' => 'documents', 'name' => 'documents_user_id_foreign'],
            ['table' => 'documents', 'name' => 'documents_technical_service_id_foreign'],
            ['table' => 'documents', 'name' => 'documents_state_type_id_foreign'],
            ['table' => 'documents', 'name' => 'documents_soap_type_id_foreign'],
            ['table' => 'documents', 'name' => 'documents_seller_id_foreign'],
            ['table' => 'documents', 'name' => 'documents_sale_note_id_foreign'],
            ['table' => 'documents', 'name' => 'documents_quotation_id_foreign'],
            ['table' => 'documents', 'name' => 'documents_payment_method_type_id_foreign'],
            ['table' => 'documents', 'name' => 'documents_payment_condition_id_foreign'],
            ['table' => 'documents', 'name' => 'documents_order_note_id_foreign'],
            ['table' => 'documents', 'name' => 'documents_hotel_rent_id_foreign'],
            ['table' => 'documents', 'name' => 'documents_group_id_foreign'],
            ['table' => 'documents', 'name' => 'documents_establishment_id_foreign'],
            ['table' => 'documents', 'name' => 'documents_document_type_id_foreign'],
            ['table' => 'documents', 'name' => 'documents_dispatch_id_foreign'],
            ['table' => 'documents', 'name' => 'documents_customer_id_foreign'],
            ['table' => 'documents', 'name' => 'documents_currency_type_id_foreign'],
            ['table' => 'documents', 'name' => 'documents_agent_id_foreign'],
            ['table' => 'dispatch_items', 'name' => 'dispatch_items_item_id_foreign'],
            ['table' => 'dispatch_items', 'name' => 'dispatch_items_dispatch_id_foreign'],
            ['table' => 'dispatches', 'name' => 'dispatches_user_id_foreign'],
            ['table' => 'dispatches', 'name' => 'dispatches_unit_type_id_foreign'],
            ['table' => 'dispatches', 'name' => 'dispatches_transport_mode_type_id_foreign'],
            ['table' => 'dispatches', 'name' => 'dispatches_transport_id_foreign'],
            ['table' => 'dispatches', 'name' => 'dispatches_transfer_reason_type_id_foreign'],
            ['table' => 'dispatches', 'name' => 'dispatches_state_type_id_foreign'],
            ['table' => 'dispatches', 'name' => 'dispatches_soap_type_id_foreign'],
            ['table' => 'dispatches', 'name' => 'dispatches_sender_id_foreign'],
            ['table' => 'dispatches', 'name' => 'dispatches_sender_address_id_foreign'],
            ['table' => 'dispatches', 'name' => 'dispatches_receiver_id_foreign'],
            ['table' => 'dispatches', 'name' => 'dispatches_receiver_address_id_foreign'],
            ['table' => 'dispatches', 'name' => 'dispatches_establishment_id_foreign'],
            ['table' => 'dispatches', 'name' => 'dispatches_driver_id_foreign'],
            ['table' => 'dispatches', 'name' => 'dispatches_document_type_id_foreign'],
            ['table' => 'dispatches', 'name' => 'dispatches_document_id_foreign'],
            ['table' => 'dispatches', 'name' => 'dispatches_dispatcher_id_foreign'],
            ['table' => 'dispatches', 'name' => 'dispatches_customer_id_foreign'],
            ['table' => 'dispatches', 'name' => 'dispatches_buyer_id_foreign'],
            ['table' => 'cash_document_payments', 'name' => 'cash_document_payments_sale_note_payment_id_foreign'],
            ['table' => 'cash_document_payments', 'name' => 'cash_document_payments_document_payment_id_foreign'],
            ['table' => 'cash_document_payments', 'name' => 'cash_document_payments_cash_id_foreign'],
            ['table' => 'cash_document_payments', 'name' => 'cash_document_payments_cash_document_id_foreign'],
            ['table' => 'cash_document_payments', 'name' => 'cash_document_payments_cash_document_credit_id_foreign'],
            ['table' => 'cash_document_credits', 'name' => 'cash_document_credits_sale_note_id_foreign'],
            ['table' => 'cash_document_credits', 'name' => 'cash_document_credits_document_id_foreign'],
            ['table' => 'cash_document_credits', 'name' => 'cash_document_credits_cash_id_foreign'],
            ['table' => 'cash_documents', 'name' => 'cash_documents_technical_service_id_foreign'],
            ['table' => 'cash_documents', 'name' => 'cash_documents_sale_note_id_foreign'],
            ['table' => 'cash_documents', 'name' => 'cash_documents_quotation_id_foreign'],
            ['table' => 'cash_documents', 'name' => 'cash_documents_purchase_id_foreign'],
            ['table' => 'cash_documents', 'name' => 'cash_documents_expense_payment_id_foreign'],
            ['table' => 'cash_documents', 'name' => 'cash_documents_document_id_foreign'],
            ['table' => 'cash_documents', 'name' => 'cash_documents_cash_id_foreign'],
            ['table' => 'hotel_rent_item_payments', 'name' => 'hotel_rent_item_payments_payment_method_type_id_foreign'],
            ['table' => 'hotel_rent_item_payments', 'name' => 'hotel_rent_item_payments_hotel_rent_item_id_foreign'],
            ['table' => 'hotel_rent_items', 'name' => 'hotel_rent_items_item_id_foreign'],
            ['table' => 'hotel_rent_items', 'name' => 'hotel_rent_items_hotel_rent_order_id_foreign'],
            ['table' => 'hotel_rent_items', 'name' => 'hotel_rent_items_hotel_rent_id_foreign'],
            ['table' => 'sale_note_payments', 'name' => 'sale_note_payments_sale_note_id_foreign'],
            ['table' => 'sale_note_payments', 'name' => 'sale_note_payments_payment_method_type_id_foreign'],
            ['table' => 'sale_note_payments', 'name' => 'sale_note_payments_card_brand_id_foreign'],
            ['table' => 'sale_note_items', 'name' => 'sale_note_items_warehouse_id_foreign'],
            ['table' => 'sale_note_items', 'name' => 'sale_note_items_sale_note_id_foreign'],
            ['table' => 'sale_note_items', 'name' => 'sale_note_items_price_type_id_foreign'],
            ['table' => 'sale_note_items', 'name' => 'sale_note_items_item_id_foreign'],
            ['table' => 'sale_note_items', 'name' => 'sale_note_items_inventory_kardex_id_foreign'],
            ['table' => 'sale_note_items', 'name' => 'sale_note_items_affectation_igv_type_id_foreign'],
            ['table' => 'sale_note_fees', 'name' => 'sale_note_fees_sale_note_id_foreign'],
            ['table' => 'sale_note_fees', 'name' => 'sale_note_fees_currency_type_id_foreign'],
            ['table' => 'purchase_supplies', 'name' => 'purchase_supplies_supply_id_foreign'],
            ['table' => 'purchase_supplies', 'name' => 'purchase_supplies_purchase_id_foreign'],
            ['table' => 'purchase_payments', 'name' => 'purchase_payments_purchase_id_foreign'],
            ['table' => 'purchase_payments', 'name' => 'purchase_payments_payment_method_type_id_foreign'],
            ['table' => 'purchase_payments', 'name' => 'purchase_payments_card_brand_id_foreign'],
            ['table' => 'purchase_items', 'name' => 'purchase_items_warehouse_id_foreign'],
            ['table' => 'purchase_items', 'name' => 'purchase_items_purchase_id_foreign'],
            ['table' => 'purchase_items', 'name' => 'purchase_items_price_type_id_foreign'],
            ['table' => 'purchase_items', 'name' => 'purchase_items_item_id_foreign'],
            ['table' => 'purchase_items', 'name' => 'purchase_items_affectation_igv_type_id_foreign'],
            ['table' => 'purchase_fee', 'name' => 'purchase_fee_purchase_id_foreign'],
            ['table' => 'purchase_fee', 'name' => 'purchase_fee_payment_method_type_id_foreign'],
            ['table' => 'purchase_fee', 'name' => 'purchase_fee_currency_type_id_foreign'],
            ['table' => 'hotel_rent_orders', 'name' => 'hotel_rent_orders_sale_note_id_foreign'],
            ['table' => 'hotel_rent_orders', 'name' => 'hotel_rent_orders_hotel_rent_id_foreign'],
            ['table' => 'hotel_rent_orders', 'name' => 'hotel_rent_orders_establishment_id_foreign'],
            ['table' => 'dispatch_sale_notes', 'name' => 'dispatch_sale_notes_sale_note_id_foreign'],
            ['table' => 'contract_payments', 'name' => 'contract_payments_payment_method_type_id_foreign'],
            ['table' => 'contract_payments', 'name' => 'contract_payments_contract_id_foreign'],
            ['table' => 'contract_payments', 'name' => 'contract_payments_card_brand_id_foreign'],
            ['table' => 'contract_items', 'name' => 'contract_items_price_type_id_foreign'],
            ['table' => 'contract_items', 'name' => 'contract_items_item_id_foreign'],
            ['table' => 'contract_items', 'name' => 'contract_items_contract_id_foreign'],
            ['table' => 'contract_items', 'name' => 'contract_items_affectation_igv_type_id_foreign'],
            ['table' => 'transaction_queries', 'name' => 'transaction_queries_transaction_id_foreign'],
            ['table' => 'sale_notes', 'name' => 'sale_notes_user_id_foreign'],
            ['table' => 'sale_notes', 'name' => 'sale_notes_technical_service_id_foreign'],
            ['table' => 'sale_notes', 'name' => 'sale_notes_state_type_id_foreign'],
            ['table' => 'sale_notes', 'name' => 'sale_notes_soap_type_id_foreign'],
            ['table' => 'sale_notes', 'name' => 'sale_notes_quotation_id_foreign'],
            ['table' => 'sale_notes', 'name' => 'sale_notes_payment_method_type_id_foreign'],
            ['table' => 'sale_notes', 'name' => 'sale_notes_payment_condition_id_foreign'],
            ['table' => 'sale_notes', 'name' => 'sale_notes_order_note_id_foreign'],
            ['table' => 'sale_notes', 'name' => 'sale_notes_order_id_foreign'],
            ['table' => 'sale_notes', 'name' => 'sale_notes_hotel_rent_id_foreign'],
            ['table' => 'sale_notes', 'name' => 'sale_notes_establishment_id_foreign'],
            ['table' => 'sale_notes', 'name' => 'sale_notes_customer_id_foreign'],
            ['table' => 'sale_notes', 'name' => 'sale_notes_currency_type_id_foreign'],
            ['table' => 'sale_notes', 'name' => 'sale_notes_agent_id_foreign'],
            ['table' => 'quotation_payments', 'name' => 'quotation_payments_quotation_id_foreign'],
            ['table' => 'quotation_payments', 'name' => 'quotation_payments_payment_method_type_id_foreign'],
            ['table' => 'quotation_payments', 'name' => 'quotation_payments_card_brand_id_foreign'],
            ['table' => 'quotation_items', 'name' => 'quotation_items_quotation_id_foreign'],
            ['table' => 'quotation_items', 'name' => 'quotation_items_price_type_id_foreign'],
            ['table' => 'quotation_items', 'name' => 'quotation_items_item_id_foreign'],
            ['table' => 'quotation_items', 'name' => 'quotation_items_affectation_igv_type_id_foreign'],
            ['table' => 'purchases', 'name' => 'purchases_user_id_foreign'],
            ['table' => 'purchases', 'name' => 'purchases_supplier_id_foreign'],
            ['table' => 'purchases', 'name' => 'purchases_state_type_id_foreign'],
            ['table' => 'purchases', 'name' => 'purchases_soap_type_id_foreign'],
            ['table' => 'purchases', 'name' => 'purchases_purchase_order_id_foreign'],
            ['table' => 'purchases', 'name' => 'purchases_payment_condition_id_foreign'],
            ['table' => 'purchases', 'name' => 'purchases_group_id_foreign'],
            ['table' => 'purchases', 'name' => 'purchases_establishment_id_foreign'],
            ['table' => 'purchases', 'name' => 'purchases_document_type_id_foreign'],
            ['table' => 'purchases', 'name' => 'purchases_customer_id_foreign'],
            ['table' => 'purchases', 'name' => 'purchases_currency_type_id_foreign'],
            ['table' => 'purchase_order_items', 'name' => 'purchase_order_items_purchase_order_id_foreign'],
            ['table' => 'purchase_order_items', 'name' => 'purchase_order_items_price_type_id_foreign'],
            ['table' => 'purchase_order_items', 'name' => 'purchase_order_items_item_id_foreign'],
            ['table' => 'purchase_order_items', 'name' => 'purchase_order_items_affectation_igv_type_id_foreign'],
            ['table' => 'item_unit_type_prices', 'name' => 'item_unit_type_prices_price_label_id_foreign'],
            ['table' => 'item_unit_type_prices', 'name' => 'item_unit_type_prices_item_unit_type_id_foreign'],
            ['table' => 'inventory_transfer_items', 'name' => 'inventory_transfer_items_item_lots_group_id_foreign'],
            ['table' => 'inventory_transfer_items', 'name' => 'inventory_transfer_items_item_lot_id_foreign'],
            ['table' => 'inventory_transfer_items', 'name' => 'inventory_transfer_items_inventory_transfer_id_foreign'],
            ['table' => 'hotel_room_rates', 'name' => 'hotel_room_rates_hotel_room_id_foreign'],
            ['table' => 'hotel_room_rates', 'name' => 'hotel_room_rates_hotel_rate_id_foreign'],
            ['table' => 'contracts', 'name' => 'contracts_user_id_foreign'],
            ['table' => 'contracts', 'name' => 'contracts_state_type_id_foreign'],
            ['table' => 'contracts', 'name' => 'contracts_soap_type_id_foreign'],
            ['table' => 'contracts', 'name' => 'contracts_quotation_id_foreign'],
            ['table' => 'contracts', 'name' => 'contracts_payment_method_type_id_foreign'],
            ['table' => 'contracts', 'name' => 'contracts_establishment_id_foreign'],
            ['table' => 'contracts', 'name' => 'contracts_customer_id_foreign'],
            ['table' => 'contracts', 'name' => 'contracts_currency_type_id_foreign'],
            ['table' => 'weighted_average_costs', 'name' => 'weighted_average_costs_item_id_foreign'],
            ['table' => 'user_default_document_types', 'name' => 'user_default_document_types_user_id_foreign'],
            ['table' => 'user_default_document_types', 'name' => 'user_default_document_types_series_id_foreign'],
            ['table' => 'user_default_document_types', 'name' => 'user_default_document_types_document_type_id_foreign'],
            ['table' => 'transactions', 'name' => 'transactions_transaction_state_id_foreign'],
            ['table' => 'transactions', 'name' => 'transactions_soap_type_id_foreign'],
            ['table' => 'transactions', 'name' => 'transactions_payment_link_id_foreign'],
            ['table' => 'technical_service_payments', 'name' => 'technical_service_payments_technical_service_id_foreign'],
            ['table' => 'technical_service_payments', 'name' => 'technical_service_payments_payment_method_type_id_foreign'],
            ['table' => 'series_configurations', 'name' => 'series_configurations_series_id_foreign'],
            ['table' => 'series_configurations', 'name' => 'series_configurations_document_type_id_foreign'],
            ['table' => 'sale_opportunity_items', 'name' => 'sale_opportunity_items_sale_opportunity_id_foreign'],
            ['table' => 'sale_opportunity_items', 'name' => 'sale_opportunity_items_price_type_id_foreign'],
            ['table' => 'sale_opportunity_items', 'name' => 'sale_opportunity_items_item_id_foreign'],
            ['table' => 'sale_opportunity_items', 'name' => 'sale_opportunity_items_affectation_igv_type_id_foreign'],
            ['table' => 'sale_opportunity_files', 'name' => 'sale_opportunity_files_sale_opportunity_id_foreign'],
            ['table' => 'retention_documents', 'name' => 'retention_documents_retention_id_foreign'],
            ['table' => 'retention_documents', 'name' => 'retention_documents_document_type_id_foreign'],
            ['table' => 'retention_documents', 'name' => 'retention_documents_currency_type_id_foreign'],
            ['table' => 'restaurant_stock_products', 'name' => 'restaurant_stock_products_item_id_foreign'],
            ['table' => 'restaurant_item_supplies', 'name' => 'restaurant_item_supplies_supply_id_foreign'],
            ['table' => 'restaurant_item_supplies', 'name' => 'restaurant_item_supplies_item_id_foreign'],
            ['table' => 'quotations', 'name' => 'quotations_user_id_foreign'],
            ['table' => 'quotations', 'name' => 'quotations_state_type_id_foreign'],
            ['table' => 'quotations', 'name' => 'quotations_soap_type_id_foreign'],
            ['table' => 'quotations', 'name' => 'quotations_sale_opportunity_id_foreign'],
            ['table' => 'quotations', 'name' => 'quotations_payment_method_type_id_foreign'],
            ['table' => 'quotations', 'name' => 'quotations_establishment_id_foreign'],
            ['table' => 'quotations', 'name' => 'quotations_customer_id_foreign'],
            ['table' => 'quotations', 'name' => 'quotations_currency_type_id_foreign'],
            ['table' => 'purchase_settlement_payments', 'name' => 'purchase_settlement_payments_purchase_settlement_id_foreign'],
            ['table' => 'purchase_settlement_payments', 'name' => 'purchase_settlement_payments_payment_method_type_id_foreign'],
            ['table' => 'purchase_settlement_items', 'name' => 'purchase_settlement_items_purchase_settlement_id_foreign'],
            ['table' => 'purchase_settlement_items', 'name' => 'purchase_settlement_items_price_type_id_foreign'],
            ['table' => 'purchase_settlement_items', 'name' => 'purchase_settlement_items_item_id_foreign'],
            ['table' => 'purchase_settlement_items', 'name' => 'purchase_settlement_items_affectation_igv_type_id_foreign'],
            ['table' => 'purchase_settlement_items', 'name' => 'p_s_i_income_tax_affectation_igv_type_id_fk'],
            ['table' => 'purchase_quotation_items', 'name' => 'purchase_quotation_items_purchase_quotation_id_foreign'],
            ['table' => 'purchase_quotation_items', 'name' => 'purchase_quotation_items_item_id_foreign'],
            ['table' => 'purchase_orders', 'name' => 'purchase_orders_user_id_foreign'],
            ['table' => 'purchase_orders', 'name' => 'purchase_orders_supplier_id_foreign'],
            ['table' => 'purchase_orders', 'name' => 'purchase_orders_state_type_id_foreign'],
            ['table' => 'purchase_orders', 'name' => 'purchase_orders_soap_type_id_foreign'],
            ['table' => 'purchase_orders', 'name' => 'purchase_orders_sale_opportunity_id_foreign'],
            ['table' => 'purchase_orders', 'name' => 'purchase_orders_purchase_quotation_id_foreign'],
            ['table' => 'purchase_orders', 'name' => 'purchase_orders_payment_method_type_id_foreign'],
            ['table' => 'purchase_orders', 'name' => 'purchase_orders_establishment_id_foreign'],
            ['table' => 'purchase_orders', 'name' => 'purchase_orders_currency_type_id_foreign'],
            ['table' => 'promotions', 'name' => 'promotions_item_id_foreign'],
            ['table' => 'promotions', 'name' => 'promotions_category_id_foreign'],
            ['table' => 'perception_documents', 'name' => 'perception_documents_document_type_id_foreign'],
            ['table' => 'perception_documents', 'name' => 'perception_documents_currency_type_id_foreign'],
            ['table' => 'perception_documents', 'name' => 'perception_details_perception_id_foreign'],
            ['table' => 'payment_link_payments', 'name' => 'payment_link_payments_payment_link_id_foreign'],
            ['table' => 'order_note_items', 'name' => 'order_note_items_warehouse_id_foreign'],
            ['table' => 'order_note_items', 'name' => 'order_note_items_price_type_id_foreign'],
            ['table' => 'order_note_items', 'name' => 'order_note_items_order_note_id_foreign'],
            ['table' => 'order_note_items', 'name' => 'order_note_items_item_id_foreign'],
            ['table' => 'order_note_items', 'name' => 'order_note_items_affectation_igv_type_id_foreign'],
            ['table' => 'order_form_items', 'name' => 'order_form_items_order_form_id_foreign'],
            ['table' => 'order_form_items', 'name' => 'order_form_items_item_id_foreign'],
            ['table' => 'items_rating', 'name' => 'items_rating_user_id_foreign'],
            ['table' => 'items_rating', 'name' => 'items_rating_item_id_foreign'],
            ['table' => 'item_warehouse_prices', 'name' => 'item_warehouse_prices_warehouse_id_foreign'],
            ['table' => 'item_warehouse_prices', 'name' => 'item_warehouse_prices_item_id_foreign'],
            ['table' => 'item_warehouse', 'name' => 'item_warehouse_warehouse_id_foreign'],
            ['table' => 'item_warehouse', 'name' => 'item_warehouse_item_id_foreign'],
            ['table' => 'item_unit_types', 'name' => 'item_unit_types_unit_type_id_foreign'],
            ['table' => 'item_unit_types', 'name' => 'item_unit_types_item_id_foreign'],
            ['table' => 'item_tags', 'name' => 'item_tags_tag_id_foreign'],
            ['table' => 'item_tags', 'name' => 'item_tags_item_id_foreign'],
            ['table' => 'item_supplies', 'name' => 'item_supplies_item_id_foreign'],
            ['table' => 'item_supplies', 'name' => 'item_supplies_individual_item_id_foreign'],
            ['table' => 'item_sets', 'name' => 'item_sets_item_id_foreign'],
            ['table' => 'item_sets', 'name' => 'item_sets_individual_item_id_foreign'],
            ['table' => 'item_modifier_group', 'name' => 'item_modifier_group_modifier_group_id_foreign'],
            ['table' => 'item_modifier_group', 'name' => 'item_modifier_group_item_id_foreign'],
            ['table' => 'item_lots_group', 'name' => 'item_lots_group_item_id_foreign'],
            ['table' => 'item_lots', 'name' => 'item_lots_warehouse_id_foreign'],
            ['table' => 'item_lots', 'name' => 'item_lots_item_id_foreign'],
            ['table' => 'item_images', 'name' => 'item_images_item_id_foreign'],
            ['table' => 'inventory_kardex', 'name' => 'inventory_kardex_warehouse_id_foreign'],
            ['table' => 'inventory_kardex', 'name' => 'inventory_kardex_item_id_foreign'],
            ['table' => 'inventories', 'name' => 'inventories_warehouse_id_foreign'],
            ['table' => 'inventories', 'name' => 'inventories_item_id_foreign'],
            ['table' => 'inventories', 'name' => 'inventories_inventory_transaction_id_foreign'],
            ['table' => 'inventories', 'name' => 'inventories_inventories_transfer_id_foreign'],
            ['table' => 'income_payments', 'name' => 'income_payments_payment_method_type_id_foreign'],
            ['table' => 'income_payments', 'name' => 'income_payments_income_id_foreign'],
            ['table' => 'income_payments', 'name' => 'income_payments_card_brand_id_foreign'],
            ['table' => 'income_items', 'name' => 'income_items_income_id_foreign'],
            ['table' => 'hotel_rooms', 'name' => 'hotel_rooms_item_id_foreign'],
            ['table' => 'hotel_rooms', 'name' => 'hotel_rooms_hotel_floor_id_foreign'],
            ['table' => 'hotel_rooms', 'name' => 'hotel_rooms_hotel_category_id_foreign'],
            ['table' => 'hotel_rooms', 'name' => 'hotel_rooms_establishment_id_foreign'],
            ['table' => 'guide_items', 'name' => 'guide_items_item_id_foreign'],
            ['table' => 'guide_items', 'name' => 'guide_items_guide_id_foreign'],
            ['table' => 'fixed_asset_purchase_items', 'name' => 'fixed_asset_purchase_items_price_type_id_foreign'],
            ['table' => 'fixed_asset_purchase_items', 'name' => 'fixed_asset_purchase_items_fixed_asset_purchase_id_foreign'],
            ['table' => 'fixed_asset_purchase_items', 'name' => 'fixed_asset_purchase_items_fixed_asset_item_id_foreign'],
            ['table' => 'fixed_asset_purchase_items', 'name' => 'fixed_asset_purchase_items_affectation_igv_type_id_foreign'],
            ['table' => 'expense_payments', 'name' => 'expense_payments_expense_method_type_id_foreign'],
            ['table' => 'expense_payments', 'name' => 'expense_payments_expense_id_foreign'],
            ['table' => 'expense_payments', 'name' => 'expense_payments_card_brand_id_foreign'],
            ['table' => 'expense_items', 'name' => 'expense_items_expense_id_foreign'],
            ['table' => 'devolution_items', 'name' => 'devolution_items_item_id_foreign'],
            ['table' => 'devolution_items', 'name' => 'devolution_items_devolution_id_foreign'],
            ['table' => 'cash_transactions', 'name' => 'cash_transactions_payment_method_type_id_foreign'],
            ['table' => 'cash_transactions', 'name' => 'cash_transactions_cash_id_foreign'],
            ['table' => 'voided', 'name' => 'voided_user_id_foreign'],
            ['table' => 'voided', 'name' => 'voided_state_type_id_foreign'],
            ['table' => 'voided', 'name' => 'voided_soap_type_id_foreign'],
            ['table' => 'user_commissions', 'name' => 'user_commissions_user_id_foreign'],
            ['table' => 'technical_services', 'name' => 'technical_services_user_id_foreign'],
            ['table' => 'technical_services', 'name' => 'technical_services_soap_type_id_foreign'],
            ['table' => 'technical_services', 'name' => 'technical_services_customer_id_foreign'],
            ['table' => 'tag_template_fields', 'name' => 'tag_template_fields_tag_template_id_foreign'],
            ['table' => 'system_activity_logs', 'name' => 'system_activity_logs_user_id_foreign'],
            ['table' => 'system_activity_logs', 'name' => 'system_activity_logs_system_activity_log_type_id_foreign'],
            ['table' => 'summaries', 'name' => 'summaries_user_id_foreign'],
            ['table' => 'summaries', 'name' => 'summaries_state_type_id_foreign'],
            ['table' => 'summaries', 'name' => 'summaries_soap_type_id_foreign'],
            ['table' => 'series', 'name' => 'series_series_device_group_id_foreign'],
            ['table' => 'series', 'name' => 'series_establishment_id_foreign'],
            ['table' => 'series', 'name' => 'series_document_type_id_foreign'],
            ['table' => 'sale_opportunities', 'name' => 'sale_opportunities_user_id_foreign'],
            ['table' => 'sale_opportunities', 'name' => 'sale_opportunities_state_type_id_foreign'],
            ['table' => 'sale_opportunities', 'name' => 'sale_opportunities_soap_type_id_foreign'],
            ['table' => 'sale_opportunities', 'name' => 'sale_opportunities_establishment_id_foreign'],
            ['table' => 'sale_opportunities', 'name' => 'sale_opportunities_customer_id_foreign'],
            ['table' => 'sale_opportunities', 'name' => 'sale_opportunities_currency_type_id_foreign'],
            ['table' => 'retentions', 'name' => 'retentions_user_id_foreign'],
            ['table' => 'retentions', 'name' => 'retentions_supplier_id_foreign'],
            ['table' => 'retentions', 'name' => 'retentions_state_type_id_foreign'],
            ['table' => 'retentions', 'name' => 'retentions_soap_type_id_foreign'],
            ['table' => 'retentions', 'name' => 'retentions_retention_type_id_foreign'],
            ['table' => 'retentions', 'name' => 'retentions_establishment_id_foreign'],
            ['table' => 'retentions', 'name' => 'retentions_document_type_id_foreign'],
            ['table' => 'retentions', 'name' => 'retentions_currency_type_id_foreign'],
            ['table' => 'purchase_settlements', 'name' => 'purchase_settlements_user_id_foreign'],
            ['table' => 'purchase_settlements', 'name' => 'purchase_settlements_supplier_id_foreign'],
            ['table' => 'purchase_settlements', 'name' => 'purchase_settlements_state_type_id_foreign'],
            ['table' => 'purchase_settlements', 'name' => 'purchase_settlements_soap_type_id_foreign'],
            ['table' => 'purchase_settlements', 'name' => 'purchase_settlements_payment_method_type_id_foreign'],
            ['table' => 'purchase_settlements', 'name' => 'purchase_settlements_operation_type_id_foreign'],
            ['table' => 'purchase_settlements', 'name' => 'purchase_settlements_establishment_id_foreign'],
            ['table' => 'purchase_settlements', 'name' => 'purchase_settlements_document_type_id_foreign'],
            ['table' => 'purchase_settlements', 'name' => 'purchase_settlements_currency_type_id_foreign'],
            ['table' => 'purchase_quotations', 'name' => 'purchase_quotations_user_id_foreign'],
            ['table' => 'purchase_quotations', 'name' => 'purchase_quotations_state_type_id_foreign'],
            ['table' => 'purchase_quotations', 'name' => 'purchase_quotations_soap_type_id_foreign'],
            ['table' => 'purchase_quotations', 'name' => 'purchase_quotations_establishment_id_foreign'],
            ['table' => 'perceptions', 'name' => 'perceptions_user_id_foreign'],
            ['table' => 'perceptions', 'name' => 'perceptions_state_type_id_foreign'],
            ['table' => 'perceptions', 'name' => 'perceptions_soap_type_id_foreign'],
            ['table' => 'perceptions', 'name' => 'perceptions_establishment_id_foreign'],
            ['table' => 'perceptions', 'name' => 'perceptions_document_type_id_foreign'],
            ['table' => 'perceptions', 'name' => 'perceptions_customer_id_foreign'],
            ['table' => 'perceptions', 'name' => 'perceptions_currency_type_id_foreign'],
            ['table' => 'pending_account_commissions', 'name' => 'pending_account_commissions_seller_id_foreign'],
            ['table' => 'payment_links', 'name' => 'payment_links_user_id_foreign'],
            ['table' => 'payment_links', 'name' => 'payment_links_soap_type_id_foreign'],
            ['table' => 'payment_links', 'name' => 'payment_links_person_id_foreign'],
            ['table' => 'payment_links', 'name' => 'payment_links_payment_link_type_id_foreign'],
            ['table' => 'order_notes', 'name' => 'order_notes_user_id_foreign'],
            ['table' => 'order_notes', 'name' => 'order_notes_state_type_id_foreign'],
            ['table' => 'order_notes', 'name' => 'order_notes_soap_type_id_foreign'],
            ['table' => 'order_notes', 'name' => 'order_notes_payment_method_type_id_foreign'],
            ['table' => 'order_notes', 'name' => 'order_notes_establishment_id_foreign'],
            ['table' => 'order_notes', 'name' => 'order_notes_customer_id_foreign'],
            ['table' => 'order_notes', 'name' => 'order_notes_currency_type_id_foreign'],
            ['table' => 'order_forms', 'name' => 'order_forms_user_id_foreign'],
            ['table' => 'order_forms', 'name' => 'order_forms_unit_type_id_foreign'],
            ['table' => 'order_forms', 'name' => 'order_forms_transport_mode_type_id_foreign'],
            ['table' => 'order_forms', 'name' => 'order_forms_transfer_reason_type_id_foreign'],
            ['table' => 'order_forms', 'name' => 'order_forms_state_type_id_foreign'],
            ['table' => 'order_forms', 'name' => 'order_forms_soap_type_id_foreign'],
            ['table' => 'order_forms', 'name' => 'order_forms_establishment_id_foreign'],
            ['table' => 'order_forms', 'name' => 'order_forms_driver_id_foreign'],
            ['table' => 'order_forms', 'name' => 'order_forms_dispatcher_id_foreign'],
            ['table' => 'order_forms', 'name' => 'order_forms_customer_id_foreign'],
            ['table' => 'items', 'name' => 'items_web_platform_id_foreign'],
            ['table' => 'items', 'name' => 'items_warehouse_id_foreign'],
            ['table' => 'items', 'name' => 'items_unit_type_id_foreign'],
            ['table' => 'items', 'name' => 'items_sale_affectation_igv_type_id_foreign'],
            ['table' => 'items', 'name' => 'items_purchase_affectation_igv_type_id_foreign'],
            ['table' => 'items', 'name' => 'items_preparation_area_id_foreign'],
            ['table' => 'items', 'name' => 'items_item_type_id_foreign'],
            ['table' => 'items', 'name' => 'items_currency_type_id_foreign'],
            ['table' => 'items', 'name' => 'items_category_id_foreign'],
            ['table' => 'items', 'name' => 'items_brand_id_foreign'],
            ['table' => 'items', 'name' => 'items_account_id_foreign'],
            ['table' => 'income', 'name' => 'income_user_id_foreign'],
            ['table' => 'income', 'name' => 'income_state_type_id_foreign'],
            ['table' => 'income', 'name' => 'income_soap_type_id_foreign'],
            ['table' => 'income', 'name' => 'income_income_type_id_foreign'],
            ['table' => 'income', 'name' => 'income_income_reason_id_foreign'],
            ['table' => 'income', 'name' => 'income_establishment_id_foreign'],
            ['table' => 'income', 'name' => 'income_currency_type_id_foreign'],
            ['table' => 'hotel_rents', 'name' => 'hotel_rents_hotel_rate_id_foreign'],
            ['table' => 'hotel_rents', 'name' => 'hotel_rents_establishment_id_foreign'],
            ['table' => 'guides', 'name' => 'guides_warehouse_id_foreign'],
            ['table' => 'guides', 'name' => 'guides_user_id_foreign'],
            ['table' => 'guides', 'name' => 'guides_soap_type_id_foreign'],
            ['table' => 'guides', 'name' => 'guides_inventory_transaction_id_foreign'],
            ['table' => 'guides', 'name' => 'guides_document_type_id_foreign'],
            ['table' => 'global_payments', 'name' => 'global_payments_user_id_foreign'],
            ['table' => 'global_payments', 'name' => 'global_payments_soap_type_id_foreign'],
            ['table' => 'fixed_asset_purchases', 'name' => 'fixed_asset_purchases_user_id_foreign'],
            ['table' => 'fixed_asset_purchases', 'name' => 'fixed_asset_purchases_supplier_id_foreign'],
            ['table' => 'fixed_asset_purchases', 'name' => 'fixed_asset_purchases_state_type_id_foreign'],
            ['table' => 'fixed_asset_purchases', 'name' => 'fixed_asset_purchases_soap_type_id_foreign'],
            ['table' => 'fixed_asset_purchases', 'name' => 'fixed_asset_purchases_group_id_foreign'],
            ['table' => 'fixed_asset_purchases', 'name' => 'fixed_asset_purchases_establishment_id_foreign'],
            ['table' => 'fixed_asset_purchases', 'name' => 'fixed_asset_purchases_document_type_id_foreign'],
            ['table' => 'fixed_asset_purchases', 'name' => 'fixed_asset_purchases_customer_id_foreign'],
            ['table' => 'fixed_asset_purchases', 'name' => 'fixed_asset_purchases_currency_type_id_foreign'],
            ['table' => 'expenses', 'name' => 'expenses_user_id_foreign'],
            ['table' => 'expenses', 'name' => 'expenses_supplier_id_foreign'],
            ['table' => 'expenses', 'name' => 'expenses_state_type_id_foreign'],
            ['table' => 'expenses', 'name' => 'expenses_soap_type_id_foreign'],
            ['table' => 'expenses', 'name' => 'expenses_expense_type_id_foreign'],
            ['table' => 'expenses', 'name' => 'expenses_expense_reason_id_foreign'],
            ['table' => 'expenses', 'name' => 'expenses_establishment_id_foreign'],
            ['table' => 'expenses', 'name' => 'expenses_currency_type_id_foreign'],
            ['table' => 'ejb_report_configurations', 'name' => 'ejb_report_configurations_document_type_id_foreign'],
            ['table' => 'ejb_report_configurations', 'name' => 'ejb_report_configurations_bank_account_usd_id_foreign'],
            ['table' => 'ejb_report_configurations', 'name' => 'ejb_report_configurations_bank_account_pen_id_foreign'],
            ['table' => 'download_tray', 'name' => 'download_tray_user_id_foreign'],
            ['table' => 'documentary_files', 'name' => 'documentary_files_user_id_foreign'],
            ['table' => 'documentary_files', 'name' => 'documentary_files_person_id_foreign'],
            ['table' => 'documentary_files', 'name' => 'documentary_files_documentary_process_id_foreign'],
            ['table' => 'devolutions', 'name' => 'devolutions_user_id_foreign'],
            ['table' => 'devolutions', 'name' => 'devolutions_state_type_id_foreign'],
            ['table' => 'devolutions', 'name' => 'devolutions_soap_type_id_foreign'],
            ['table' => 'devolutions', 'name' => 'devolutions_establishment_id_foreign'],
            ['table' => 'devolutions', 'name' => 'devolutions_devolution_reason_id_foreign'],
            ['table' => 'columns_to_reports', 'name' => 'columns_to_reports_user_id_foreign'],
            ['table' => 'claims', 'name' => 'claims_status_claim_id_foreign'],
            ['table' => 'claims', 'name' => 'claims_district_id_foreign'],
            ['table' => 'claims', 'name' => 'claims_assigned_user_id_foreign'],
            ['table' => 'cash', 'name' => 'cash_user_id_foreign'],
            ['table' => 'authorized_discount_users', 'name' => 'authorized_discount_users_user_id_foreign'],
            ['table' => 'authorized_discount_users', 'name' => 'authorized_discount_users_seller_id_foreign'],
            ['table' => 'warehouses', 'name' => 'warehouses_establishment_id_foreign'],
            ['table' => 'users', 'name' => 'users_restaurant_role_id_foreign'],
            ['table' => 'users', 'name' => 'users_establishment_id_foreign'],
            ['table' => 'template_columns_config', 'name' => 'template_columns_config_establishment_id_foreign'],
            ['table' => 'tag_templates', 'name' => 'tag_templates_establishment_id_foreign'],
            ['table' => 'series_device_groups', 'name' => 'series_device_groups_establishment_id_foreign'],
            ['table' => 'plates', 'name' => 'plates_person_id_foreign'],
            ['table' => 'person_addresses', 'name' => 'person_addresses_province_id_foreign'],
            ['table' => 'person_addresses', 'name' => 'person_addresses_person_id_foreign'],
            ['table' => 'person_addresses', 'name' => 'person_addresses_district_id_foreign'],
            ['table' => 'person_addresses', 'name' => 'person_addresses_department_id_foreign'],
            ['table' => 'person_addresses', 'name' => 'person_addresses_country_id_foreign'],
            ['table' => 'person_address', 'name' => 'person_address_province_id_foreign'],
            ['table' => 'person_address', 'name' => 'person_address_person_id_foreign'],
            ['table' => 'person_address', 'name' => 'person_address_district_id_foreign'],
            ['table' => 'person_address', 'name' => 'person_address_department_id_foreign'],
            ['table' => 'hotel_rates', 'name' => 'hotel_rates_establishment_id_foreign'],
            ['table' => 'hotel_floors', 'name' => 'hotel_floors_establishment_id_foreign'],
            ['table' => 'hotel_categories', 'name' => 'hotel_categories_establishment_id_foreign'],
            ['table' => 'dispatch_addresses', 'name' => 'dispatch_addresses_person_id_foreign'],
            ['table' => 'discount_coupon_usages', 'name' => 'discount_coupon_usages_person_id_foreign'],
            ['table' => 'discount_coupon_usages', 'name' => 'discount_coupon_usages_order_id_foreign'],
            ['table' => 'discount_coupon_usages', 'name' => 'discount_coupon_usages_discount_coupon_id_foreign'],
            ['table' => 'bank_accounts', 'name' => 'bank_accounts_establishment_id_foreign'],
            ['table' => 'bank_accounts', 'name' => 'bank_accounts_currency_type_id_foreign'],
            ['table' => 'bank_accounts', 'name' => 'bank_accounts_bank_id_foreign'],
            ['table' => 'persons', 'name' => 'persons_province_id_foreign'],
            ['table' => 'persons', 'name' => 'persons_person_type_id_foreign'],
            ['table' => 'persons', 'name' => 'persons_nationality_id_foreign'],
            ['table' => 'persons', 'name' => 'persons_identity_document_type_id_foreign'],
            ['table' => 'persons', 'name' => 'persons_district_id_foreign'],
            ['table' => 'persons', 'name' => 'persons_department_id_foreign'],
            ['table' => 'persons', 'name' => 'persons_country_id_foreign'],
            ['table' => 'persons', 'name' => 'persons_address_type_id_foreign'],
            ['table' => 'establishments', 'name' => 'establishments_province_id_foreign'],
            ['table' => 'establishments', 'name' => 'establishments_district_id_foreign'],
            ['table' => 'establishments', 'name' => 'establishments_department_id_foreign'],
            ['table' => 'establishments', 'name' => 'establishments_country_id_foreign'],
            ['table' => 'districts', 'name' => 'districts_province_id_foreign'],
            ['table' => 'workers', 'name' => 'workers_identity_document_type_id_foreign'],
            ['table' => 'webhook_deliveries', 'name' => 'webhook_deliveries_webhook_subscription_id_foreign'],
            ['table' => 'tips', 'name' => 'tips_soap_type_id_foreign'],
            ['table' => 'supplies', 'name' => 'supplies_unit_type_id_foreign'],
            ['table' => 'provinces', 'name' => 'provinces_department_id_foreign'],
            ['table' => 'production', 'name' => 'production_soap_type_id_foreign'],
            ['table' => 'person_types', 'name' => 'person_types_price_label_id_foreign'],
            ['table' => 'packaging', 'name' => 'packaging_soap_type_id_foreign'],
            ['table' => 'origin_addresses', 'name' => 'origin_addresses_country_id_foreign'],
            ['table' => 'orders', 'name' => 'orders_status_order_id_foreign'],
            ['table' => 'orders', 'name' => 'orders_shipping_status_order_id_foreign'],
            ['table' => 'orders', 'name' => 'orders_payment_status_order_id_foreign'],
            ['table' => 'module_levels', 'name' => 'module_levels_module_id_foreign'],
            ['table' => 'mill', 'name' => 'mill_soap_type_id_foreign'],
            ['table' => 'item_movement_rel_extra', 'name' => 'item_movement_rel_extra_item_movement_id_foreign'],
            ['table' => 'inventories_transfer', 'name' => 'inventories_transfer_transfer_collect_id_foreign'],
            ['table' => 'inventories_transfer', 'name' => 'inventories_transfer_soap_type_id_foreign'],
            ['table' => 'inventories_transfer', 'name' => 'inventories_transfer_document_type_id_foreign'],
            ['table' => 'fixed_asset_items', 'name' => 'fixed_asset_items_unit_type_id_foreign'],
            ['table' => 'fixed_asset_items', 'name' => 'fixed_asset_items_purchase_affectation_igv_type_id_foreign'],
            ['table' => 'fixed_asset_items', 'name' => 'fixed_asset_items_item_type_id_foreign'],
            ['table' => 'fixed_asset_items', 'name' => 'fixed_asset_items_currency_type_id_foreign'],
            ['table' => 'drivers', 'name' => 'drivers_identity_document_type_id_foreign'],
            ['table' => 'dispatchers', 'name' => 'dispatchers_identity_document_type_id_foreign'],
            ['table' => 'delivery_zone_locations', 'name' => 'delivery_zone_locations_delivery_zone_id_foreign'],
            ['table' => 'companies', 'name' => 'companies_soap_type_id_foreign'],
            ['table' => 'companies', 'name' => 'companies_identity_document_type_id_foreign'],
            ['table' => 'client_errors', 'name' => 'client_errors_client_error_type_id_foreign'],
        ];

        foreach ($foreignKeys as $foreignKey) {
            DB::unprepared(
                sprintf(
                    'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                    $foreignKey['table'],
                    $foreignKey['name']
                )
            );
        }
    }
};
// ######### FIN CAMBIO NELSON #########
