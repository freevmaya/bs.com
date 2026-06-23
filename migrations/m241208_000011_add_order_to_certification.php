<?php

use yii\db\Migration;

class m241208_000011_add_order_to_certification extends Migration
{
    public function safeUp()
    {
        $this->addColumn('certification', 'order', $this->integer()->defaultValue(0)->after('name'));
        
        // Обновляем существующие записи с порядком
        $certifications = [
            'EN A' => 1,
            'EN B' => 2,
            'EN C' => 3,
            'EN D' => 4,
            'CCC' => 5,
            'LFT' => 6,
            'LFT A' => 7,
            'LFT B' => 8,
        ];
        
        foreach ($certifications as $name => $order) {
            $this->update('certification', ['order' => $order], ['name' => $name]);
        }
    }
    
    public function safeDown()
    {
        $this->dropColumn('certification', 'order');
    }
}