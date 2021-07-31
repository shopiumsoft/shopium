<?php

namespace Shopium\Models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ProductCategoryRef".
 *
 * The followings are the available columns in table 'ProductCategoryRef':
 * @property integer $id
 * @property integer $category
 * @property integer $product
 * @property boolean $is_main
 * @property boolean $switch
 */
class ProductCategoryRef extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%shop__product_category_ref}}';
    }

    public function getCountProducts2()
    {
        return $this->hasMany(static::class, ['category' => 'id'])->count();
    }
    /*  public function relations() {
          return array(
              'active' => array(self::STAT, 'Product', 'id', 'condition'=>'`products`.`switch`=1'),
              'countProducts' => array(self::HAS_MANY, 'ProductCategoryRef', 'category'),
          );
      }*/
}
