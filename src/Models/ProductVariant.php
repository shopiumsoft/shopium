<?php

namespace panix\mod\shop\models;

/**
 * This is the model class for table "ProductVariant".
 *
 * The followings are the available columns in table 'ProductVariant':
 * @property integer $id
 * @property integer $attribute_id
 * @property integer $option_id
 * @property integer $product_id
 * @property float $price
 * @property integer $price_type
 * @property string $sku
 * @property Attribute $productAttribute
 * @property AttributeOption $option
 */
class ProductVariant extends \yii\db\ActiveRecord
{


    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%shop__product_variant}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['attribute_id', 'option_id', 'product_id', 'price', 'price_type'], 'required'],
            //[['attribute_id', 'option_id', 'product_id', 'price_type'], 'numerical', 'integerOnly' => true],
            //['price', 'numerical'],
            ['sku', 'string', 'max' => 255],
            ['currency_id', 'default'],
            [['id', 'attribute_id', 'option_id', 'product_id', 'price', 'price_type', 'sku', 'currency_id'], 'safe'],
        ];
    }

    public function getProductAttribute()
    {
        return $this->hasOne(Attribute::class, ['id' => 'attribute_id']);
    }

    public function getOption()
    {
        return $this->hasOne(AttributeOption::class, ['id' => 'option_id']);
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return array(
            'id' => 'ID',
            'attribute_id' => 'Attribute',
            'option_id' => 'Option',
            'product_id' => 'Product',
            'price' => 'Price',
            'price_type' => 'Price Type',
            'sku' => 'Sku',
        );
    }

}