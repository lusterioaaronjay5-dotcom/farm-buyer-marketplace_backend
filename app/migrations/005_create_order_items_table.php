<?php

class Create_order_items_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('order_items')) {
            return;
        }

        $this->_lava->dbforge
            ->add_field([
                'order_item_id' => [
                    'type'           => 'INT',
                    'constraint'     => 10,
                    'unsigned'       => TRUE,
                    'auto_increment' => TRUE,
                    'null'           => FALSE,
                ],
                'order_id' => [
                    'type'       => 'INT',
                    'constraint' => 10,
                    'unsigned'   => TRUE,
                    'null'       => FALSE,
                ],
                'product_id' => [
                    'type'       => 'INT',
                    'constraint' => 10,
                    'unsigned'   => TRUE,
                    'null'       => FALSE,
                ],
                'quantity' => [
                    'type'       => 'INT',
                    'constraint' => 10,
                    'unsigned'   => TRUE,
                    'null'       => FALSE,
                ],
                'unit_price' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'null'       => FALSE,
                ],
                'subtotal' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '12,2',
                    'null'       => FALSE,
                ],
            ])
            ->add_key('order_item_id', primary: TRUE)
            ->add_key('order_id', name: 'fk_items_order')
            ->add_key('product_id', name: 'fk_items_product')
            ->add_foreign_key('order_id', 'orders', 'order_id', 'CASCADE', 'CASCADE')
            ->add_foreign_key('product_id', 'products', 'product_id', 'RESTRICT', 'CASCADE')
            ->create_table('order_items');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('order_items');
    }
}