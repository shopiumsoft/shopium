<?php

namespace Shopium\Models;

use panix\mod\images\models\Image;
use panix\mod\shop\components\ExternalFinder;
use panix\mod\shop\models\query\ProductReviewsQuery;
use panix\mod\sitemap\behaviors\SitemapBehavior;
use panix\mod\user\models\User;
use Yii;
use panix\engine\CMS;
use panix\mod\shop\models\query\ProductQuery;
use yii\caching\DbDependency;
use yii\db\Exception;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use panix\engine\db\ActiveRecord;

/**
 * Class Product
 * @property integer $id Product id
 * @property integer $manufacturer_id Manufacturer
 * @property integer $type_id Type
 * @property integer $supplier_id Supplier
 * @property integer $currency_id Currency
 * @property Currency $currency
 * @property integer $use_configurations
 * @property string $slug
 * @property string $name Product name
 * @property string $short_description Product short_description
 * @property string $full_description Product full_description
 * @property float $price Price
 * @property float $max_price Max price
 * @property float $price_purchase
 * @property boolean $unit Unit
 * @property boolean $sku Product article
 * @property integer $quantity
 * @property integer $availability
 * @property integer $label
 * @property integer $is_condition
 * @property integer $main_category_id
 * @property integer $auto_decrease_quantity
 * @property integer $views Views product on frontend
 * @property integer $created_at Date created
 * @property integer $updated_at Date updated
 * @property boolean $switch On/Off object
 * @property integer $added_to_cart_count
 * @property integer $votes
 * @property integer $rating
 * @property Manufacturer[] $manufacturer
 * @property Supplier[] $supplier
 * @property string $discount Discount
 * @property string $video Youtube video URL
 * @property boolean $hasDiscount
 * @property object|bool $hasMarkup See module markup
 * @property float $originalPrice See [[\panix\mod\discounts\components\DiscountBehavior]]
 * @property float $discountPrice
 * @property string $discountSum
 * @property integer $ordern Sorting drag-and-drop
 * @property boolean $isAvailable
 * @property Category $categories
 * @property array $eavAttributes
 * @property Kit $kit
 * @property ProductPrices[] $prices
 * @property ProductVariant[] $variants
 * @property ProductReviews[] $reviews
 * @property ProductType $type
 * @property string $ratingScore
 * @property RelatedProduct[] $relatedProducts
 */
class Product extends ActiveRecord
{

    use traits\ProductTrait;

    const SCENARIO_INSERT = 'insert';

    /**
     * @var array of attributes used to configure product
     */
    private $_configurable_attributes;
    private $_configurable_attribute_changed = false;

    /**
     * @var array
     */
    private $_configurations;
    private $_related;
    private $_kit;
    public $file;

    public $hasDiscount = null;
    public $discountPrice;
    public $originalPrice;
    public $discountSum;

    const route = '/admin/shop/default';
    const MODULE_ID = 'shop';

    const STATUS_IN_STOCK = 1;
    const STATUS_PREORDER = 2;
    const STATUS_OUT_STOCK = 3;
    const STATUS_ARCHIVE = 4;

    public static function find()
    {
        return new ProductQuery(get_called_class());
    }

    public function labels()
    {
        $labelsList = [];
        /** @var \panix\mod\discounts\components\DiscountBehavior|self $this */
        $new = Yii::$app->settings->get('shop', 'label_expire_new');

        if ($new) {
            if ((time() - 86400 * $new) <= $this->created_at) {
                $labelsList['new'] = [
                    //'class' => 'success',
                    'value' => self::t('LABEL_NEW'),
                    'label' => self::t('LABEL_NEW'),
                    // 'title' => Yii::t('app/default', 'FROM_BY', Yii::$app->formatter->asDate(date('Y-m-d', $this->created_at))) . ' ' . Yii::t('app/default', 'TO_BY', Yii::$app->formatter->asDate(date('Y-m-d', $this->created_at + (86400 * $new))))
                ];
            }
        }

        if ($this->hasDiscount) {
            $labelsList['discount']['value'] = '-' . $this->discountSum;
            $labelsList['discount']['label'] = self::t('LABEL_DISCOUNT');
            if (isset($this->discountEndDate)) {
                $labelsList['discount']['title'] = '-' . $this->discountSum . ' до ' . $this->discountEndDate;
            }
        }

        foreach (self::getLabelByName() as $key => $label) {
            $labelsList[$key]['label'] = $label;
            $labelsList[$key]['value'] = $label;
        }
        return $labelsList;
    }

    public function getIsAvailable()
    {
        return $this->availability == self::STATUS_IN_STOCK;
    }

    public function beginCartForm()
    {
        $html = '';
        $html .= Html::beginForm(['/cart/add'], 'post');
        $html .= Html::hiddenInput('product_id', $this->id);
        //$html .= Html::hiddenInput('product_price', $this->price);
        //$html .= Html::hiddenInput('use_configurations', $this->use_configurations, ['id' => 'use_configurations-' . $this->id]);
        //$html .= Html::hiddenInput('use_configurations', 0);
        $configurable_id = 0;
        if ($this->use_configurations) {
            $configurable_id = $this->id;
        }
        $html .= Html::hiddenInput('configurable_id', $configurable_id);
        return $html;
    }

    public function endCartForm()
    {
        return Html::endForm();
    }

    public static function getSort()
    {
        return new \yii\data\Sort([
            'defaultOrder' => [
                'ordern' => SORT_DESC,
            ],
            'attributes' => [
                // '*',
                'price' => [
                    'asc' => ['price' => SORT_ASC],
                    'desc' => ['price' => SORT_DESC],
                    //'default' => SORT_ASC,
                    //'label' => 'Цена1',
                ],
                'sku' => [
                    'asc' => ['sku' => SORT_ASC],
                    'desc' => ['sku' => SORT_DESC],
                ],
                'ordern' => [
                    'asc' => ['ordern' => SORT_ASC],
                    'desc' => ['ordern' => SORT_DESC],
                ],
                'type_id' => [
                    'asc' => ['type_id' => SORT_ASC],
                    'desc' => ['type_id' => SORT_DESC],
                    'label' => 'по типу'
                ],
                'created_at' => [
                    'asc' => ['created_at' => SORT_ASC],
                    'desc' => ['created_at' => SORT_DESC],
                    'label' => 'по дате добавления'
                ],
                'updated_at' => [
                    'asc' => ['updated_at' => SORT_ASC],
                    'desc' => ['updated_at' => SORT_DESC],
                    'label' => 'по дате изменения'
                ],
                'name' => [
                    'default' => SORT_ASC,
                    //'asc' => ['translation.name' => SORT_ASC],
                    //'desc' => ['translation.name' => SORT_DESC],
                    'asc' => ['name_ru' => SORT_ASC],
                    'desc' => ['name_ru' => SORT_DESC],
                ],
                'commentsCount',
            ],
        ]);
    }

    /* public function getHasDiscount2()
     {
         if (!empty($this->discount)) {
             return $this->discount;
         }
         return null;
     }

     public function getHasDiscount()
     {
         if (!empty($this->discount)) {
             return $this->_hasDiscount;
         }
         return null;
     }

     public function setHasDiscount($v){
         $this->_hasDiscount = $v;
     }


    public function discount()
    {
        if ($this->discount) {
            $sum = $this->discount;
            if ('%' === substr($sum, -1, 1)) {
                $sum = $this->price * ((double)$sum) / 100;
            }
            $this->discountSum = $sum;
            $this->discountPrice = $this->price - $sum;
        }
    }*/

    public function afterFind()
    {
        // $this->discount();
        if ($this->discount) {
            $sum = $this->discount;
            if ('%' === substr($sum, -1, 1)) {
                $sum = $this->price * ((double)$sum) / 100;
            }
            $this->discountSum = $this->discount;
            $this->discountPrice = $this->price - $sum;
            $this->originalPrice = $this->price;
            $this->hasDiscount = $this->discount;
        }

        parent::afterFind();
    }

    public function getMainImage($size = false)
    {
        /** @var $image \panix\mod\images\behaviors\ImageBehavior|\panix\mod\images\models\Image */
        $image = $this->getImageData($size);

        $result = [];
        if ($image) {
            $result['url'] = $image->url;
            $result['title'] = (isset($image->model) && $image->model->alt_title) ? $image->model->alt_title : $this->name;
        } else {
            $result['url'] = CMS::placeholderUrl(['size' => $size, 'bg' => 'fff']);
            $result['title'] = $this->name;
        }

        return (object)$result;
    }

    /**
     * @param string $size Default value 50x50.
     * @return string
     */
    public function renderGridImage($size = '50x50')
    {
        $small = $this->getMainImage($size);
        $big = $this->getMainImage();

        return Html::a(Html::img($small->url, ['alt' => $small->title, 'class' => 'img-thumbnail']), $big->url, ['title' => $this->name, 'data-fancybox' => 'gallery']);
    }


    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%shop__product}}';
    }

    public function getUrl()
    {
        return ['/shop/product/view', 'slug' => $this->slug, 'id' => $this->id];
    }

    /* public function transactions() {
      return [
      self::SCENARIO_DEFAULT => self::OP_INSERT | self::OP_UPDATE,
      ];
      } */


    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_INSERT] = ['use_configurations'];
        $scenarios['duplicate'] = [];
        $scenarios['configurable'] = ['name', 'sku', 'slug', 'main_category_id'];
        return $scenarios;
    }

    /**
     * Decrease product quantity when added to cart
     */
    public function decreaseQuantity()
    {
        if ($this->auto_decrease_quantity && (int)$this->quantity > 0) {
            $this->quantity--;
            $this->save(false);
        }
    }

    /**
     * @param string $img default, hqdefault, mqdefault, sddefault, maxresdefault OR 0,1,2,3
     * @return string
     */
    public function getVideoPreview($img = 'default')
    {
        return "https://img.youtube.com/vi/" . CMS::parse_yturl($this->video) . "/{$img}.jpg";
    }


    /**
     * @inheritdoc
     */
    public function rules()
    {

        $rules = [];


        if (!$this->auto) {
            /*$rules[] = ['slug', '\panix\engine\validators\UrlValidator', 'attributeCompare' => 'name'];
            $rules[] = ['slug', 'match',
                'pattern' => '/^([a-z0-9-])+$/i',
                'message' => Yii::t('app/default', 'PATTERN_URL')
            ];*/
            $rules[] = [['name'], 'required']; //, 'slug'
        }
        $rules[] = [['main_category_id', 'price', 'unit'], 'required', 'on' => 'default'];


        //$rules[] = [['slug'], 'unique'];
        $rules[] = ['price', 'commaToDot'];
        $rules[] = [['name', 'slug', 'video'], 'string', 'max' => 255];
        $rules[] = ['video', 'url'];
        $rules[] = [['image'], 'image'];

        $rules[] = [['name', 'slug'], 'trim'];
        $rules[] = [['full_description', 'length', 'width', 'height', 'weight'], 'string'];
        $rules[] = ['use_configurations', 'boolean', 'on' => self::SCENARIO_INSERT];
        $rules[] = ['enable_comments', 'boolean'];
        $rules[] = [['unit'], 'default', 'value' => 1];
        // $rules[] = ['ConfigurationsProduct', 'each', 'rule' => ['integer']];
        $rules[] = [['sku', 'full_description', 'video', 'price_purchase', 'label', 'discount', 'markup'], 'default']; // установим ... как NULL, если они пустые
        $rules[] = [['price', 'price_purchase'], 'double'];
        $rules[] = [['manufacturer_id', 'type_id', 'quantity', 'views', 'availability', 'added_to_cart_count', 'ordern', 'category_id', 'currency_id', 'supplier_id', 'weight_class_id', 'length_class_id', 'is_condition'], 'integer'];
        $rules[] = [['id', 'name', 'slug', 'full_description', 'use_configurations', 'length', 'width', 'height', 'weight'], 'safe'];

        return $rules;
    }


    public function getLabel()
    {
        if ($this->label)
            return explode(',', $this->label);
        return [];
    }

    public function getLabelByName()
    {
        return ArrayHelper::filter(self::getLabelList(), $this->getLabel());
    }

    public static function getLabelList()
    {
        return [
            'top_sale' => self::t('LABEL_TOP_SALE'),
            'hit_sale' => self::t('LABEL_HIT_SALE'),
            'sale' => self::t('LABEL_SALE')
        ];
    }

    public function getUnits()
    {
        return [
            1 => self::t('UNIT_THING'),
            2 => self::t('UNIT_METER'),
            3 => self::t('UNIT_BOX'),
        ];
    }

    public function getConditions()
    {
        return [
            0 => self::t('CONDITION_NEW'),
            1 => self::t('CONDITION_REFURBISHED'),
            2 => self::t('CONDITION_USED'),
        ];
    }

    public function processVariants()
    {
        $result = [];
        foreach ($this->variants as $v) {
            if (isset($v->productAttribute->id)) {
                $result[$v->productAttribute->id]['attribute'] = $v->productAttribute;
                $result[$v->productAttribute->id]['options'][] = $v;
            }
        };
        return $result;
    }

    public function beforeValidate()
    {
        // For configurable product set 0 price
        // if ($this->use_configurations)
        //     $this->price = 0;


        $this->slug = CMS::slug($this->name);


        return parent::beforeValidate();
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /* public function getCategory2() {
      return $this->hasOne(Category::className(), ['id' => 'category_id']);
      } */
    public function getKit()
    {
        return $this->hasMany(Kit::class, ['owner_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getReviews()
    {
        return $this->hasMany(ProductReviews::class, ['product_id' => 'id'])->orderBy(['id' => SORT_DESC]);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getManufacturer()
    {
        return $this->hasOne(Manufacturer::class, ['id' => 'manufacturer_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSupplier()
    {
        return $this->hasOne(Supplier::class, ['id' => 'supplier_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getType()
    {
        return $this->hasOne(ProductType::class, ['id' => 'type_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCurrency()
    {
        return $this->hasOne(Currency::class, ['id' => 'currency_id']);

    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getType2()
    {
        return $this->hasOne(ProductType::class, ['type_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRelated()
    {
        return $this->hasMany(RelatedProduct::class, ['related_id' => 'id']);
    }

    /**
     * @return int|string
     */
    public function getRelatedProductCount()
    {
        return $this->hasMany(RelatedProduct::class, ['product_id' => 'id'])->count();
    }

    public function getRelatedProducts()
    {
        return $this->hasMany(Product::class, ['id' => 'related_id'])
            ->viaTable(RelatedProduct::tableName(), ['product_id' => 'id']);
    }


    public function getKitProducts()
    {
        return $this->hasMany(Product::class, ['id' => 'product_id'])
            ->viaTable(Kit::tableName(), ['owner_id' => 'id']);
    }

    public function getCategorization()
    {
        return $this->hasMany(ProductCategoryRef::class, ['product' => 'id']);
    }

    public function getCategories()
    {
        return $this->hasMany(Category::class, ['id' => 'category'])->via('categorization');
    }

    public function getPrices()
    {
        return $this->hasMany(ProductPrices::class, ['product_id' => 'id']);
    }

    public function getMainCategory()
    {
        return $this->hasOne(Category::class, ['id' => 'category'])
            ->via('categorization', function ($query) {
                /** @var Query $query */
                $query->where(['is_main' => 1]);
            });
    }

    public function getVariants()
    {
        return $this->hasMany(ProductVariant::class, ['product_id' => 'id'])
            ->joinWith(['productAttribute', 'option'])
            ->orderBy(AttributeOption::tableName() . '.ordern');
    }

//'variants' => array(self::HAS_MANY, 'ProductVariant', array('product_id'), 'with' => array('attribute', 'option'), 'order' => 'option.ordern'),

    /**
     * @param array $prices
     */
    public function processPrices(array $prices = [])
    {
        $dontDelete = [];
        foreach ($prices as $index => $price) {
			if(isset($price['value'])){
				if ($price['value'] > 0) {

					$record = ProductPrices::find()->where(array(
						'id' => $index,
						'product_id' => $this->id,
					))->one();

					if (!$record) {
						$record = new ProductPrices;
					}
					$record->from = $price['from'];
					$record->value = $price['value'];
					$record->product_id = $this->id;
					$record->save();

					$dontDelete[] = $record->id;
				}
			}
        }

        // Delete not used relations
        if (sizeof($dontDelete) > 0) {
            ProductPrices::deleteAll(
                ['AND', 'product_id=:id', ['NOT IN', 'id', $dontDelete]], [':id' => $this->id]);
        } else {
            // Delete all relations
            ProductPrices::deleteAll('product_id=:id', [':id' => $this->id]);
        }

    }

    /**
     * Set product categories and main category
     * @param array $categories ids.
     * @param integer $main_category Main category id.
     */
    public function setCategories(array $categories, $main_category)
    {
        $notDelete = [];


        if (!Category::find()->where(['id' => $main_category])->count())
            $main_category = 1;

        if (!in_array($main_category, $categories))
            array_push($categories, $main_category);


        foreach ($categories as $category) {

            $count = ProductCategoryRef::find()->where([
                'category' => (int)$category,
                'product' => $this->id,
            ])->count();


            if (!$count) {
                $record = new ProductCategoryRef;
                $record->category = (int)$category;
                $record->product = $this->id;
                if ($this->scenario == 'duplicate') {
                    $record->switch = 1;
                } else {
                    $record->switch = ($this->switch) ? $this->switch : 1;
                }
                $record->save(false);
            }

            $notDelete[] = (int)$category;
        }

        // Clear main category
        ProductCategoryRef::updateAll([
            'is_main' => 0,
            'switch' => ($this->switch) ? $this->switch : 1
        ], 'product=:p', [':p' => $this->id]);

        // Set main category
        ProductCategoryRef::updateAll([
            'is_main' => 1,
            'switch' => ($this->switch) ? $this->switch : 1,
        ], 'product=:p AND category=:c', [':p' => $this->id, ':c' => $main_category]);

        // Delete not used relations
        if (count($notDelete) > 0) {

            ProductCategoryRef::deleteAll(
                ['AND', 'product=:id', ['NOT IN', 'category', $notDelete]], [':id' => $this->id]);

        } else {
            // Delete all relations
            ProductCategoryRef::deleteAll(['product' => $this->id]);
        }

    }

    public function setRelatedProducts($ids = [])
    {
        $this->_related = $ids;
    }

    private function clearRelatedProducts()
    {
        RelatedProduct::deleteAll(['product_id' => $this->id]);
        if (Yii::$app->settings->get('shop', 'product_related_bilateral')) {
            RelatedProduct::deleteAll(['related_id' => $this->id]);
        }
    }

    public function setKitProducts($ids = [])
    {
        $this->_kit = $ids;
    }

    private function clearKitProducts()
    {
        Kit::deleteAll(['owner_id' => $this->id]);

    }

    public $auto = false;

    /*
    public function getAuto222(){
        if (Yii::$app->id != 'console') {
            $type_id = $this->type_id;
            if ($this->isNewRecord && isset(Yii::$app->request->get('Product')['type_id'])) {
                $type_id = Yii::$app->request->get('Product')['type_id'];
            }

            $type = ProductType::findOne($type_id);

            if ($type && $type->product_name)
                $this->auto = true;
        }
    }*/

    public function afterSave($insert, $changedAttributes)
    {

        // Process related products
        if ($this->_related !== null) {
            $this->clearRelatedProducts();

            foreach ($this->_related as $id) {
                $related = new RelatedProduct;
                $related->product_id = $this->id;
                $related->related_id = (int)$id;

                if ($related->save()) {

                    //двустороннюю связь между товарами
                    if (Yii::$app->settings->get('shop', 'product_related_bilateral')) {
                        $related = new RelatedProduct;

                        $related->product_id = (int)$id;
                        $related->related_id = $this->id;
                        if (!$related->save()) {
                            throw new \yii\base\Exception('Error save product relation');
                        }
                    }
                } else {

                }
            }
        }

        if ($this->_kit !== null) {
            $this->clearKitProducts();

            foreach ($this->_kit as $id) {
                $kit = new Kit;
                $kit->owner_id = $this->id;
                $kit->product_id = (int)$id;
                $kit->save();
            }
        }
        // Save configurable attributes
        if ($this->_configurable_attribute_changed === true) {
            // Clear
            self::getDb()->createCommand()->delete('{{%shop__product_configurable_attributes}}', ['product_id' => $this->id])->execute();

            foreach ($this->_configurable_attributes as $attr_id) {
                self::getDb()->createCommand()->insert('{{%shop__product_configurable_attributes}}', [
                    'product_id' => $this->id,
                    'attribute_id' => $attr_id
                ])->execute();
            }
        }

        // Process min and max price for configurable product
        // if ($this->use_configurations)
        // $this->updatePrices($this);
        // else {
        // Check if product is configuration

        /* $query = (new Query())
             ->from('{{%shop__product_configurations}} t')
             ->where(['in', 't.configurable_id', [$this->id]])
             ->all();*/


        /* $query = Yii::$app->db->createCommand()
          ->from('{{%shop__product_configurations}} t')
          ->where(['in', 't.configurable_id', [$this->id]])
          ->queryAll();
         */
        /* foreach ($query as $row) {
             $model = Product::findOne($row['product_id']);
             if ($model)
                 $this->updatePrices($model);
         }*/
        // }

        //if ($this->type->product_name) {
        //    $this->name = $this->replaceName();
        //    $this->slug = CMS::slug($this->name);
        //    $this->save(false);
        //}


        //Prices history
        if (isset($changedAttributes['price_purchase'])) {
            if ($this->attributes['price_purchase'] <> $changedAttributes['price_purchase']) {
                static::getDb()->createCommand()->insert('{{%shop__product_price_history}}', [
                    'product_id' => $this->id,
                    'currency_id' => $this->currency_id,
                    'currency_rate' => ($this->currency_id) ? Yii::$app->currency->currencies[$this->currency_id]['rate'] : NULL,
                    'price' => $this->price,
                    //  'price_purchase' => $this->price_purchase,
                    'created_at' => time(),
                    'type' => ($changedAttributes['price_purchase'] < $this->attributes['price_purchase']) ? 1 : 0,
                    'event' => 'product'
                ])->execute();
            }
        }
        if (isset($changedAttributes['discount'])) {
            if ($this->attributes['discount'] <> $changedAttributes['discount']) {


                $sum = $this->attributes['discount'];
                if (strpos($sum, '%')) {
                    $sum = (double)str_replace('%', '', $sum);
                    $this->price -= $this->price * ((double)$sum) / 100;
                }

                static::getDb()->createCommand()->insert('{{%shop__product_price_history}}', [
                    'product_id' => $this->id,
                    'currency_id' => $this->currency_id,
                    'currency_rate' => ($this->currency_id) ? Yii::$app->currency->currencies[$this->currency_id]['rate'] : NULL,
                    'price' => $this->price,
                    // 'price_purchase' => $this->price_purchase,
                    'created_at' => time(),
                    // 'type' => ($changedAttributes['discount'] < $this->attributes['discount']) ? 1 : 0,
                    'event' => 'product_discount'
                ])->execute();
            }
        }
        //$this->name = $this->replaceName();
        //$this->slug = CMS::slug($this->name);

        if (isset($changedAttributes['currency_id'])) {
            if ($this->attributes['currency_id'] <> $changedAttributes['currency_id']) {


                $sum = $this->discount;
                if (strpos($sum, '%')) {
                    $sum = (double)str_replace('%', '', $sum);
                    $this->price -= $this->price * ((double)$sum) / 100;
                }

                static::getDb()->createCommand()->insert('{{%shop__product_price_history}}', [
                    'product_id' => $this->id,
                    'currency_id' => $this->currency_id,
                    'currency_rate' => ($this->currency_id) ? Yii::$app->currency->currencies[$this->currency_id]['rate'] : NULL,
                    'price' => $this->price,
                    // 'price_purchase' => $this->price_purchase,
                    'created_at' => time(),
                    //  'type' => ($changedAttributes['discount'] < $this->attributes['discount']) ? 1 : 0,
                    'event' => 'product_currency'
                ])->execute();
            }
        }
        if (!$insert && isset($changedAttributes['availability']) && false) { // @todo: dev
            if ($this->attributes['availability'] == self::STATUS_IN_STOCK) {
                $records = $this->getNotifications()->all();
                $siteName = Yii::$app->settings->get('app', 'sitename');
                foreach ($records as $row) {
                    if (!$row->product)
                        continue;

                    /**
                     * @var $mailer \yii\swiftmailer\Mailer
                     */
                    $mailer = Yii::$app->mailer;
                    $mailer->htmlLayout = "@app/mail/layouts/html";
                    $mail = $mailer->compose(['html' => "@shop/mail/{$lang}/product_notify"], [
                        'data' => $row,
                        'product' => $row->product,
                        'site_name' => $siteName
                    ]);
                    $mail->setTo($row->email);
                    $mail->setSubject(Yii::t('shop/admin', 'MAIL_PRODUCT_NOTIFY_SUBJECT', [
                        'site_name' => $siteName
                    ]));
                    $mail->send();

                    //$row->delete();
                }
            }

        }
        parent::afterSave($insert, $changedAttributes);
    }

    public function getNotifications()
    {
        return $this->hasMany(ProductNotifications::class, ['product_id' => 'id']);
    }


//

    /**
     * Update price and max_price for configurable product
     * @param Product $model
     */
    public function updatePrices(Product $model)
    {
        $query = (new Query())
            ->select('MIN(price) as min_price, MAX(price) as max_price')
            ->from(self::tableName())
            ->where(['in', 'id', $model->getConfigurations(true)])
            ->one();
        /*$query = (new Query())
            ->select('MIN(t.price) as min_price, MAX(t.price) as max_price')
            ->from('{{%shop__product}} t')
            ->where(['in', 't.id', $model->getConfigurations(true)])
            ->one();*/

        // Update
        static::getDb()->createCommand()->update(self::tableName(), [
            'price' => $query['min_price'],
            'max_price' => $query['max_price']
        ], 'id=:id', [':id' => $model->id])->execute();
    }

    /**
     * @param boolean $reload
     * @return array of product ids
     */
    public function getConfigurations($reload = false)
    {
        if (is_array($this->_configurations) && $reload === false)
            return $this->_configurations;


        $query = (new Query())
            ->select('t.configurable_id')
            ->from('{{%shop__product_configurations}} as t')
            ->where('t.product_id=:id', [':id' => $this->id])
            ->groupBy('t.configurable_id');
        // ->one();
        $this->_configurations = $query->createCommand()->queryColumn();
        /* $this->_configurations = Yii::$app->db->createCommand()
          ->select('t.configurable_id')
          ->from('{{%shop__product_configurations}} t')
          ->where('product_id=:id', array(':id' => $this->id))
          ->group('t.configurable_id')
          ->queryColumn(); */

        return $this->_configurations;
    }

    public function getFrontPrice()
    {
        $currency = Yii::$app->currency;
        //if ($this->hasMarkup) {

        // $this->price = $this->markupPrice;
        // if ($this->hasDiscount) {
        // $this->discountPrice = '123';
        //}
        // }
        if ($this->hasDiscount) {
            $price = $currency->convert($this->discountPrice, $this->currency_id);
        } else {
            $price = $currency->convert($this->price, $this->currency_id);
        }
        return $price;
    }

    public function priceRange()
    {
        $price = $this->getFrontPrice();
        $max_price = Yii::$app->currency->convert($this->max_price);

        // if ($this->use_configurations && $max_price > 0)
        //     return Yii::$app->currency->number_format($price) . ' - ' . Yii::$app->currency->number_format($max_price);

        return Yii::$app->currency->number_format($price);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        $this->clearRelatedProducts();
        RelatedProduct::deleteAll(['related_id' => $this->id]);

        $this->clearKitProducts();
        Kit::deleteAll(['owner_id' => $this->id]);

        // Delete categorization
        ProductCategoryRef::deleteAll([
            'product' => $this->id
        ]);

        // Delete price history
        Yii::$app->db->createCommand()->delete('{{%shop__product_price_history}}', ['product_id' => $this->id])->execute();

        // Clear configurable attributes
        Yii::$app->db->createCommand()->delete('{{%shop__product_configurable_attributes}}', ['product_id' => $this->id])->execute();
        // Delete configurations
        Yii::$app->db->createCommand()->delete('{{%shop__product_configurations}}', ['product_id' => $this->id])->execute();
        Yii::$app->db->createCommand()->delete('{{%shop__product_configurations}}', ['configurable_id' => $this->id])->execute();
        /* if (Yii::app()->hasModule('wishlist')) {
          Yii::import('mod.wishlist.models.WishlistProducts');
          $wishlistProduct = WishlistProducts::model()->findByAttributes(array('product_id' => $this->id));
          if ($wishlistProduct)
          $wishlistProduct->delete();
          }
          // Delete from comapre if install module "comapre"
          if (Yii::app()->hasModule('comapre')) {
          Yii::import('mod.comapre.components.CompareProducts');
          $comapreProduct = new CompareProducts;
          $comapreProduct->remove($this->id);
          } */


        if (Yii::$app->hasModule('wishlist')) {
            Yii::$app->db->createCommand()->delete(\panix\mod\wishlist\models\WishListProducts::tableName(), ['product_id' => $this->id])->execute();
        }
        if (Yii::$app->hasModule('csv')) {
            $external = new ExternalFinder('{{%csv}}');
            $external->deleteObject(ExternalFinder::OBJECT_PRODUCT, $this->id);
        }

        ProductReviews::deleteAll(['product_id'=>$this->id]);

        parent::afterDelete();
    }

    public function setConfigurable_attributes(array $ids)
    {
        $this->_configurable_attributes = $ids;
        $this->_configurable_attribute_changed = true;
    }

    /**
     * @return array
     */
    public function getConfigurable_attributes()
    {
        if ($this->_configurable_attribute_changed === true)
            return $this->_configurable_attributes;

        if ($this->_configurable_attributes === null) {

            $query = new Query;
            $query->select('attribute_id')
                ->from('{{%shop__product_configurable_attributes}}')
                ->where(['product_id' => $this->id])
                ->groupBy('attribute_id');
            $this->_configurable_attributes = $query->createCommand()->queryColumn();
            /*    $this->_configurable_attributes = Yii::app()->db->createCommand()
              ->select('t.attribute_id')
              ->from('{{shop__product_configurable_attributes}} t')
              ->where('t.product_id=:id', array(':id' => $this->id))
              ->group('t.attribute_id')
              ->queryColumn(); */
        }

        return $this->_configurable_attributes;
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {

        if (substr($name, 0, 4) === 'eav_') {

            $table = Attribute::tableName();
            $dependency = new DbDependency();
            $dependency->sql = "SELECT MAX(updated_at) FROM {$table}";


            if ($this->getIsNewRecord())
                return null;

            $attribute = substr($name, 4);
            /** @var \panix\mod\shop\components\EavBehavior $this */
            $eavData = $this->getEavAttributes();

            if (isset($eavData[$attribute]))
                $value = $eavData[$attribute];
            else
                return null;


            $attributeModel = Attribute::getDb()->cache(function ($db) use ($attribute) {
                $q = Attribute::find()->where(['name' => $attribute]);

                $result = $q->one();
                return $result;
            });


            //$attributeModel = Attribute::find()->where(['name' => $attribute])->cache(3600 * 24, $dependency)->one();
            return (object)['name' => $attributeModel->title, 'value' => $attributeModel->renderValue($value)];
            //return $attributeModel->renderValue($value);
        }
        return parent::__get($name);
    }

    public function behaviors()
    {
        $a = [];
        if (Yii::$app->getModule('sitemap')) {
            $a['sitemap'] = [
                'class' => SitemapBehavior::class,
                //'batchSize' => 100,
                'scope' => function ($model) {
                    /** @var \yii\db\ActiveQuery $model */
                    $model->select(['slug', 'updated_at']);
                    $model->where(['switch' => 1]);
                },
                'dataClosure' => function ($model) {
                    /** @var self $model */
                    return [
                        'loc' => $model->getUrl(),
                        'lastmod' => $model->updated_at,
                        'changefreq' => SitemapBehavior::CHANGEFREQ_DAILY,
                        'priority' => 0.9
                    ];
                }
            ];
        }
        // if (Yii::$app->getModule('images'))
        $a['imagesBehavior'] = [
            'class' => '\panix\mod\images\behaviors\ImageBehavior',
            'path' => '@uploads/store/product'
        ];
        $a['slug'] = [
            'class' => '\yii\behaviors\SluggableBehavior',
            'attribute' => 'name',
            'slugAttribute' => 'slug',
        ];
        $a['eav'] = [
            'class' => '\panix\mod\shop\components\EavBehavior',
            'tableName' => ProductAttributesEav::tableName()
        ];
        $a['translate'] = [
            'class' => '\panix\mod\shop\components\TranslateBehavior',
            'translationAttributes' => ['name', 'short_description', 'full_description']
        ];
        if (Yii::$app->getModule('seo'))
            $a['seo'] = [
                'class' => '\panix\mod\seo\components\SeoBehavior',
                'url' => $this->getUrl()
            ];

        if (Yii::$app->getModule('comments')) {
            $a['comments'] = [
                'class' => '\panix\mod\comments\components\CommentBehavior',
                //'handlerClass' => static::class,
                'owner_title' => 'name', // Attribute name to present comment owner in admin panel
            ];
        }
        if (Yii::$app->getModule('markup')) // && Yii::$app->id !== 'console'
            $a['markup'] = [
                'class' => '\panix\mod\markup\components\MarkupBehavior'
            ];

        if (Yii::$app->getModule('discounts')) // && Yii::$app->id !== 'console'
            $a['discounts'] = [
                'class' => '\panix\mod\discounts\components\DiscountBehavior'
            ];


        return ArrayHelper::merge($a, parent::behaviors());
    }

    protected $_discountPrice;

    public function setDiscountPrice($value)
    {
        $this->_discountPrice = $value;
    }

    public function getDiscountPrice()
    {
        return $this->_discountPrice;
    }

    /**
     * Replaces comma to dot
     * @param $attr
     */
    public function commaToDot($attr)
    {
        $this->$attr = str_replace(',', '.', $this->$attr);
    }

    public function getPriceByQuantity($q = 1)
    {
        return ProductPrices::find()
            ->where(['product_id' => $this->id])
            ->andWhere(['<=', 'from', $q])
            ->orderBy(['from' => SORT_DESC])
            ->one();
    }

    /**
     * @param $product Product
     * @param array $variants
     * @param $configuration
     * @param int $quantity
     * @return float|int|mixed|null
     */
    public static function calculatePrices($product, array $variants, $configuration, $quantity = 1)
    {
        if (($product instanceof Product) === false)
            $product = Product::findOne($product);

        if (($configuration instanceof Product) === false && $configuration > 0)
            $configuration = Product::findOne($configuration);

        if ($configuration instanceof Product) {
            //  $result = $configuration->hasDiscount ? $configuration->discountPrice : $configuration->price;
            if ($configuration->currency_id) {
                $result = Yii::$app->currency->convert($configuration->hasDiscount ? $configuration->discountPrice : $configuration->price, $configuration->currency_id);
            } else {
                $result = ($configuration->hasDiscount) ? $configuration->discountPrice : $configuration->price;
            }
        } else {

            // if ($quantity > 1 && ($pr = $product->getPriceByQuantity($quantity))) {
            if ($product->prices && $quantity > 1) {
               // var_dump($quantity);die;
                $pr = $product->getPriceByQuantity($quantity);
                if($pr){
                    $result = Yii::$app->currency->convert($pr->value, $product->currency_id);
                }else{
                    $result = $product->price;
                }

                // if ($product->currency_id) {
                //$result = Yii::$app->currency->convert($pr->value, $product->currency_id);
                //} else {
                //     $result = $pr->value;
                //}
            } else {
                if ($product->currency_id) {
                    $result = Yii::$app->currency->convert($product->hasDiscount ? $product->discountPrice : $product->price, $product->currency_id);
                } else {
                    $result = ($product->hasDiscount) ? $product->discountPrice : $product->price;
                }

            }
        }

        // if $variants contains not models
        if (!empty($variants) && ($variants[0] instanceof ProductVariant) === false)
            $variants = ProductVariant::findAll($variants);

        foreach ($variants as $variant) {
            // Price is percent
            if ($variant->price_type == 1)
                $result += ($result / 100 * $variant->price);
            else
                $result += $variant->price;
        }

        return $result;
    }


    public function getRatingScore()
    {
        return ($this->votes > 0) ? round($this->rating / $this->votes, 1) : 0;
    }

    /*
        public function processConfigurations($productPks)
        {
            // Clear relations
            self::getDb()->createCommand()->delete('{{%shop__product_configurations}}', ['product_id' => $this->id])->execute();

            if (!sizeof($productPks))
                return;

            foreach ($productPks as $k => $pk) {
                self::getDb()->createCommand()->insert('{{%shop__product_configurations}}', [
                    'product_id' => $this->id,
                    'configurable_id' => $pk
                ])->execute();
                if (true) { //recursive
                    //  CMS::dump($this->getConfigurable_attributes());die;
                    self::getDb()->createCommand()->delete('{{%shop__product_configurations}}', ['product_id' => $pk])->execute();
                    $newids = $productPks;
                    $newids[] = $this->id;
                    unset($newids[$k]);
                    foreach ($newids as $pk2) {
                        self::getDb()->createCommand()->insert('{{%shop__product_configurations}}', [
                            'product_id' => $pk,
                            'configurable_id' => $pk2
                        ])->execute();

                        self::getDb()->createCommand()->delete('{{%shop__product_configurable_attributes}}', ['product_id' => $pk])->execute();

                        foreach ($this->getConfigurable_attributes() as $attr_id) {
                            self::getDb()->createCommand()->insert('{{%shop__product_configurable_attributes}}', [
                                'product_id' => $pk,
                                'attribute_id' => $attr_id
                            ])->execute();
                        }
                    }


                }
            }
        }
    */
    public function removeConfigure($id, $action = 'insert')
    {
        $tableName = '{{%shop__product_configurations}}';

        try {
            self::getDb()->createCommand()->{$action}($tableName, [
                'product_id' => $this->id,
                'configurable_id' => $id
            ])->execute();

            self::getDb()->createCommand()->{$action}($tableName, [
                'product_id' => $id,
                'configurable_id' => $this->id
            ])->execute();


            self::getDb()->createCommand()->delete('{{%shop__product_configurable_attributes}}', ['product_id' => $id])->execute();

            $use_configurations = ($action == 'insert') ? 1 : 0;
            self::getDb()->createCommand()->update(self::tableName(), ['use_configurations' => $use_configurations], ['id' => $id])->execute();

            foreach ($this->getConfigurable_attributes() as $attr_id) {
                self::getDb()->createCommand()->insert('{{%shop__product_configurable_attributes}}', [
                    'product_id' => $id,
                    'attribute_id' => $attr_id
                ])->execute();
            }


        } catch (Exception $exception) {

        }
    }

    public function addConfigure($id)
    {
        $tableName = '{{%shop__product_configurations}}';
        try {
            self::getDb()->createCommand()->insert($tableName, [
                'product_id' => $this->id,
                'configurable_id' => $id
            ])->execute();
            if (true) { //recursive
                self::getDb()->createCommand()->insert($tableName, [
                    'product_id' => $id,
                    'configurable_id' => $this->id
                ])->execute();

                foreach ($this->getConfigurable_attributes() as $attr_id) {
                    self::getDb()->createCommand()->insert('{{%shop__product_configurable_attributes}}', [
                        'product_id' => $id,
                        'attribute_id' => $attr_id
                    ])->execute();
                }


            }
        } catch (Exception $exception) {
            //error duplicate
        }

    }

}
