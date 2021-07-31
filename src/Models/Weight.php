<?php

namespace panix\mod\shop\models;

use Yii;
use yii\db\ActiveRecord;


/**
 * Class Weight
 * @property integer $id
 */
class Weight extends ActiveRecord
{

    const MODULE_ID = 'shop';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%shop_product_weight}}';
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
