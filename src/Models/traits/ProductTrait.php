<?php

namespace panix\mod\shop\models\traits;

use Yii;
use yii\helpers\ArrayHelper;
use yii\caching\DbDependency;
use panix\mod\shop\models\Category;
use panix\mod\shop\models\Manufacturer;
use panix\mod\shop\models\ProductType;
use panix\mod\shop\models\search\ProductSearch;
use panix\mod\shop\models\Supplier;
use panix\engine\Html;
use panix\engine\CMS;
use panix\mod\shop\models\Attribute;
use panix\mod\shop\models\Product;

/**
 * Trait ProductTrait
 * @package panix\mod\shop\models\traits
 */
trait ProductTrait
{
    public static function getAvailabilityItems()
    {
        return [
            1 => self::t('AVAILABILITY_1'),
            2 => self::t('AVAILABILITY_2'),
            3 => self::t('AVAILABILITY_3'),
            4 => self::t('AVAILABILITY_4'),
        ];
    }

    public function getGridColumns()
    {

        $price_max = Product::find()->aggregatePrice('MAX')->asArray()->one();
        $price_min = Product::find()->aggregatePrice('MIN')->asArray()->one();

        $columns = [];
        $columns['image'] = [
            'class' => 'panix\engine\grid\columns\ImageColumn',
            'attribute' => 'image',
            // 'filter'=>true,
            'value' => function ($model) {
                /** @var $model Product */


                $reviewsQuery = $model->getReviews()->status(1);
                $aggregate = $reviewsQuery->aggregateRate();

                $reviewsCount = $aggregate->count();
                $aggregate = $aggregate->one();
                Yii::$app->view->registerCss("
                    .cancel-on-png, .cancel-off-png, .star-on-png, .star-off-png, .star-half-png{font-size:13px;}
                    .star-off-png{color:#dee2e6}
                    .star-on-png,.star-half-png{color:orange}
                    .raty img{width:13px;height:13px;}
                    ", [], 'rating');
                $rating = \panix\ext\rating\RatingInput::widget([
                    'name' => 'product-rating',
                    'value' => ($reviewsCount > 0) ? round($aggregate->rate / $reviewsCount, 1) : 0,
                    'options' => [
                        'starType' => 'img',
                        'readOnly' => true,
                    ],
                ]);


                return $model->renderGridImage() . $rating;
            },
        ];
        $columns['id'] = [
            'attribute' => 'id',
            'contentOptions' => ['class' => 'text-center'],
        ];
        $columns['name'] = [
            'attribute' => 'name',
            'format' => 'raw',
            'contentOptions' => ['class' => 'text-left'],
            'value' => function ($model) {
                /** @var $model Product */
                if ($model->name) {
                    $html = Html::a($model->name, $model->getUrl(), ['data-pjax' => 0, 'target' => '_blank']);
                    if ($model->views > 0) {
                        $html .= " <small>(" . Yii::t('app/default', 'VIEWS', ['n' => $model->views]) . ")</small>";
                    }
                    if (true) {
                        $labels = [];
                        foreach ($model->labels() as $key => $label) {
                            $class = 'secondary';
                            if ($key == 'new') {
                                $class = 'success';
                            } elseif ($key == 'top_sale') {
                                $class = 'primary';
                            } elseif ($key == 'hit_sale') {
                                $class = 'info';
                            } elseif ($key == 'sale') {
                                $class = 'danger';
                            } elseif ($key == 'discount') {
                                $class = 'danger';
                            }
                            $labelOptions = [];
                            $labelOptions['class'] = 'badge badge-' . $class;
                            if (isset($label['tooltip']))
                                $labelOptions['title'] = $label['tooltip'];
                            $labelOptions['data-toggle'] = 'tooltip';
                            $labels[] = Html::tag('span', $label['value'], $labelOptions);
                        }
                        $html .= '<br/>' . implode('', $labels);
                    }
                    return $html;
                }
                return null;

            },
        ];
        $columns['sku'] = [
            'attribute' => 'sku',
            'contentOptions' => ['class' => 'text-center'],
        ];
       /* $columns['type_id'] = [
            'attribute' => 'type_id',
            'format' => 'raw',
            'contentOptions' => ['class' => 'text-center'],
            'filter' => ArrayHelper::map(ProductType::find()
                ->addOrderBy(['name' => SORT_ASC])
                // ->cache(3200, new DbDependency(['sql' => 'SELECT MAX(`updated_at`) FROM ' . Manufacturer::tableName()]))
                ->all(), 'id', 'name'),
            'filterInputOptions' => ['class' => 'form-control', 'prompt' => html_entity_decode('&mdash; выберите тип &mdash;')],
            'value' => 'type.name'
        ];*/
        /*$columns['rating'] = [
            'header' => 'Rating',
            'format' => 'raw',
            'contentOptions' => ['style' => 'min-width:140px'],
            'value' => function ($model) {

                $reviewsQuery = $model->getReviews()->status(1);
                $aggregate = $reviewsQuery->aggregateRate();

                $reviewsCount = $aggregate->count();
                $aggregate = $aggregate->one();
//Yii::$app->view->registerCss(".cancel-on-png, .cancel-off-png, .star-on-png, .star-off-png, .star-half-png{font-size:1rem}",[],'rating');
                return \panix\ext\rating\RatingInput::widget([
                    'name' => 'product-rating',
                    'value' => ($reviewsCount > 0) ? round($aggregate->rate / $reviewsCount, 1) : 0,
                    'options' => [
                        'starType'=>'i',
                        'readOnly' => true,
                    ],
                ]);
            }
        ];*/
        /*$columns['price'] = [
            'attribute' => 'price',
            'format' => 'raw',
            'class' => 'panix\engine\grid\columns\jui\SliderColumn',
            'max' => (int)Product::find()->aggregatePrice('MAX'),
            'min' => (int)Product::find()->aggregatePrice('MIN'),
            'prefix' => '<sup>' . Yii::$app->currency->main['symbol'] . '</sup>',
            'contentOptions' => ['class' => 'text-center', 'style' => 'position:relative'],
            'value' => function ($model) {
                $ss = '';
                if ($model->hasDiscount) {
                    $price = $model->discountPrice;
                    $ss = '<del class="text-secondary">' . Yii::$app->currency->number_format($model->originalPrice) . '</del> / ';
                } else {
                    $price = $model->price;
                }
                if ($model->currency_id) {
                    $priceHtml = $price;
                    $symbol = Html::tag('sup', Yii::$app->currency->currencies[$model->currency_id]['symbol']);
                } else {
                    $priceHtml = Yii::$app->currency->convert($price, $model->currency_id);
                    $symbol = Html::tag('sup', Yii::$app->currency->main['symbol']);
                }
                //$ss .= '<span class="badge badge-danger position-absolute" style="top:0;right:0;">123</span>';
                return $ss . Html::tag('span', Yii::$app->currency->number_format($priceHtml), ['class' => 'text-success font-weight-bold']) . ' ' . $symbol;
            }
        ];*/
        $columns['price'] = [
            'attribute' => 'price',
            'format' => 'raw',
            'class' => 'panix\engine\grid\columns\jui\SliderColumn',
            'max' => (int)$price_max['aggregation_price'],
            'min' => (int)$price_min['aggregation_price'],
            'prefix' => '<sup>' . Yii::$app->currency->main['symbol'] . '</sup>',
            'contentOptions' => ['class' => 'text-center', 'style' => 'position:relative'],
            'value' => function ($model) {
                /** @var static $model */
                return $model->getGridPrice();
            }
        ];
        $columns['supplier_id'] = [
            'attribute' => 'supplier_id',
            'filter' => ArrayHelper::map(Supplier::find()
                ->cache(3200, new DbDependency(['sql' => 'SELECT MAX(`updated_at`) FROM ' . Supplier::tableName()]))
                ->addOrderBy(['name' => SORT_ASC])
                ->all(), 'id', 'name'),
            'filterInputOptions' => ['class' => 'form-control', 'prompt' => html_entity_decode('&mdash; выберите поставщика &mdash;')],
            'value' => function ($model) {
                return ($model->supplier) ? $model->supplier->name : NULL;
            }
        ];
        $columns['manufacturer_id'] = [
            'attribute' => 'manufacturer_id',
            'filter' => ArrayHelper::map(Manufacturer::find()
                ->addOrderBy(['name_' . Yii::$app->language => SORT_ASC])
                // ->cache(3200, new DbDependency(['sql' => 'SELECT MAX(`updated_at`) FROM ' . Manufacturer::tableName()]))
                ->all(), 'id', 'name_' . Yii::$app->language),
            'filterInputOptions' => ['class' => 'form-control', 'prompt' => html_entity_decode('&mdash; выберите производителя &mdash;')],
            'value' => function ($model) {
                return ($model->manufacturer) ? $model->manufacturer->name : NULL;
            }
        ];
        $columns['categories'] = [
            'header' => static::t('Категории'),
            'attribute' => 'main_category_id',
            'format' => 'html',
            'contentOptions' => ['style' => 'max-width:180px'],
            'filter' => Html::dropDownList(Html::getInputName(new ProductSearch, 'main_category_id'), (isset(Yii::$app->request->get('ProductSearch')['main_category_id'])) ? Yii::$app->request->get('ProductSearch')['main_category_id'] : null, Category::flatTree(),
                [
                    'class' => 'form-control',
                    'prompt' => html_entity_decode('&mdash; выберите категорию &mdash;')
                ]
            ),
            'value' => function ($model) {
                /** @var $model Product */
                $result = '';

                foreach ($model->categories as $category) {
                    $options = [];
                    $options['data-pjax'] = '0';
                    $options['title'] = $category->name;
                    if ($category->id == $model->main_category_id) {
                        $options['class'] = 'badge badge-secondary';
                    } else {
                        $options['class'] = 'badge badge-light';
                    }
                    $result .= Html::a($category->name, $category->getUrl(), $options);
                }
                return $result;
            }
        ];
        $columns['commentsCount'] = [
            'header' => static::t('COMMENTS_COUNT'),
            'attribute' => 'commentsCount',
            'format' => 'html',
            'filter' => true,
            'contentOptions' => ['class' => 'text-center'],
            'value' => function ($model) {
                /** @var $model Product */
                $options['data-pjax'] = 0;
                return Html::a($model->getReviews()->count(), ['/admin/comments/default/index', 'CommentsSearch[object_id]' => $model->primaryKey], $options);
            }
        ];
        $columns['created_at'] = [
            'attribute' => 'created_at',
            'class' => 'panix\engine\grid\columns\jui\DatepickerColumn',
        ];
        $columns['updated_at'] = [
            'attribute' => 'updated_at',
            'class' => 'panix\engine\grid\columns\jui\DatepickerColumn',
        ];


        /*$query2 = Attribute::find()
            ->cache(3600)
            ->displayOnFront()
            ->sort()
            //->where(['IN', 'name', array_keys($this->_attributes)])
            ->all();*/


        /*$db = Attribute::getDb();
        $query = $db->cache(function () {
            return Attribute::find()
                ->displayOnFront()
                ->sort()
                ->all();
        }, 3600);


        $get = Yii::$app->request->get('ProductSearch');
        foreach ($query as $m) {

            $columns['' . $m->name] = [
                //'class' => 'panix\mod\shop\components\EavColumn',
                'attribute' => 'eav_' . $m->name,
                'header' => $m->title,
                'filter' => Html::dropDownList(
                    'ProductSearch[eav][' . $m->name . ']',
                    (isset($get['eav'][$m->name])) ? $get['eav'][$m->name] : null,
                    ArrayHelper::map($m->options, 'id', 'value'),
                    ['class' => 'custom-select w-auto', 'prompt' => html_entity_decode('&mdash; ' . $m->title . ' &mdash;')]
                ),
                //'filter'=>true,
                'contentOptions' => ['class' => 'text-center'],
                'filterOptions' => ['class' => 'text-center'],

            ];
        }*/


        $columns['DEFAULT_CONTROL'] = [
            'class' => 'panix\engine\grid\columns\ActionColumn',
        ];
        $columns['DEFAULT_COLUMNS'] = [
            [
                'class' => \panix\engine\grid\sortable\Column::class,
            ],
            [
                'class' => 'panix\engine\grid\columns\CheckboxColumn',
                'customActions' => [
                    [
                        'label' => self::t('GRID_OPTION_ACTIVE'),
                        'url' => '#',
                        'icon' => 'eye',
                        'linkOptions' => [
                            'onClick' => 'return setProductsStatus(1, this);',
                            'data-confirm-info' => self::t('CONFIRM_SHOW'),
                            'data-pjax' => 0
                        ],
                    ],
                    [
                        'label' => self::t('GRID_OPTION_DEACTIVE'),
                        'url' => '#',
                        'icon' => 'eye-close',
                        'linkOptions' => [
                            'onClick' => 'return setProductsStatus(0, this);',
                            'data-confirm-info' => self::t('CONFIRM_HIDE'),
                            'data-pjax' => 0
                        ],
                    ],
                    [
                        'label' => self::t('GRID_OPTION_SETCATEGORY'),
                        'url' => '#',
                        'icon' => 'folder-open',
                        'linkOptions' => [
                            'onClick' => 'return showCategoryAssignWindow(this);',
                           // 'data-confirm' => self::t('CONFIRM_CATEGORY'),
                            'data-pjax' => 0
                        ],
                    ],
                    [
                        'label' => self::t('GRID_OPTION_COPY'),
                        'url' => '#',
                        'icon' => 'copy',
                        'linkOptions' => [
                            'onClick' => 'return showDuplicateProductsWindow(this);',
                           // 'data-confirm' => self::t('CONFIRM_COPY'),
                            'data-pjax' => 0
                        ],
                    ],
                    [
                        'label' => self::t('GRID_OPTION_SETPRICE'),
                        'url' => '#',
                        'icon' => 'currencies',
                        'linkOptions' => [
                            'onClick' => 'return setProductsPrice(this);',
                          //  'data-confirm' => self::t('CONFIRM_PRICE'),
                            'data-pjax' => 0
                        ],
                    ],
                    [
                        'label' => self::t('GRID_OPTION_UPDATE_VIEWS'),
                        'url' => '#',
                        'icon' => 'refresh',
                        'linkOptions' => [
                            'onClick' => 'return updateProductsViews(this);',
                           // 'data-confirm' => self::t('CONFIRM_UPDATE_VIEWS'),
                            'data-pjax' => 0
                        ],
                    ]
                ]
            ]
        ];

        return $columns;
    }

    public function getGridPrice()
    {


        $prices = [];
        $prices[Yii::$app->currency->main['id']]['symbol'] = Yii::$app->currency->main['symbol'];
        if ($this->hasDiscount) {
            $prices[Yii::$app->currency->main['id']]['value'] = $this->discountPrice;
            $prices[Yii::$app->currency->main['id']]['original_value'] = $this->price;
        } else {
            $prices[Yii::$app->currency->main['id']]['value'] = $this->price;
        }
        if ($this->currency_id && Yii::$app->currency->main['id'] != $this->currency_id) {
            if ($this->hasDiscount) {

                $prices[Yii::$app->currency->main['id']]['value'] = $this->discountPrice * Yii::$app->currency->currencies[$this->currency_id]['rate'];
                $prices[Yii::$app->currency->main['id']]['original_value'] = $this->price * Yii::$app->currency->currencies[$this->currency_id]['rate'];
            } else {
                $prices[Yii::$app->currency->main['id']]['value'] = $this->price * Yii::$app->currency->currencies[$this->currency_id]['rate'];
            }

        }


        $data = [];
        foreach ($prices as $currency => $price_data) {
            $price = '';
            if (isset($price_data['original_value'])) {
                $price .= '<del class="text-secondary">' . Yii::$app->currency->number_format($price_data['original_value']) . '</del> / ';
            }
            $price .= Html::tag('span', Yii::$app->currency->number_format($price_data['value']), ['class' => 'text-success font-weight-bold']) . ' ' . $price_data['symbol'];
            //$price .= Html::tag('span', $price_data['value'], ['class' => 'text-success font-weight-bold']) . ' ' . $price_data['symbol'];
            $data[] = $price;
        }
        return implode('<br>', $data);

    }

    public function getDataAttributes()
    {


        /** @var \panix\mod\shop\components\EavBehavior $attributes */
        $attributes = $this->getEavAttributes();
        $data = [];
        $groups = [];
        $models = [];

        // $query = Attribute::getDb()->cache(function () {
        $query = Attribute::find()
            ->where(['name' => array_keys($attributes)])
            ->displayOnFront()
            ->sort()
            ->all();
        // }, 3600);


        foreach ($query as $m)
            $models[$m->name] = $m;


        foreach ($models as $model) {
            /** @var Attribute $model */
            $abbr = ($model->abbreviation) ? ' ' . $model->abbreviation : '';

            $value = $model->renderValue($attributes[$model->name]) . $abbr;

            if ($model->group_id && Yii::$app->settings->get('shop', 'group_attribute')) {
                $groups[$model->group->name][] = [
                    'id' => $model->id,
                    'name' => $model->title,
                    'hint' => $model->hint,
                    'value' => $value
                ];

            }
            // $data[$model->title] = $value;
            $data[$model->name]['name'] = $model->title;
            $data[$model->name]['value'] = $value;

        }

        return [
            'data' => $data,
            'groups' => $groups,
        ];
    }

    /**
     * Convert price to current currency
     *
     * @param string $attr
     * @return mixed
     */
    public function toCurrentCurrency($attr = 'price')
    {
        return Yii::$app->currency->convert($this->$attr);
    }

    public function getProductAttributes()
    {
        /** @var $this Product */
        //Yii::import('mod.shop.components.AttributesRender');
        $attributes = new \panix\mod\shop\components\AttributesRender;
        return $attributes->getData($this);
    }


    public function description($codes = [])
    {
        $description = $this->name;
        /** @var $this Product */
        /*if ($this->mainCategory) {
            if (!empty($this->mainCategory->seo_product_description)) {
                return $this->replaceMeta($this->mainCategory->seo_product_description);
            } else {

                $parent = $this->mainCategory->parent()->one();
                if ($parent) {
                    return $this->replaceMeta($parent->seo_product_description);
                }
            }
        }*/
        if ($this->type_id) {
            if (!empty($this->type->product_description)) {
                $description = $this->replaceMeta($this->type->product_description, $codes);
            }

        }
        return $description;
    }

    public function title($codes = [])
    {
        $title = $this->name;
        /** @var $this Product */
        /*if ($this->mainCategory) {
            if (!empty($this->mainCategory->seo_product_title)) {
                $title = $this->replaceMeta($this->mainCategory->seo_product_title);
            } else {
                $parent = $this->mainCategory->parent()->one();
                if ($parent) {
                    if ($parent->seo_product_title)
                        $title = $this->replaceMeta($parent->seo_product_title);
                }

            }
        }*/


        if ($this->type_id) {
            if (!empty($this->type->product_title)) {
                $title = $this->replaceMeta($this->type->product_title, $codes);
            }

        }

        return $title;
    }


    public function replaceMeta($text, $codesList)
    {
        /** @var $this Product */
        $codes = [];
        $codes["{product_id}"] = $this->id;
        $codes["{product_name}"] = $this->name;
        $codes["{product_price}"] = $this->getFrontPrice();
        $codes["{product_sku}"] = $this->sku;
        $codes["{product_type}"] = ($this->type) ? $this->type->name : null;
        $codes["{product_manufacturer}"] = (isset($this->manufacturer)) ? $this->manufacturer->name : null;
        $codes["{product_category}"] = (isset($this->mainCategory)) ? $this->mainCategory->name : null;
        $codes["{currency.symbol}"] = Yii::$app->currency->active['symbol'];
        $codes["{currency.iso}"] = Yii::$app->currency->active['iso'];
        $codes = ArrayHelper::merge($codes, $codesList);
        return CMS::textReplace($text, $codes);
    }

    public function replaceName()
    {
        /** @var $this Product */
        $codes = [];

        $attributes = Yii::$app->request->post('Attribute', []);
        if (empty($attributes))
            return false;


        $reAttributes = [];
        foreach ($attributes as $key => $val) {

            if (in_array($key, [Attribute::TYPE_TEXT, Attribute::TYPE_TEXTAREA, Attribute::TYPE_YESNO])) {
                foreach ($val as $k => $v) {
                    $reAttributes[$k] = '"' . $v . '"';
                    if (is_string($v) && $v === '') {
                        unset($reAttributes[$k]);
                    }
                }
            } else {
                foreach ($val as $k => $v) {
                    $reAttributes[$k] = $v;
                    if (is_string($v) && $v === '') {
                        unset($reAttributes[$k]);
                    }
                }
            }
        }

        foreach ($this->getEavAttributesValue($reAttributes) as $k => $attr) {
            $codes['{eav_' . $k . '.value}'] = $attr['value'];
            $codes['{eav_' . $k . '.name}'] = $attr['name'];

        }


        $codes["{product_id}"] = $this->id;
        $codes["{product_name}"] = $this->name;
        $codes["{product_price}"] = $this->getFrontPrice();
        $codes["{product_sku}"] = $this->sku;
        if ($this->type) {
            $type = $this->type;
        } else {
            $type = ProductType::findOne(Yii::$app->request->get('Product')['type_id']);
        }
        $codes["{product_type}"] = $type->name;
        $codes['{product_manufacturer}'] = null;
        if (isset($this->manufacturer)) {
            $codes['{product_manufacturer}'] = $this->manufacturer->name;
        } else {
            if (isset(Yii::$app->request->post('Product')['manufacturer_id']) && Yii::$app->request->post('Product')['manufacturer_id']) {
                $manufacturer = Manufacturer::findOne(Yii::$app->request->post('Product')['manufacturer_id']);
                if ($manufacturer) {
                    $codes['{product_manufacturer}'] = $manufacturer->name;
                }
            }
        }

        $codes["{product_category}"] = (isset($this->mainCategory)) ? $this->mainCategory->name : (Category::findOne((int)Yii::$app->request->post('Product')['main_category_id']))->name;
        $codes["{currency.symbol}"] = Yii::$app->currency->active['symbol'];
        $codes["{currency.iso}"] = Yii::$app->currency->active['iso'];

        return CMS::textReplace($type->product_name, $codes);
    }
}
