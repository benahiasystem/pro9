<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - formats: varchar(255)
 * - cron: boolean
 * - stock: boolean
 * - limit_documents: bigint
 * - limit_users: bigint
 * - locked_emission: boolean
 * - restrict_sales_limit: boolean
 * - locked_create_establishments: boolean
 * - permission_to_edit_cpe: boolean
 * - customer_filter_by_seller: boolean
 * - set_address_by_establishment: boolean
 * - plan: json
 * - enable_whatsapp: boolean
 * - phone_whatsapp: varchar(255)
 * - apk_url: text
 * - visual: json
 * - sidebar_mode: varchar(255)
 * - product_default_image: varchar(255)
 * - decimal_quantity: smallint
 * - locked_users: boolean
 * - date_time_start: timestamp
 * - quantity_documents: integer
 * - quantity_sales_notes: integer
 * - locked_tenant: boolean
 * - compact_sidebar: boolean
 * - colums_grid_item: smallint
 * - options_pos: boolean
 * - auto_send_hka: boolean
 * - edit_name_product: boolean
 * - restrict_receipt_date: boolean
 * - shipping_time_days: integer
 * - affectation_igv_type_id: varchar(255)
 * - global_discount_type_id: varchar(255)
 * - include_igv: boolean
 * - global_igv_handling: boolean
 * - percentage_allowance_charge: numeric(30,12)
 * - igv_retention_percentage: numeric(30,12)
 * - active_allowance_charge: boolean
 * - active_warehouse_prices: boolean
 * - product_only_location: boolean
 * - terms_condition: text
 * - terms_condition_sale: text
 * - legend_footer_sale: varchar(255)
 * - cotizaction_finance: boolean
 * - quotation_allow_seller_generate_sale: boolean
 * - allow_edit_unit_price_to_seller: boolean
 * - header_image: varchar(255)
 * - destination_sale: boolean
 * - default_document_type_80: boolean
 * - search_item_by_barcode: boolean
 * - login: text
 * - navbar: varchar(15)
 * - finances: text
 * - smtp_encryption: text
 * - smtp_password: text
 * - smtp_user: text
 * - smtp_port: bigint
 * - smtp_host: text
 * - ticket_58: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 * - url_apiruc: text
 * - token_apiruc: text
 * - seller_can_create_product: boolean
 * - seller_can_view_balance: boolean
 * - seller_can_generate_sale_opportunities: boolean
 * - update_document_on_dispaches: boolean
 * - is_pharmacy: boolean
 * - search_item_by_series: boolean
 * - mi_tienda_pe: boolean
 * - group_items_generate_document: boolean
 * - change_free_affectation_igv: boolean
 * - currency_type_id: varchar(255)
 * - select_available_price_list: boolean
 * - show_extra_info_to_item: smallint
 * - enabled_global_igv_to_purchase: smallint
 * - show_pdf_name: smallint
 * - dispatches_address_text: smallint
 * - show_items_only_user_stablishment: bigint
 * - item_name_pdf_description: smallint
 * - auto_print: boolean
 * - printer_name_documents: varchar(255)
 * - show_service_on_pos: boolean
 * - pos_cost_price: boolean
 * - pos_history: boolean
 * - show_totals_on_cpe_list: boolean
 * - show_terms_condition_pos: boolean
 * - show_ticket_80: boolean
 * - show_ticket_58: boolean
 * - show_ticket_50: boolean
 * - show_complete_name_pos: boolean
 * - show_last_price_sale: boolean
 * - show_logo_by_establishment: boolean
 * - print_new_line_to_observation: smallint
 * - new_validator_pagination: bigint
 * - validate_purchase_sale_unit_price: boolean
 * - checked_global_igv_to_purchase: boolean
 * - checked_update_purchase_price: boolean
 * - set_global_purchase_currency_items: boolean
 * - set_unit_price_dispatch_related_record: boolean
 * - restrict_voided_send: boolean
 * - shipping_time_days_voided: integer
 * - enabled_tips_pos: boolean
 * - top_menu_a_id: bigint
 * - top_menu_b_id: bigint
 * - top_menu_c_id: bigint
 * - top_menu_d_id: bigint
 * - top_menu_extra_one: json
 * - top_menu_extra_two: json
 * - skin_id: bigint
 * - change_currency_item: boolean
 * - enabled_advanced_records_search: boolean
 * - change_decimal_quantity_unit_price_pdf: boolean
 * - decimal_quantity_unit_price_pdf: integer
 * - separate_cash_transactions: boolean
 * - order_cash_income: boolean
 * - generate_order_note_from_quotation: boolean
 * - list_items_by_warehouse: boolean
 * - hide_pdf_view_documents: boolean
 * - affect_all_documents: boolean
 * - dashboard_sales: boolean
 * - dashboard_general: boolean
 * - dashboard_clients: boolean
 * - dashboard_products: boolean
 * - dashboard_goal_enabled: boolean
 * - dashboard_goal_amount: numeric(30,12)
 * - restrict_series_selection_seller: boolean
 * - regex_password_user: boolean
 * - enabled_remember_change_password: boolean
 * - quantity_month_remember_change_password: integer
 * - enabled_point_system: boolean
 * - point_system_sale_amount: numeric(30,12)
 * - quantity_of_points: numeric(30,12)
 * - round_points_of_sale: boolean
 * - enable_categories_products_view: boolean
 * - restrict_seller_discount: boolean
 * - sellers_discount_limit: numeric(30,12)
 * - enabled_sales_agents: boolean
 * - change_affectation_exonerated_igv: boolean
 * - search_factory_code_items: boolean
 * - show_load_voucher: boolean
 * - register_series_invoice_xml: boolean
 * - enable_discount_by_customer: boolean
 * - enabled_dispatch_ticket_pdf: boolean
 * - enabled_dispatch_ticket_pdf_individual: boolean
 * - show_price_barcode_ticket: boolean
 * - price_selected_add_product: boolean
 * - pdf_footer_images: json
 * - restrict_sale_items_cpe: boolean
 * - show_convert_cpe_pos: boolean
 * - order_node_advanced: boolean
 * - remove_validation_email_establishments: boolean
 * - select_establishment_bank_account: boolean
 * - change_values_preview_document: boolean
 * - session_lifetime: numeric(30,12)
 * - search_items_main_form: boolean
 * - show_all_item_details: boolean
 * - add_description_to_document_item: boolean
 * - show_item_description_pack: boolean
 * - show_weighted_cost_purchase: boolean
 * - show_unify_amount_items: boolean
 * - condition_sale_purchase_price_to_item: boolean
 * - price1_label: varchar(255)
 * - price2_label: varchar(255)
 * - price3_label: varchar(255)
 * - qrchat_url: varchar(255)
 * - qrchat_app_key: varchar(255)
 * - qrchat_auth_key: varchar(255)
 * - qrchat_enable: boolean
 * - enable_list_product: boolean
 * - is_migrated_address: boolean
 * - qr_api_url: varchar(100)
 * - qr_api_apiKey: varchar(120)
 * - qr_api_enable: boolean
 * - show_seller_in_pdf: boolean
 * - show_bank_accounts_in_pdf: boolean
 * - enabled_price_items_dispatch: boolean
 * - exact_discount: boolean
 * - enabled_guarantee_fund: boolean
 * - from_guest_register: boolean
 * - was_verified_guest_user: boolean
 * - available_cash_report_seller: boolean
 * - restaurant_tip_factor: smallint
 * - enable_consigned: boolean
 * - enable_weight_in_dispatches: boolean
 * - public_search_bg_color: varchar(7)
 * - public_search_bg_image_path: varchar(255)
 * - auto_send_pdf_email: boolean
 * - enable_help_center: boolean
 * - enable_interactive_tours: boolean
 * - enable_guided_tours: boolean
 * - before_day_creation_suscription_order: integer
 * - message_notify_to_suscription_orders: text
 * - send_link_subscription: boolean
 * - count_send_link_subscription: integer
 * - show_item_discounts_charges_attributes: boolean
 * - disable_users: boolean
 * - enable_bank_accounts: boolean
 * - item_name_autocomplete: boolean
 * - search_by_click: boolean
 * - disable_retention_for_amount: boolean
 * - evolution_instance: varchar(255)
 * - evolution_instance_adopted: boolean
 * - evolution_use_qr_api_instance: boolean
 * - evolution_connected_phone: varchar(30)
 * - evolution_profile_name: varchar(255)
 * - evolution_instance_token: varchar(255)
 * - evolution_connection_state: varchar(20)
 * - evolution_connected_at: timestamp
 * - evolution_enabled: boolean
 * - evolution_owner_user_id: bigint
 * - whatsapp_bot_enabled: boolean
 * - evolution_webhook_token: varchar(64)
 * - bot_trigger_command: varchar(20)
 * - bot_exit_command: varchar(20)
 * - bot_pause_command: varchar(20)
 * - bot_session_ttl_minutes: bigint
 * - qr_api_instance: varchar(255)
 * - qr_api_instance_adopted: boolean
 * - qr_api_connected_phone: varchar(30)
 * - qr_api_profile_name: varchar(255)
 * - qr_api_instance_token: varchar(255)
 * - qr_api_connection_state: varchar(20)
 * - qr_api_connected_at: timestamp
 * - qr_api_webhook_token: varchar(64)
 * - qr_api_use_bot_instance: boolean
 * - date_format: varchar(255)
 * - time_format: varchar(255)
 * - date_of_due_test_days: date
 * - enable_dedicated_series: boolean
 * - qr_api_pdf_format: varchar(10)
 * - enable_global_discount: boolean
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('formats', 255)->collation('sab_ci_ai')->default('default');
            $table->boolean('cron')->default(true);
            $table->boolean('stock')->default(true);
            $table->bigInteger('limit_documents')->default('0');
            $table->bigInteger('limit_users')->default('10');
            $table->boolean('locked_emission')->default(false);
            $table->boolean('restrict_sales_limit')->default(false)->comment('habilitar restricción de límite de ventas mensual');
            $table->boolean('locked_create_establishments')->default(false);
            $table->boolean('permission_to_edit_cpe')->default(false);
            $table->boolean('customer_filter_by_seller')->default(false);
            $table->boolean('set_address_by_establishment')->default(false);
            $table->json('plan')->nullable();
            $table->boolean('enable_whatsapp')->default(true);
            $table->string('phone_whatsapp', 255)->collation('sab_ci_ai')->nullable();
            $table->text('apk_url')->collation('sab_ci_ai')->nullable();
            $table->json('visual')->nullable();
            $table->string('sidebar_mode', 255)->collation('sab_ci_ai')->nullable()->default('light')->comment('Modo del sidebar: light o dark');
            $table->string('product_default_image', 255)->collation('sab_ci_ai')->nullable();
            $table->smallInteger('decimal_quantity')->default('2');
            $table->boolean('locked_users')->default(false);
            $table->timestamp('date_time_start')->nullable();
            $table->integer('quantity_documents');
            $table->integer('quantity_sales_notes');
            $table->boolean('locked_tenant')->default(false);
            $table->boolean('compact_sidebar')->default(false);
            $table->smallInteger('colums_grid_item')->nullable()->default('4');
            $table->boolean('options_pos')->default(true);
            $table->boolean('auto_send_hka')->default(true)->comment('Envío automático de comprobantes digitales a HKA');
            $table->boolean('edit_name_product')->default(false);
            $table->boolean('restrict_receipt_date')->default(true);
            $table->integer('shipping_time_days')->default('4');
            $table->string('affectation_igv_type_id', 255)->collation('sab_ci_ai')->default('10');
            $table->string('global_discount_type_id', 255)->collation('sab_ci_ai')->default('02');
            $table->boolean('include_igv')->nullable();
            $table->boolean('global_igv_handling')->default(true);
            $table->decimal('percentage_allowance_charge', 30, 12)->nullable()->default('0.00');
            $table->decimal('igv_retention_percentage', 30, 12)->default('3.00000');
            $table->boolean('active_allowance_charge')->nullable()->default(false);
            $table->boolean('active_warehouse_prices')->nullable()->default(false);
            $table->boolean('product_only_location')->nullable();
            $table->text('terms_condition')->collation('sab_ci_ai')->nullable();
            $table->text('terms_condition_sale')->collation('sab_ci_ai')->nullable();
            $table->string('legend_footer_sale', 255)->collation('sab_ci_ai')->nullable();
            $table->boolean('cotizaction_finance')->default(true);
            $table->boolean('quotation_allow_seller_generate_sale')->default(false);
            $table->boolean('allow_edit_unit_price_to_seller')->default(false);
            $table->string('header_image', 255)->collation('sab_ci_ai')->nullable();
            $table->boolean('destination_sale')->default(true);
            $table->boolean('default_document_type_80')->default(false);
            $table->boolean('search_item_by_barcode')->default(false);
            $table->text('login')->collation('sab_ci_ai')->nullable();
            $table->string('navbar', 15)->collation('sab_ci_ai')->default('fixed');
            $table->text('finances')->collation('sab_ci_ai')->nullable();
            $table->text('smtp_encryption')->collation('sab_ci_ai')->nullable()->comment('Tipo de cifrado de correo');
            $table->text('smtp_password')->collation('C')->nullable()->comment('contraseña de usuario para el envio de correo');
            $table->text('smtp_user')->collation('sab_ci_ai')->nullable()->comment('Nombre de usuario para el envio de correo');
            $table->bigInteger('smtp_port')->default('0')->comment('Puerto de correo del cliente');
            $table->text('smtp_host')->collation('sab_ci_ai')->nullable()->comment('Host de correo del cliente');
            $table->boolean('ticket_58')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->text('url_apiruc')->collation('sab_ci_ai')->nullable();
            $table->text('token_apiruc')->collation('C')->nullable();
            $table->boolean('seller_can_create_product')->default(false)->comment('Define si los vendedores pueden crear productos');
            $table->boolean('seller_can_view_balance')->default(true)->comment('Define si los vendedores pueden ver el balance en finanzas');
            $table->boolean('seller_can_generate_sale_opportunities')->default(false)->comment('Define si los vendedores pueden crear productos');
            $table->boolean('update_document_on_dispaches')->default(false)->comment('Si esta activo, al momento de crear una guia, se actualiza el pdf de documentos.');
            $table->boolean('is_pharmacy')->default(false)->comment('Establece si se activa el modulo de farmacia');
            $table->boolean('search_item_by_series')->default(false);
            $table->boolean('mi_tienda_pe')->default(false);
            $table->boolean('group_items_generate_document')->default(true);
            $table->boolean('change_free_affectation_igv')->default(false);
            $table->string('currency_type_id', 255)->collation('sab_ci_ai')->nullable()->default('VES')->comment('Id de cat_currency_types_id');
            $table->boolean('select_available_price_list')->default(false);
            $table->smallInteger('show_extra_info_to_item')->nullable()->default('0')->comment('Habilita datos extra para item');
            $table->smallInteger('enabled_global_igv_to_purchase')->nullable()->default('0')->comment('Habilita el igv global en la compra. Sobreescribe has_igv del item');
            $table->smallInteger('show_pdf_name')->nullable()->default('0')->comment('Muestra el nombre de pdf en vez del producto');
            $table->smallInteger('dispatches_address_text')->nullable()->default('0')->comment('En guias, habilita colocar la direccion de destino como texto');
            $table->bigInteger('show_items_only_user_stablishment')->nullable()->default('1')->comment('permite mostrar stock del alamcen de usuario');
            $table->smallInteger('item_name_pdf_description')->default('0')->comment('Si esta activado, el nombre de pdf será por defecto la descripcion del item');
            $table->boolean('auto_print')->default(false);
            $table->string('printer_name_documents', 255)->collation('sab_ci_ai')->nullable();
            $table->boolean('show_service_on_pos')->default(false)->comment('Permite listar al inicio, los servicios en pos');
            $table->boolean('pos_cost_price')->default(true);
            $table->boolean('pos_history')->default(true);
            $table->boolean('show_totals_on_cpe_list')->default(false);
            $table->boolean('show_terms_condition_pos')->default(false);
            $table->boolean('show_ticket_80')->default(true);
            $table->boolean('show_ticket_58')->default(false);
            $table->boolean('show_ticket_50')->default(false);
            $table->boolean('show_complete_name_pos')->default(false);
            $table->boolean('show_last_price_sale')->default(false);
            $table->boolean('show_logo_by_establishment')->default(false);
            $table->smallInteger('print_new_line_to_observation')->default('0')->comment('Añade la posiblidad de colocar salto de linea en observación de pdf');
            $table->bigInteger('new_validator_pagination')->nullable()->default('0');
            $table->boolean('validate_purchase_sale_unit_price')->default(false);
            $table->boolean('checked_global_igv_to_purchase')->default(false);
            $table->boolean('checked_update_purchase_price')->default(false);
            $table->boolean('set_global_purchase_currency_items')->default(false);
            $table->boolean('set_unit_price_dispatch_related_record')->default(false);
            $table->boolean('restrict_voided_send')->default(true);
            $table->integer('shipping_time_days_voided')->default('7');
            $table->boolean('enabled_tips_pos')->default(false);
            $table->bigInteger('top_menu_a_id')->nullable();
            $table->bigInteger('top_menu_b_id')->nullable();
            $table->bigInteger('top_menu_c_id')->nullable();
            $table->bigInteger('top_menu_d_id')->nullable();
            $table->json('top_menu_extra_one')->nullable();
            $table->json('top_menu_extra_two')->nullable();
            $table->bigInteger('skin_id')->default('1');
            $table->boolean('change_currency_item')->default(false);
            // ######## INICIO MONEDA DOCUMENTAL DE VENTAS ########
            $table->boolean('sales_documents_ves')->default(true);
            // ######## FIN MONEDA DOCUMENTAL DE VENTAS ########
            $table->boolean('enabled_advanced_records_search')->default(false);
            $table->boolean('change_decimal_quantity_unit_price_pdf')->default(false);
            $table->integer('decimal_quantity_unit_price_pdf')->default('2');
            $table->boolean('separate_cash_transactions')->default(false);
            $table->boolean('order_cash_income')->default(false);
            $table->boolean('generate_order_note_from_quotation')->default(false);
            $table->boolean('list_items_by_warehouse')->default(false);
            $table->boolean('hide_pdf_view_documents')->default(false);
            $table->boolean('affect_all_documents')->default(false);
            $table->boolean('dashboard_sales')->default(true);
            $table->boolean('dashboard_general')->default(true);
            $table->boolean('dashboard_clients')->default(true);
            $table->boolean('dashboard_products')->default(false);
            $table->boolean('dashboard_goal_enabled')->default(false);
            $table->decimal('dashboard_goal_amount', 30, 12)->default('0.00');
            $table->boolean('restrict_series_selection_seller')->default(false);
            $table->boolean('regex_password_user')->default(false);
            $table->boolean('enabled_remember_change_password')->default(false);
            $table->integer('quantity_month_remember_change_password')->default('1');
            $table->boolean('enabled_point_system')->default(false)->comment('sistema por puntos');
            $table->decimal('point_system_sale_amount', 30, 12)->default('1.00')->comment('sistema por puntos');
            $table->decimal('quantity_of_points', 30, 12)->default('1.00')->comment('sistema por puntos');
            $table->boolean('round_points_of_sale')->default(false)->comment('sistema por puntos');
            $table->boolean('enable_categories_products_view')->default(false);
            $table->boolean('restrict_seller_discount')->default(false)->comment('limitar descuento a los vendedores');
            $table->decimal('sellers_discount_limit', 30, 12)->default('0.00')->comment('limitar descuento a los vendedores');
            $table->boolean('enabled_sales_agents')->default(false);
            $table->boolean('change_affectation_exonerated_igv')->default(false);
            $table->boolean('search_factory_code_items')->default(false);
            $table->boolean('show_load_voucher')->default(false);
            $table->boolean('register_series_invoice_xml')->default(false);
            $table->boolean('enable_discount_by_customer')->default(false);
            $table->boolean('enabled_dispatch_ticket_pdf')->default(false);
            $table->boolean('enabled_dispatch_ticket_pdf_individual')->default(false);
            $table->boolean('show_price_barcode_ticket')->nullable();
            $table->boolean('price_selected_add_product')->default(false);
            $table->json('pdf_footer_images')->nullable();
            $table->boolean('restrict_sale_items_cpe')->default(false);
            $table->boolean('show_convert_cpe_pos')->default(false);
            $table->boolean('order_node_advanced')->default(false);
            $table->boolean('remove_validation_email_establishments')->default(false);
            $table->boolean('select_establishment_bank_account')->default(false);
            $table->boolean('change_values_preview_document')->default(false);
            $table->decimal('session_lifetime', 30, 12)->default('2.00');
            $table->boolean('search_items_main_form')->default(false);
            $table->boolean('show_all_item_details')->default(false);
            $table->boolean('add_description_to_document_item')->default(false);
            $table->boolean('show_item_description_pack')->default(false);
            $table->boolean('show_weighted_cost_purchase')->default(false);
            $table->boolean('show_unify_amount_items')->default(false);
            $table->boolean('condition_sale_purchase_price_to_item')->default(false)->comment('precio de venta no debe ser menor a costo');
            $table->string('price1_label', 255)->collation('sab_ci_ai')->default('Precio 1');
            $table->string('price2_label', 255)->collation('sab_ci_ai')->default('Precio 2');
            $table->string('price3_label', 255)->collation('sab_ci_ai')->default('Precio 3');
            $table->string('qrchat_url', 255)->collation('sab_ci_ai')->nullable()->comment('datos de acceso para envio gratuito mediante whatsapp');
            $table->string('qrchat_app_key', 255)->collation('sab_ci_ai')->nullable()->comment('datos de acceso para envio gratuito mediante whatsapp');
            $table->string('qrchat_auth_key', 255)->collation('sab_ci_ai')->nullable()->comment('datos de acceso para envio gratuito mediante whatsapp');
            $table->boolean('qrchat_enable')->default(false)->comment('falso mostrara boton de wsapp web');
            $table->boolean('enable_list_product')->default(true)->comment('habilitar listado de precios de productos');
            $table->boolean('is_migrated_address')->default(false);
            $table->string('qr_api_url', 100)->collation('sab_ci_ai')->nullable();
            $table->string('qr_api_apiKey', 120)->collation('sab_ci_ai')->nullable();
            $table->boolean('qr_api_enable')->default(false);
            $table->boolean('show_seller_in_pdf')->default(true);
            $table->boolean('show_bank_accounts_in_pdf')->default(true);
            $table->boolean('enabled_price_items_dispatch')->default(false);
            $table->boolean('exact_discount')->default(true);
            $table->boolean('enabled_guarantee_fund')->default(false);
            $table->boolean('from_guest_register')->default(false);
            $table->boolean('was_verified_guest_user')->default(false);
            $table->boolean('available_cash_report_seller')->default(false);
            $table->smallInteger('restaurant_tip_factor')->default('0');
            $table->boolean('enable_consigned')->default(false);
            $table->boolean('enable_weight_in_dispatches')->default(false);
            $table->string('public_search_bg_color', 7)->collation('sab_ci_ai')->nullable();
            $table->string('public_search_bg_image_path', 255)->collation('sab_ci_ai')->nullable();
            $table->boolean('auto_send_pdf_email')->default(false);
            $table->boolean('enable_help_center')->default(true);
            $table->boolean('enable_interactive_tours')->default(true);
            $table->boolean('enable_guided_tours')->default(false);
            $table->integer('before_day_creation_suscription_order')->default('1');
            $table->text('message_notify_to_suscription_orders')->collation('sab_ci_ai')->nullable();
            $table->boolean('send_link_subscription')->default(false);
            $table->integer('count_send_link_subscription')->default('0');
            $table->boolean('show_item_discounts_charges_attributes')->default(false);
            $table->boolean('disable_users')->default(false);
            $table->boolean('enable_bank_accounts')->default(false);
            $table->boolean('item_name_autocomplete')->default(false)->comment('autocompleta nombre y codigo de barra con atributos');
            $table->boolean('search_by_click')->default(false);
            $table->boolean('disable_retention_for_amount')->default(false);
            $table->string('evolution_instance', 255)->collation('sab_ci_ai')->nullable();
            $table->boolean('evolution_instance_adopted')->default(false);
            $table->boolean('evolution_use_qr_api_instance')->default(false);
            $table->string('evolution_connected_phone', 30)->collation('sab_ci_ai')->nullable();
            $table->string('evolution_profile_name', 255)->collation('sab_ci_ai')->nullable();
            $table->string('evolution_instance_token', 255)->collation('C')->nullable();
            $table->string('evolution_connection_state', 20)->collation('sab_ci_ai')->default('disconnected');
            $table->timestamp('evolution_connected_at')->nullable();
            $table->boolean('evolution_enabled')->default(false);
            $table->bigInteger('evolution_owner_user_id')->nullable();
            $table->boolean('whatsapp_bot_enabled')->default(true);
            $table->string('evolution_webhook_token', 64)->collation('C')->nullable();
            $table->string('bot_trigger_command', 20)->collation('sab_ci_ai')->default('/bot');
            $table->string('bot_exit_command', 20)->collation('sab_ci_ai')->default('/fin');
            $table->string('bot_pause_command', 20)->collation('sab_ci_ai')->default('/p');
            $table->bigInteger('bot_session_ttl_minutes')->default('30');
            $table->string('qr_api_instance', 255)->collation('sab_ci_ai')->nullable();
            $table->boolean('qr_api_instance_adopted')->default(false);
            $table->string('qr_api_connected_phone', 30)->collation('sab_ci_ai')->nullable();
            $table->string('qr_api_profile_name', 255)->collation('sab_ci_ai')->nullable();
            $table->string('qr_api_instance_token', 255)->collation('C')->nullable();
            $table->string('qr_api_connection_state', 20)->collation('sab_ci_ai')->default('disconnected');
            $table->timestamp('qr_api_connected_at')->nullable();
            $table->string('qr_api_webhook_token', 64)->collation('C')->nullable();
            $table->boolean('qr_api_use_bot_instance')->default(false);
            $table->string('date_format', 255)->collation('sab_ci_ai')->default('DD-MM-YYYY')->comment('Plantilla de formato de fecha para mostrar en listados y formularios. Ej: DD-MM-YYYY=27-05-2026, DD/MM/YYYY=27/05/2026, YYYY-MM-DD=2026-05-27. Se guarda con la sintaxis de moment.js.');
            $table->string('time_format', 255)->collation('sab_ci_ai')->default('HH:mm:ss')->comment('Plantilla de formato de hora para mostrar en listados y formularios. Ej: HH:mm=14:30, HH:mm:ss=14:30:45, hh:mm A=02:30 PM. Se guarda con la sintaxis de moment.js.');
            $table->date('date_of_due_test_days')->nullable();
            $table->boolean('enable_dedicated_series')->default(false);
            $table->string('qr_api_pdf_format', 10)->collation('sab_ci_ai')->default('ticket');
            $table->boolean('enable_global_discount')->default(false);
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
        });
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_limit_documents_range" CHECK ("limit_documents" BETWEEN -9223372036854775808 AND 9223372036854775807)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_limit_users_range" CHECK ("limit_users" BETWEEN -9223372036854775808 AND 9223372036854775807)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_decimal_quantity_range" CHECK ("decimal_quantity" BETWEEN -128 AND 127)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_quantity_documents_range" CHECK ("quantity_documents" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_quantity_sales_notes_range" CHECK ("quantity_sales_notes" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_colums_grid_item_range" CHECK ("colums_grid_item" BETWEEN -128 AND 127)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_shipping_time_days_range" CHECK ("shipping_time_days" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_smtp_port_range" CHECK ("smtp_port" BETWEEN 0 AND 4294967295)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_show_extra_info_to_item_range" CHECK ("show_extra_info_to_item" BETWEEN 0 AND 255)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_enabled_global_igv_to_purchase_range" CHECK ("enabled_global_igv_to_purchase" BETWEEN 0 AND 255)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_show_pdf_name_range" CHECK ("show_pdf_name" BETWEEN 0 AND 255)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_dispatches_address_text_range" CHECK ("dispatches_address_text" BETWEEN 0 AND 255)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_show_items_only_user_stablishment_range" CHECK ("show_items_only_user_stablishment" BETWEEN 0 AND 4294967295)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_item_name_pdf_description_range" CHECK ("item_name_pdf_description" BETWEEN -128 AND 127)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_print_new_line_to_observation_range" CHECK ("print_new_line_to_observation" BETWEEN 0 AND 255)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_new_validator_pagination_range" CHECK ("new_validator_pagination" BETWEEN 0 AND 16777215)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_shipping_time_days_voided_range" CHECK ("shipping_time_days_voided" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_top_menu_a_id_range" CHECK ("top_menu_a_id" >= 0)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_top_menu_b_id_range" CHECK ("top_menu_b_id" >= 0)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_top_menu_c_id_range" CHECK ("top_menu_c_id" >= 0)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_top_menu_d_id_range" CHECK ("top_menu_d_id" >= 0)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_skin_id_range" CHECK ("skin_id" >= 0)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_decimal_quantity_unit_price_pdf_range" CHECK ("decimal_quantity_unit_price_pdf" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_quantity_month_remember_change_password_range" CHECK ("quantity_month_remember_change_password" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_restaurant_tip_factor_range" CHECK ("restaurant_tip_factor" BETWEEN -128 AND 127)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_before_day_creation_suscription_order_range" CHECK ("before_day_creation_suscription_order" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_count_send_link_subscription_range" CHECK ("count_send_link_subscription" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_evolution_owner_user_id_range" CHECK ("evolution_owner_user_id" >= 0)');
        DB::statement('ALTER TABLE "configurations" ADD CONSTRAINT "configurations_bot_session_ttl_minutes_range" CHECK ("bot_session_ttl_minutes" BETWEEN 0 AND 4294967295)');
    }

    public function down(): void
    {
        Schema::dropIfExists('configurations');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
