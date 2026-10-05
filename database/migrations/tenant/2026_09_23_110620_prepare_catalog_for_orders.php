<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection();
        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach (['users', 'customers', 'products', 'product_variants', 'taxes', 'audit_logs'] as $table) {
                $metadata = $connection->selectOne('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$connection->getDatabaseName(), $connection->getTablePrefix().$table]);
                if ($metadata && strtolower($metadata->ENGINE) !== 'innodb') {
                    $connection->statement('ALTER TABLE '.$connection->getQueryGrammar()->wrapTable($table).' ENGINE=InnoDB, ROW_FORMAT=DYNAMIC');
                }
            }
        }
        if (! Schema::hasColumn('products', 'hsn_code')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->string('hsn_code', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('hsn_code');
        });
        // Engine conversions intentionally remain transactional on rollback.
    }
};
