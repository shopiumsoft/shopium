<?php

namespace Shopium\Models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ProductAttributesEav".
 */
class ProductAttributesEav extends ActiveRecord
{

    public static function tableName()
    {
        return '{{%shop__product_attribute_eav}}';
    }

}
