<?php

namespace panix\mod\shop\models;

use Yii;
use yii\db\ActiveRecord;


/**
 * Class Length
 * @property integer $id
 */
class Length extends ActiveRecord
{

    const MODULE_ID = 'shop';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%shop_product_length}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
        ];
    }
}
