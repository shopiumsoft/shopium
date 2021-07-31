<?php

namespace Shopium\Cart\Models;

use Yii;
use panix\engine\CMS;
use panix\mod\shop\models\Product;
use panix\engine\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use panix\engine\Html;
use yii\helpers\Url;

/**
 * Class OrderProduct
 *
 * @property integer $order_id
 * @property integer $product_id
 * @property integer $configurable_id
 * @property integer $currency_id
 * @property integer $supplier_id
 * @property float $currency_rate
 * @property string $name
 * @property string $configurable_name
 * @property integer $quantity Quantity products
 * @property float $price Products price
 * @property float $price_purchase
 * @property string $configurable_data
 * @property string $sku Article product
 * @property string $variants
 * @property Product $originalProduct
 * @property Product $configureProduct
 * @property Order $order
 * @property string $attributes_data
 *
 * @package panix\mod\cart\models
 */
class OrderProduct extends ActiveRecord
{

    const MODULE_ID = 'cart';

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%order__product}}';
    }

    public static function find()
    {
        return new query\OrderProductQuery(get_called_class());
    }

    public function getOrder()
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }

    public function getOriginalProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getPriceDisplay()
    {
        if ($this->currency_id && $this->currency_rate) {
            return $this->price * $this->currency_rate;
        } else {
            return $this->price;
        }
    }

    /**
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        $this->order->updateTotalPrice();
        $this->order->updateDeliveryPrice();

        if ($this->isNewRecord) {
            $product = Product::findOne($this->product_id);

            if ($product->added_to_cart_count == Yii::$app->settings->get('shop', 'added_to_cart_count')) {
                $product->added_to_cart_date = time();
                $product->save(false);
            }

            $product->decreaseQuantity();

        }

        return parent::afterSave($insert, $changedAttributes);
    }


    public function afterDelete()
    {
        if ($this->order) {
            $this->order->updateTotalPrice();
            $this->order->updateDeliveryPrice();
        }

        return parent::afterDelete();
    }

    /**
     * Render full name to present product on order view
     *
     * @param bool $appendConfigurableName
     * @return string
     */
    public function getRenderFullName($appendConfigurableName = true)
    {

        if ($this->getProduct()) {
            $result = \yii\helpers\Html::a($this->name, $this->getProduct()->getUrl(), ['target' => '_blank']);
        } else {
            $result = $this->name;
        }


        if (!empty($this->configurable_name) && $appendConfigurableName)
            $result .= '<br/>' . $this->configurable_name;

        $variants = unserialize($this->variants);

        if ($this->configurable_data !== '' && is_string($this->configurable_data))
            $this->configurable_data = unserialize($this->configurable_data);

        if (!is_array($variants))
            $variants = [];

        if (!is_array($this->configurable_data))
            $this->configurable_data = [];

        $variants = array_merge($variants, $this->configurable_data);

        if (!empty($variants)) {
            foreach ($variants as $key => $value)
                $result .= "<br/> - {$key}: {$value}";
        }

        return $result;
    }

    public function getCategories()
    {
        $content = [];
        if ($this->getProduct()) {
            foreach ($this->getProduct()->categories as $c) {
                $content[] = $c->name;
            }
        }
        return implode(', ', $content);
    }

    public function getConfigureProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'configurable_id']);
    }

    public function getVariantsConfigure()
    {
        //if (!empty($this->configurable_name) && $appendConfigurableName)
        //    $result .= '<br/>' . $this->configurable_name;

        $variants = unserialize($this->variants);

        if ($this->configurable_data !== '' && is_string($this->configurable_data))
            $this->configurable_data = unserialize($this->configurable_data);

        if (!is_array($variants))
            $variants = [];

        if (!is_array($this->configurable_data))
            $this->configurable_data = [];

        $variants = array_merge($variants, $this->configurable_data);

        // if (!empty($variants)) {
        //  foreach ($variants as $key => $value)
        // $result .= "<br/> - {$key}: {$value}";
        // }
//CMS::dump($variants);die;
        return $variants;
    }

    public function getConfiguration()
    {
        if ($this->configurable_data !== '' && is_string($this->configurable_data))
            $this->configurable_data = unserialize($this->configurable_data);

        return $this->configurable_data;
    }

    public function getProductAttributes()
    {
        return json_decode($this->attributes_data);
    }


    public function getProductName($absoluteUrl = false, $linkOptions = [])
    {
        if ($this->configurable_id) {
            if ($this->id != $this->configurable_id) {
                return Html::a($this->configureProduct->name, Url::to($this->configureProduct->getUrl(), $absoluteUrl), $linkOptions);
            }
        } elseif ($this->getProduct()) {
            return Html::a($this->getProduct()->name, Url::to($this->getProduct()->getUrl(), $absoluteUrl), $linkOptions);
        }
        return $this->name;
    }


    public function getProductUrl()
    {
        if ($this->configurable_id) {
            if ($this->id != $this->configurable_id) {
                return $this->configureProduct->getUrl();
            }
        } elseif ($this->getProduct()) {
            return $this->getProduct()->getUrl();
        }
        return [];
    }


    public function getProductImage($size = null)
    {
        if ($this->configurable_id) {
            if ($this->id != $this->configurable_id) {
                return ($this->configureProduct) ? $this->configureProduct->getMainImage($size)->url : CMS::placeholderUrl(['size' => $size]);
            }
        } elseif ($this->getProduct()) {
            return $this->getProduct()->getMainImage($size)->url;
        }

    }

    public function getProduct()
    {
        if ($this->originalProduct) {
            return $this->originalProduct;
        } else {
            return false;
        }
    }

    public function getAttributesProduct()
    {
        $items = [];
        if (isset($this->productAttributes->attributes)) {
            $attributesData = (array)$this->productAttributes->attributes;
            $query = \panix\mod\shop\models\Attribute::find();
            $query->where(['IN', 'name', array_keys($attributesData)]);
            $query->displayOnPdf();
            $query->sort();
            $result = $query->all();

            foreach ($result as $q) {
                $items[] = [
                    'title' => $q->title,
                    'value' => $q->renderValue($attributesData[$q->name])
                ];
            }
        }
        return $items;
    }

    public function getGridColumns()
    {

        //  $price_max = self::find()->aggregatePrice('MAX')->asArray()->one();
        //  $price_min = self::find()->aggregatePrice('MIN')->asArray()->one();

        $columns = [];

        $columns['id'] = [
            'attribute' => 'id',
            'format' => 'raw',
            'contentOptions' => ['class' => 'text-center image'],
            'value' => function ($model) {
                return $model->id;
            },
        ];

        $columns['image'] = [
            'class' => 'panix\engine\grid\columns\ImageColumn',
            'format' => 'raw',
            'contentOptions' => ['class' => 'text-center image'],
            'value' => function ($model) {
                /** @var \panix\mod\shop\models\Product $model */
                return $model->renderGridImage();
            },
        ];
        $columns['name'] = [
            'attribute' => 'name',
            'format' => 'raw',
            'contentOptions' => ['class' => 'text-left'],
            'value' => function ($model) {
                /** @var \panix\mod\shop\models\Product $model */
                return $model->name;
            },
        ];
        $columns['sku'] = [
            'attribute' => 'sku',
            'format' => 'raw',
            'contentOptions' => ['class' => 'text-left'],
            'value' => function ($model) {
                /** @var \panix\mod\shop\models\Product $model */
                return $model->sku;
            },
        ];
        $columns['price'] = [
            'attribute' => 'price',
            'format' => 'raw',
            'contentOptions' => ['class' => 'text-center'],
            'value' => function ($model) {
                /** @var \panix\mod\shop\models\Product $model */
                $discount = '';
                if ($model->hasDiscount) {
                    $discount = 'Скидка ' . $model->discountSum;
                }
                $html = $discount;
                $html .= '<div class="input-group">';
                $html .= Html::textInput("price_{$model->id}", $model->getFrontPrice(), ['id' => "price_{$model->id}", 'class' => 'form-control']);
                $html .= '<div class="input-group-append">';
                $html .= '<span class="input-group-text">' . (($model->currency_id) ? Yii::$app->currency->getById($model->currency_id)->iso : Yii::$app->currency->main['iso']) . '</span>';
                $html .= '</div></div>';
                return $html;
            }
        ];
        $columns['quantity'] = [
            'attribute' => 'quantity',
            'format' => 'raw',
            'contentOptions' => ['class' => 'text-center'],
            'value' => function ($model) {
                /** @var \panix\mod\shop\models\Product $model */
                return \yii\jui\Spinner::widget([
                    'id' => "count_{$model->id}",
                    'name' => "count_{$model->id}",
                    'value' => 1,
                    'clientOptions' => ['max' => 999, 'min' => 1],
                    'options' => ['class' => 'cart-spinner']
                ]);
            }
        ];


        $columns['DEFAULT_CONTROL'] = [
            'class' => 'panix\engine\grid\columns\ActionColumn',
            'template' => '{add}',
            // 'filter' => false,
            'buttons' => [
                'add' => function ($url, $data, $key) {
                    return Html::a(Html::icon('add'), $data->id, [
                        'title' => Yii::t('yii', 'VIEW'),
                        'class' => 'btn btn-sm btn-success addProductToOrder',
                        'onClick' => 'return addProductToOrder(this, ' . Yii::$app->request->get('id') . ');'
                    ]);
                }
            ]
        ];

        return $columns;
    }
}
