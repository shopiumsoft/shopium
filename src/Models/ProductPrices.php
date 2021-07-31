<?php

namespace panix\mod\shop\models;

use Yii;
use panix\mod\shop\models\query\ProductQuery;
use yii\db\ActiveRecord;

/**
 * Class ProductPrices
 *
 * @property integer $id
 * @property integer $from
 * @property string $value
 * @property integer $product_id
 *
 * @package panix\mod\shop\models
 */
class ProductPrices extends ActiveRecord
{


    const route = '/admin/shop/default';
    const MODULE_ID = 'shop';

    public static function find2()
    {
        return new ProductQuery(get_called_class());
    }


    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%shop__product_prices}}';
    }

    /**
     * Replaces comma to dot
     * @param $attr
     */
    public function commaToDot($attr)
    {
        $this->$attr = str_replace(',', '.', $this->$attr);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['value', 'commaToDot'],
            [['from', 'value'], 'required'],
            [['from'], 'integer'],
            [['value'], 'safe'],
        ];
    }


}
