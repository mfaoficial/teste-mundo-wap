<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateStoresTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('stores', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'biginteger', ['autoIncrement' => true]);
        $table->addColumn('name', 'string', ['limit' => 200, 'null' => false]);
        $table->create();
    }
}
