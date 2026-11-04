<?php

class Create_deliveries_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('deliveries')) {
            return;
        }

        $this->_lava->dbforge
            ->add_field([
                'delivery_id' => [
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
                'delivery_status' => [
                    'type'       => 'ENUM',
                    'constraint' => "'pending','preparing','out_for_delivery','delivered','cancelled'",
                    'null'       => FALSE,
                    'default'    => 'pending',
                ],
                'delivery_address' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 500,
                    'null'       => TRUE,
                    'default'    => NULL,
                ],
                'latitude' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,7',
                    'null'       => TRUE,
                    'default'    => NULL,
                ],
                'longitude' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,7',
                    'null'       => TRUE,
                    'default'    => NULL,
                ],
                'updated_at' => [
                    'type'    => 'TIMESTAMP',
                    'null'    => TRUE,
                    'default' => 'CURRENT_TIMESTAMP',
                ],
            ])
            ->add_key('delivery_id', primary: TRUE)
            ->add_key('order_id', unique: TRUE, name: 'order_id')
            ->add_foreign_key('order_id', 'orders', 'order_id', 'CASCADE', 'CASCADE')
            ->create_table('deliveries');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('deliveries');
    }
}