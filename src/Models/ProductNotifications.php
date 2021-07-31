<?php

namespace panix\mod\shop\models;

use Yii;
use panix\engine\db\ActiveRecord;

/**
 * This is the model class for table "notifications".
 *
 * The followings are the available columns in table 'notifications':
 * @property integer $id
 * @property integer $product_id
 * @property string $email
 */
class ProductNotifications extends ActiveRecord
{

    const MODULE_ID = 'cart';

    public function init()
    {
        if (!Yii::$app->user->isGuest) {
            $this->email = Yii::$app->user->email;
        }
        parent::init();
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%shop__product_notify}}';
    }

    /**
     * @inheritdoc
     */
    public static function find()
    {
        return new query\ProductNotificationsQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['email', 'required'],
            ['email', 'string', 'max' => 255],
            ['email', 'email'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    /**
     * @return \yii\data\Sort
     */
    public static function getSort()
    {
        return new \yii\data\Sort([
            'attributes' => [
                //'totalEmails',
                'name' => [
                    'asc' => ['product.name' => SORT_ASC],
                    'desc' => ['product.name' => SORT_DESC],
                ],
                'product.quantity' => [
                    'asc' => ['quantity' => SORT_ASC],
                    'desc' => ['quantity' => SORT_DESC],
                ],
                'product.availability' => [
                    'asc' => ['availability' => SORT_ASC],
                    'desc' => ['availability' => SORT_DESC],
                ],

            ],
        ]);//
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return array(
            'id' => 'ID',
            'product_id' => Yii::t('app/default', 'Продукт'),
            'product' => Yii::t('app/default', 'Продукт'),
            'image' => Yii::t('app/default', 'Изображение'),
            'name' => Yii::t('app/default', 'Название'),
            'email' => Yii::t('app/default', 'Email'),
            'totalEmails' => Yii::t('app/default', 'Количество подписчиков')
        );
    }

    public function getTotalEmails()
    {
        return ProductNotifications::find()->where(['product_id' => $this->product_id])->count();
    }


    /**
     * Check if email exists in list for current product
     */
    public function hasEmail()
    {
        return ProductNotifications::find()
                ->where([
                    'email' => $this->email,
                    'product_id' => $this->product_id
                ])->count() > 0;
    }

}
