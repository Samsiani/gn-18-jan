<?php
/**
 * Database Schema Manager
 * Creates and manages custom tables for the CIG plugin
 * 
 * NOTE: This file is deprecated. Use CIG_DB_Installer instead.
 * Keeping for backward compatibility.
 *
 * @package CIG
 * @since 4.0.0
 * @deprecated Use CIG_DB_Installer class instead
 */

if (!defined('ABSPATH')) {
    exit;
}

class CIG_Database {

    /**
     * Create custom tables for the plugin
     * 
     * @deprecated Use CIG_DB_Installer::install() instead
     * @return void
     */
    public static function create_tables() {
        // Delegate to CIG_DB_Installer for consistent schema
        if (class_exists('CIG_DB_Installer')) {
            CIG_DB_Installer::install();
            return;
        }

        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();

        // Invoices Table - Updated to match CIG_DB_Installer schema
        // Column mapping:
        // - status column maps to _cig_invoice_status meta (values: standard/fictive)
        // - lifecycle_status column maps to _cig_lifecycle_status meta (values: completed/reserved/unfinished)
        // - sale_date is the activation_date (NULL for fictive, set for standard)
        $table_invoices = $wpdb->prefix . 'cig_invoices';
        $sql_invoices = "CREATE TABLE $table_invoices (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            invoice_number varchar(50) NOT NULL,
            customer_id bigint(20) DEFAULT 0,
            status varchar(20) DEFAULT 'fictive',
            lifecycle_status varchar(20) DEFAULT 'unfinished',
            total_amount decimal(10,2) DEFAULT 0.00,
            paid_amount decimal(10,2) DEFAULT 0.00,
            created_at datetime DEFAULT NULL,
            sale_date datetime DEFAULT NULL,
            sold_date date DEFAULT NULL,
            author_id bigint(20) DEFAULT 0,
            general_note text,
            is_rs_uploaded tinyint(1) DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY invoice_number (invoice_number),
            KEY status (status),
            KEY lifecycle_status (lifecycle_status),
            KEY sale_date (sale_date),
            KEY sold_date (sold_date),
            KEY customer_id (customer_id),
            KEY author_id (author_id)
        ) $charset_collate;";

        // Invoice Items Table
        $table_items = $wpdb->prefix . 'cig_invoice_items';
        $sql_items = "CREATE TABLE $table_items (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) NOT NULL DEFAULT 0,
            product_id bigint(20) DEFAULT 0,
            product_name varchar(255) DEFAULT '',
            sku varchar(100) DEFAULT '',
            description text,
            quantity decimal(10,2) DEFAULT 0.00,
            price decimal(10,2) DEFAULT 0.00,
            total decimal(10,2) DEFAULT 0.00,
            item_status varchar(20) DEFAULT 'none',
            warranty_duration varchar(50) DEFAULT '',
            reservation_days int(11) DEFAULT 0,
            image varchar(500) DEFAULT '',
            PRIMARY KEY  (id),
            KEY invoice_id (invoice_id),
            KEY product_id (product_id),
            KEY item_status (item_status)
        ) $charset_collate;";

        // Payments Table
        $table_payments = $wpdb->prefix . 'cig_payments';
        $sql_payments = "CREATE TABLE $table_payments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) NOT NULL DEFAULT 0,
            amount decimal(10,2) DEFAULT 0.00,
            date datetime DEFAULT NULL,
            method varchar(50) DEFAULT 'other',
            user_id bigint(20) DEFAULT 0,
            comment text,
            PRIMARY KEY  (id),
            KEY invoice_id (invoice_id),
            KEY date (date),
            KEY method (method)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_invoices);
        dbDelta($sql_items);
        dbDelta($sql_payments);
    }

    /**
     * Check if all required tables exist
     *
     * @return bool
     */
    public static function tables_exist() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'cig_invoices',
            $wpdb->prefix . 'cig_invoice_items',
            $wpdb->prefix . 'cig_payments'
        ];

        foreach ($tables as $table) {
            $result = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            ));
            if ($result !== $table) {
                return false;
            }
        }

        return true;
    }

    /**
     * Drop all custom tables
     *
     * @return void
     */
    public static function drop_tables() {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'cig_payments',
            $wpdb->prefix . 'cig_invoice_items',
            $wpdb->prefix . 'cig_invoices'
        ];

        foreach ($tables as $table) {
            // Table names are constructed from $wpdb->prefix which is safe
            // Using esc_sql for additional safety even though prefix is trusted
            $safe_table = esc_sql($table);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("DROP TABLE IF EXISTS `{$safe_table}`");
        }
    }
}
