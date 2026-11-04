<?php

class Create_products_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('products')) {
            return;
        }

        $this->_lava->dbforge
            ->add_field([
                'product_id' => [
                    'type'           => 'INT',
                    'constraint'     => 10,
                    'unsigned'       => TRUE,
                    'auto_increment' => TRUE,
                    'null'           => FALSE,
                ],
                'farmer_id' => [
                    'type'       => 'INT',
                    'constraint' => 10,
                    'unsigned'   => TRUE,
                    'null'       => FALSE,
                ],
                'product_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'null'       => FALSE,
                ],
                'description' => [
                    'type' => 'TEXT',
                    'null' => TRUE,
                ],
                'category' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => FALSE,
                ],
                'price' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'null'       => FALSE,
                ],
                'quantity_available' => [
                    'type'       => 'INT',
                    'constraint' => 10,
                    'unsigned'   => TRUE,
                    'null'       => FALSE,
                    'default'    => 0,
                ],
                'unit' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'null'       => FALSE,
                    'default'    => 'kg',
                ],
                'availability' => [
                    'type'       => 'ENUM',
                    'constraint' => "'available','unavailable'",
                    'null'       => FALSE,
                    'default'    => 'available',
                ],
                'location' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => TRUE,
                    'default'    => NULL,
                ],
                'image_path' => [
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
                'updated_at' => [
                    'type'    => 'TIMESTAMP',
                    'null'    => TRUE,
                    'default' => 'CURRENT_TIMESTAMP',
                ],
            ])
            ->add_key('product_id', primary: TRUE)
            ->add_key('farmer_id', name: 'fk_products_farmer')
            ->add_foreign_key('farmer_id', 'users', 'user_id', 'RESTRICT', 'CASCADE')
            ->create_table('products');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('products');
    }
}