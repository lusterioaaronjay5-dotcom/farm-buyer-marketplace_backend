<?php

class Create_payments_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('payments')) {
            return;
        }

        $this->_lava->dbforge
            ->add_field([
                'payment_id' => [
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
                'amount' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '12,2',
                    'null'       => FALSE,
                ],
                'payment_method' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 80,
                    'null'       => FALSE,
                    'default'    => 'gcash',
                ],
                'payment_status' => [
                    'type'       => 'ENUM',
                    'constraint' => "'pending','paid','failed','refunded'",
                    'null'       => FALSE,
                    'default'    => 'pending',
                ],
                'transaction_reference' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => TRUE,
                    'default'    => NULL,
                ],
                'created_at' => [
                    'type'    => 'TIMESTAMP',
                    'null'    => TRUE,
                    'default' => 'CURRENT_TIMESTAMP',
                ],
                'paid_at' => [
                    'type'    => 'TIMESTAMP',
                    'null'    => TRUE,
                    'default' => NULL,
                ],
            ])
            ->add_key('payment_id', primary: TRUE)
            ->add_key('order_id', unique: TRUE, name: 'order_id')
            ->add_foreign_key('order_id', 'orders', 'order_id', 'CASCADE', 'CASCADE')
            ->create_table('payments');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('payments');
    }
}