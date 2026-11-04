<?php

class Create_orders_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('orders')) {
            return;
        }

        $this->_lava->dbforge
            ->add_field([
                'order_id' => [
                    'type'           => 'INT',
                    'constraint'     => 10,
                    'unsigned'       => TRUE,
                    'auto_increment' => TRUE,
                    'null'           => FALSE,
                ],
                'buyer_id' => [
                    'type'       => 'INT',
                    'constraint' => 10,
                    'unsigned'   => TRUE,
                    'null'       => FALSE,
                ],
                'order_date' => [
                    'type'    => 'TIMESTAMP',
                    'null'    => TRUE,
                    'default' => 'CURRENT_TIMESTAMP',
                ],
                'total_amount' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '12,2',
                    'null'       => FALSE,
                    'default'    => '0.00',
                ],
                'status' => [
                    'type'       => 'ENUM',
                    'constraint' => "'pending','confirmed','preparing','out_for_delivery','completed','cancelled'",
                    'null'       => FALSE,
                    'default'    => 'pending',
                ],
                'delivery_address' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 500,
                    'null'       => TRUE,
                    'default'    => NULL,
                ],
                'notes' => [
                    'type' => 'TEXT',
                    'null' => TRUE,
                ],
            ])
            ->add_key('order_id', primary: TRUE)
            ->add_key('buyer_id', name: 'fk_orders_buyer')
            ->add_foreign_key('buyer_id', 'users', 'user_id', 'RESTRICT', 'CASCADE')
            ->create_table('orders');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('orders');
    }
}