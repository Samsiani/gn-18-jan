<?php
/**
 * DB Schema consistency tests
 *
 * These tests read the raw PHP source of CIG_DB_Installer and verify that
 * every column referenced by the REST API actually exists in the CREATE TABLE
 * statement.  No database connection or WordPress is required.
 *
 * Rationale: The REST API (class-cig-rest-invoices.php and
 * class-cig-rest-dashboard.php) reads and writes the following columns on
 * wp_cig_invoices that were initially absent from the schema:
 *
 *   is_credit_checked   — PATCH /invoices/{id}/accountant-status
 *   is_receipt_checked  — PATCH /invoices/{id}/accountant-status
 *   is_corrected        — PATCH /invoices/{id}/accountant-status
 *   accountant_note     — PATCH /invoices/{id}/accountant-note
 *   rs_uploaded_by      — PATCH /invoices/{id}/accountant-status (isRsUploaded)
 *   rs_uploaded_date    — PATCH /invoices/{id}/accountant-status (isRsUploaded)
 *
 * A missing column causes a silent MySQL error on the first write and NULL
 * reads on every GET, breaking the entire Accountant page.
 *
 * @package CIG\Tests
 */

class DbSchemaTest extends \PHPUnit\Framework\TestCase {

    /** @var string Full source of class-cig-db-installer.php */
    private static string $source;

    public static function setUpBeforeClass(): void {
        self::$source = file_get_contents(
            CIG_INCLUDES_DIR . 'class-cig-db-installer.php'
        );
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------
    private function assertColumnExists(string $column): void {
        $this->assertStringContainsString(
            $column,
            self::$source,
            "Column '{$column}' is missing from the cig_invoices CREATE TABLE statement in CIG_DB_Installer."
        );
    }

    // -------------------------------------------------------------------------
    // Columns that the REST API assumes exist on wp_cig_invoices
    // -------------------------------------------------------------------------

    public function test_schema_has_is_rs_uploaded(): void {
        $this->assertColumnExists('is_rs_uploaded');
    }

    public function test_schema_has_is_credit_checked(): void {
        $this->assertColumnExists('is_credit_checked');
    }

    public function test_schema_has_is_receipt_checked(): void {
        $this->assertColumnExists('is_receipt_checked');
    }

    public function test_schema_has_is_corrected(): void {
        $this->assertColumnExists('is_corrected');
    }

    public function test_schema_has_accountant_note(): void {
        $this->assertColumnExists('accountant_note');
    }

    public function test_schema_has_rs_uploaded_by(): void {
        $this->assertColumnExists('rs_uploaded_by');
    }

    public function test_schema_has_rs_uploaded_date(): void {
        $this->assertColumnExists('rs_uploaded_date');
    }

    // -------------------------------------------------------------------------
    // Customers table — tax_id index type (non-unique is a known issue)
    // -------------------------------------------------------------------------

    public function test_customers_schema_has_tax_id_key(): void {
        $this->assertStringContainsString(
            'tax_id',
            self::$source,
            "tax_id is missing from the cig_customers schema."
        );
    }

    // -------------------------------------------------------------------------
    // Structural checks
    // -------------------------------------------------------------------------

    public function test_schema_creates_four_tables(): void {
        $matches = [];
        preg_match_all('/CREATE TABLE/', self::$source, $matches);
        $this->assertGreaterThanOrEqual(
            4,
            count($matches[0]),
            'Expected at least 4 CREATE TABLE statements (invoices, items, payments, customers).'
        );
    }

    public function test_invoices_table_has_unique_invoice_number(): void {
        $this->assertStringContainsString(
            'UNIQUE KEY invoice_number',
            self::$source,
            'invoice_number must have a UNIQUE KEY to prevent duplicate invoice numbers.'
        );
    }
}
