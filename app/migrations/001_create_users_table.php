<?php

class Create_users_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('users')) {
            return;
        }

        $this->_lava->dbforge
            ->add_field([
                'user_id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => TRUE,
                    'auto_increment' => TRUE,
                    'null'           => FALSE,
                ],
                'full_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'null'       => FALSE,
                ],
                'email' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => FALSE,
                ],
                'password_hash' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => FALSE,
                ],
                'role' => [
                    'type'       => 'ENUM',
                    'constraint' => "'farmer','buyer','admin'",
                    'null'       => FALSE,
                    'default'    => 'buyer',
                ],
                'phone' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'null'       => TRUE,
                    'default'    => NULL,
                ],
                'address' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 500,
                    'null'       => TRUE,
                    'default'    => NULL,
                ],
                'created_at' => [
                    'type'    => 'TIMESTAMP',
                    'null'    => TRUE,
                    'default' => 'CURRENT_TIMESTAMP',
                ],
                'profile_picture' => [
                    'type' => 'MEDIUMTEXT',
                    'null' => TRUE,
                ],
            ])
            ->add_key('user_id', primary: TRUE)
            ->add_key('email', unique: TRUE, name: 'email')
            ->create_table('users');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('users');
    }
}