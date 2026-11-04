<?php

class Create_messages_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('messages')) {
            return;
        }

        $this->_lava->dbforge
            ->add_field([
                'message_id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'auto_increment' => TRUE,
                    'null'           => FALSE,
                ],
                'sender_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => FALSE,
                ],
                'receiver_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => FALSE,
                ],
                'product_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => TRUE,
                    'default'    => NULL,
                ],
                'body' => [
                    'type' => 'TEXT',
                    'null' => FALSE,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => FALSE,
                ],
                'read_at' => [
                    'type'    => 'DATETIME',
                    'null'    => TRUE,
                    'default' => NULL,
                ],
            ])
            ->add_key('message_id', primary: TRUE)
            ->add_key('receiver_id', name: 'idx_messages_receiver')
            ->add_key('sender_id', name: 'idx_messages_sender')
            ->create_table('messages');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('messages');
    }
}