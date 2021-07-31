<?php

namespace Shopium\Models;


use panix\mod\shop\components\ExternalFinder;
use panix\mod\sitemap\behaviors\SitemapBehavior;
use Yii;
use yii\helpers\ArrayHelper;
use panix\engine\behaviors\nestedsets\NestedSetsBehavior;
use panix\mod\shop\models\translate\CategoryTranslate;
use panix\mod\shop\models\query\CategoryQuery;
use panix\engine\CMS;
use panix\engine\db\ActiveRecord;
use panix\engine\behaviors\UploadFileBehavior;

/**
 * Class Category
 * @package panix\mod\shop\models
 *
 * @property integer $id
 * @property integer $tree
 * @property integer $lft
 * @property integer $rgt
 * @property integer $depth
 * @property string $slug
 * @property string $image
 * @property string $name
 * @property string $description
 * @property string $full_path
 * @property integer $switch
 * @property integer $created_at
 * @property integer $updated_at
 * @property integer $countItems Relation of getCountItems()
 * @property string getMetaDescription()
 * @property string getMetaTitle()
 */
class Category extends ActiveRecord
{

    const MODULE_ID = 'shop';
    const route = '/admin/shop/category';
    const route_update = 'index';

    public $parent_id;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%shop__category}}';
    }

    public static function find()
    {
        return new CategoryQuery(get_called_class());
    }

    /**
     * @return array
     */
    public function getUrl()
    {
        return ['/shop/catalog/view', 'slug' => $this->full_path];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['image'], 'file', 'skipOnEmpty' => true, 'extensions' => ['png', 'jpg', 'jpeg']],
            ['slug', '\panix\mod\shop\components\CategoryUrlValidator', 'attributeCompare' => 'name'],
            ['slug', 'fullPathValidator'],
            ['slug', 'match',
                'pattern' => '/^([a-z0-9-])+$/i',
                'message' => Yii::t('app/default', 'PATTERN_URL')
            ],
            [['name', 'slug'], 'trim'],
            [['name', 'slug'], 'required'],
            ['use_seo_parents', 'boolean'],
            [['description', 'image'], 'default', 'value' => null],
            [['name', 'meta_title', 'h1'], 'string', 'max' => 255],
            [['meta_description'], 'string'],
            ['description', 'safe']
        ];
    }


    public function fullPathValidator($attribute)
    {
        if ($this->parent_id) {
            $count = Category::find()->where(['full_path' => $this->parent_id->full_path . '/' . $this->{$attribute}])->count();
            if ($count) {
                $this->addError($attribute, 'Такой URL уже есть!');
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        /* if (Yii::$app->getModule('seo'))
             $a['seo'] = [
                 'class' => '\panix\mod\seo\components\SeoBehavior',
                 'url' => $this->getUrl()
             ];*/

        $a['uploadFile'] = [
            'class' => UploadFileBehavior::class,
            'files' => [
                'image' => '@uploads/categories',
            ],
            //'options' => [
            //    'watermark' => false
            // ]
        ];
        if (Yii::$app->getModule('sitemap')) {
            $a['sitemap'] = [
                'class' => SitemapBehavior::class,
                'groupName' => 'Каталог',
                //'batchSize' => 100,
                'scope' => function ($model) {
                    /** @var \yii\db\ActiveQuery $model */
                    // $model->select(['full_path', 'updated_at']);
                    $model->andWhere(['switch' => 1])->andWhere(['!=', 'id', 1]);
                },
                'dataClosure' => function ($model) {
                    /** @var self $model */
                    return [
                        'loc' => $model->getUrl(),
                        'lastmod' => $model->updated_at,
                        'name' => $model->name,
                        'changefreq' => SitemapBehavior::CHANGEFREQ_DAILY,
                        'priority' => 0.8
                    ];
                }
            ];
        }
        $a['tree'] = [
            'class' => NestedSetsBehavior::class,
            'hasManyRoots' => false,
        ];
        $a['translate'] = [
            'class' => '\panix\mod\shop\components\TranslateBehavior',
            'translationAttributes' => ['name', 'description', 'meta_title', 'meta_description', 'h1']
        ];
        return ArrayHelper::merge($a, parent::behaviors());
    }

    /**
     * Relation ProductCategoryRef
     * @return int
     */
    public function getCountItems()
    {
        return (int)$this->hasMany(ProductCategoryRef::class, ['category' => 'id'])->count();
    }

    public static function flatTree()
    {
        $result = [];
        $categories = Category::find()->orderBy(['lft' => SORT_ASC])->excludeRoot()->all();
      //  array_shift($categories);

        foreach ($categories as $c) {
            /**
             * @var self $c
             */
            if ($c->depth > 2) {
                $result[$c->id] = str_repeat(html_entity_decode('&mdash;'), $c->depth - 2) . ' ' . $c->name;
            } else {
                $result[$c->id] = ' ' . $c->name;
            }
        }

        return $result;
    }


    public function test($item)
    {
        $childCounter = 0;
        $categories = [];
        $children = $item->children()->all();
        if ($children) {
            foreach ($children as $child) {
                /** @var static|\panix\engine\behaviors\nestedsets\NestedSetsBehavior $child * */
                $categories[] = [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'url' => $child->getUrl(),
                    'productsCount' => $child->countItems,
                    //'child' => $this->test($child)
                ];
                $categories[]['child'][] = $this->test($child);
                $childCounter += $child->countItems;
            }
            CMS::dump($categories);
            die;
        }

        return [
            'children' => $categories,
            'counter' => $childCounter
        ];
    }


    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        $this->rebuildFullPath();
        return parent::beforeSave($insert);
    }

    /**
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {


        $childrens = $this->descendants()->all();
        if ($childrens) {
            foreach ($childrens as $children) {
                $children->full_path = $this->slug . '/' . $children->full_path;
                $children->saveNode(false);
            }
        }
        Yii::$app->cache->delete('CategoryUrlRule');


        if (Yii::$app->hasModule('csv')) {
            $external = new ExternalFinder('{{%csv}}');

            if (!$external->getObject(ExternalFinder::OBJECT_MAIN_CATEGORY, $this->path_hash))
                $external->createExternalId(ExternalFinder::OBJECT_MAIN_CATEGORY, $this->id, $this->path_hash);

            if (!$external->getObject(ExternalFinder::OBJECT_CATEGORY, $this->path_hash))
                $external->createExternalId(ExternalFinder::OBJECT_CATEGORY, $this->id, $this->path_hash);
        }

        return parent::afterSave($insert, $changedAttributes);
    }

    public function rebuildFullPath()
    {
        // Create category full path.
        $ancestors = $this->ancestors()
            //->orderBy('depth')
            ->all();
        if ($ancestors) {
            // Remove root category from path
            unset($ancestors[0]);

            $parts = [];
            $partsName = [];
            foreach ($ancestors as $ancestor) {
                $parts[] = $ancestor->slug;
                $partsName[] = $ancestor->name_ru;
            }

            $parts[] = $this->slug;
            $partsName[] = $this->name_ru;
            $this->full_path = implode('/', array_filter($parts));
            $this->path_hash = md5(mb_strtolower(implode('/', array_filter($partsName))));
        }

        return $this;
    }

    /**
     * @param array $params
     * @return mixed
     */
    public function h1($params=array())
    {
        if (!empty($this->h1)) {
            $value = $this->h1;
        } else {
            $value = $this->name;
        }

        return $this->replaceMeta($value, $params);
    }
    /**
     * @param array $params
     * @return mixed
     */
    public function title($params=array())
    {
        if (!empty($this->meta_title)) {
            $value = $this->meta_title;
        } else {
            $value = $this->name;
        }

        return $this->replaceMeta($value, $params);
    }

    /**
     * @param array $params
     * @return mixed
     */
    public function description($params=array())
    {
        if (!empty($this->meta_description)) {
            $value = $this->meta_description;
        } else {
            $value = $this->name;
        }

        return $this->replaceMeta($value, $params);
    }

    public function replaceMeta($text, $params)
    {

        //$replace = [
       //     "{name}" => $name,
        //    "{currency.symbol}" => Yii::$app->currency->active['symbol'],
        //];
        return CMS::textReplace($text, $params);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        if (Yii::$app->hasModule('csv')) {
            $external = new ExternalFinder('{{%csv}}');
            $external->deleteObject(ExternalFinder::OBJECT_MAIN_CATEGORY, $this->id);
            $external->deleteObject(ExternalFinder::OBJECT_CATEGORY, $this->id);
        }
        parent::afterDelete();
    }

}
