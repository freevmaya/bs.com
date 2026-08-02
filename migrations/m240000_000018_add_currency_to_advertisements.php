// FILE: .\migrations\m240000_000018_add_currency_to_advertisements.php
<?php
// migrations/m240000_000018_add_currency_to_advertisements.php

use yii\db\Migration;

class m240000_000018_add_currency_to_advertisements extends Migration
{
    public function safeUp()
    {
        $this->addColumn('advertisements', 'currency', $this->string(3)->notNull()->defaultValue('RUB')->after('price_negotiable'));
        $this->createIndex('idx-advertisements-currency', 'advertisements', 'currency');
    }

    public function safeDown()
    {
        $this->dropIndex('idx-advertisements-currency', 'advertisements');
        $this->dropColumn('advertisements', 'currency');
    }
}