<?php

namespace panix\mod\shop\models;

use panix\mod\shop\components\ExternalFinder;
use Yii;
use panix\engine\db\ActiveRecord;
use panix\engine\Html;

/**
 * Class Supplier
 *
 * @property integer $id
 * @property string $name
 * @property string $address
 * @property string $phone
 * @property Product $productsCount Counter
 *
 * @package panix\mod\shop\models
 */
class Supplier extends ActiveRecord
{

    const MODULE_ID = 'shop';
    const route = '/admin/shop/supplier';

    public function getGridColumns()
    {
        return [
            'name' => [
                'attribute' => 'name',
                'contentOptions' => ['class' => 'text-left'],
            ],
            'address' => [
                'attribute' => 'address',
                'contentOptions' => ['class' => 'text-left'],
            ],
            'phone' => [
                'attribute' => 'phone',
                'format' => 'raw',
                'contentOptions' => ['class' => 'text-center'],
                'value' => function ($model) {
                    /** @var $model self */
                    if ($model->phone)
                        return Html::tel($model->phone);
                }
            ],
            'email' => [
                'format' => 'email',
                'attribute' => 'email',
                'contentOptions' => ['class' => 'text-center'],
            ],
            'products' => [
                'header' => static::t('PRODUCTS_COUNT'),
                'format' => 'html',
                'attribute' => 'productsCount',
                'contentOptions' => ['class' => 'text-center'],
                'value' => function ($model) {
                    return Html::a($model->productsCount, ['/admin/shop/product', 'ProductSearch[supplier_id]' => $model->id]);
                }
            ],
            'DEFAULT_CONTROL' => [
                'class' => 'panix\engine\grid\columns\ActionColumn',
            ],
            'DEFAULT_COLUMNS' => [
                ['class' => 'panix\engine\grid\columns\CheckboxColumn'],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%shop__suppliers}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['address', 'name'], 'trim'],
            ['phone', '\panix\ext\telinput\PhoneInputValidator'],
            [['email'], 'email'],
            [['address'], 'string'],
            [['address'], 'string', 'max' => 255],
            [['name', 'address'], 'safe'],
        ];
    }

    public function getProductsCount()
    {
        return $this->hasOne(Product::class, ['supplier_id' => 'id'])->count();
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        if (Yii::$app->hasModule('csv')) {
            $external = new ExternalFinder('{{%csv}}');
            $external->deleteObject(ExternalFinder::OBJECT_SUPPLIER, $this->id);
        }
        parent::afterDelete();
    }
}
