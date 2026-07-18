<?php
// migrations/m240000_000017_add_invitation_token_to_advertisements.php

use yii\db\Migration;

class m240000_000017_add_invitation_token_to_advertisements extends Migration
{
    public function safeUp()
    {
        $this->addColumn('advertisements', 'invitation_token', $this->string(36)->unique()->after('source_url'));
        $this->addColumn('advertisements', 'invitation_token_created_at', $this->integer()->after('invitation_token'));
        $this->createIndex('idx-advertisements-invitation_token', 'advertisements', 'invitation_token');
    }

    public function safeDown()
    {
        $this->dropIndex('idx-advertisements-invitation_token', 'advertisements');
        $this->dropColumn('advertisements', 'invitation_token_created_at');
        $this->dropColumn('advertisements', 'invitation_token');
    }
}