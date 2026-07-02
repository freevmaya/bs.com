<?php

use yii\db\Migration;

class m240000_000003_add_file_type_to_advertisement_images extends Migration
{
    public function safeUp()
    {
        $this->addColumn('advertisement_images', 'file_type', $this->string(20)->defaultValue('image')->after('thumbnail_path'));
    }
    
    public function safeDown()
    {
        $this->dropColumn('advertisement_images', 'file_type');
    }
}