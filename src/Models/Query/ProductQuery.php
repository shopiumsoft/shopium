<?php

namespace Shopium\Models\Query;

use Yii;
use yii\db\ActiveQuery;
use panix\engine\traits\query\DefaultQueryTrait;
use panix\engine\traits\query\TranslateQueryTrait;
use panix\mod\shop\models\traits\EavQueryTrait;
use panix\mod\shop\models\Category;
use panix\mod\shop\models\Product;
use panix\mod\shop\models\ProductCategoryRef;

class ProductQuery extends ActiveQuery
{

    use DefaultQueryTrait, EavQueryTrait, FilterQueryTrait;

    /**
     * Product by category
     *
     * @return $this
     */
    public function category()
    {
        $this->joinWith(['category']);
        return $this;
    }

    public function getSales(){
        $this->published();
        $this->andWhere(['IS NOT', Product::tableName() . '.discount', null])
            ->andWhere(['!=', Product::tableName() . '.discount', '']);
        return $this;
    }


    /**
     * @param $manufacturers array|int
     * @param $whereType string
     * @return $this
     */
    public function applyManufacturers($manufacturers,$whereType = 'andWhere')
    {
        if (!is_array($manufacturers))
            $manufacturers = [$manufacturers];

        if (empty($manufacturers))
            return $this;

        sort($manufacturers);

        $this->$whereType(['manufacturer_id' => $manufacturers]);
        return $this;
    }

    /**
     * @param $suppliers array|int
     * @return $this
     */
    public function applySuppliers($suppliers)
    {
        if (!is_array($suppliers))
            $suppliers = [$suppliers];

        if (empty($suppliers))
            return $this;

        sort($suppliers);

        $this->andWhere(['supplier_id' => $suppliers]);
        return $this;
    }

    /**
     * @param $categories array|int|object
     * @param $whereType string
     * @return $this
     */
    public function applyCategories($categories, $whereType = 'andWhere')
    {
        if ($categories instanceof Category)
            $categories = [$categories->id];
        else {
            if (!is_array($categories))
                $categories = [$categories];
        }
        //  $tableName = ($this->modelClass)->tableName();
        $this->leftJoin(ProductCategoryRef::tableName(), ProductCategoryRef::tableName() . '.`product`=' . $this->modelClass::tableName() . '.`id`');
        $this->$whereType([ProductCategoryRef::tableName() . '.`category`' => $categories]);

        return $this;
    }


    /**
     * Product by manufacturer
     *
     * @return $this
     */
    public function manufacturer()
    {
        $this->joinWith(['manufacturer']);
        return $this;
    }


    /**
     * @param null $q
     * @return $this
     */
    public function applySearch($q = null)
    {
        $language = Yii::$app->language;
        if ($q) {
            $modelClass = $this->modelClass;
            $tableName = $modelClass::tableName();
            $this->andWhere(['LIKE', $tableName . '.'.Yii::$app->getModule('shop')->searchAttribute, $q]);
            $this->orWhere(['LIKE', $tableName . '.name_' . $language, $q]);

        }
        return $this;
    }

    public function new($start, $end)
    {
        $modelClass = $this->modelClass;
        $tableName = $modelClass::tableName();
        $this->between($start, $end, 'created_at');
        return $this;
    }


    /**
     * @param integer $current_id
     * @param array $wheres
     * @return $this
     */
    public function next($current_id, $wheres = [])
    {
        $modelClass = $this->modelClass;
        $tableName = $modelClass::tableName();

        $subQuery = (new \yii\db\Query())->select('MIN(`id`)')
            ->from($tableName . ' next')
            ->where(['>', 'next.id', $current_id]);

        if ($wheres) {
            $subQuery->andWhere($wheres);
        }

        $this->where(['=', 'id', $subQuery]);

        return $this;
    }

    /**
     * @param integer $current_id
     * @param array $wheres
     * @return $this
     */
    public function prev($current_id, $wheres = [])
    {
        $modelClass = $this->modelClass;
        $tableName = $modelClass::tableName();

        $subQuery = (new \yii\db\Query())->select('MAX(`id`)')
            ->from($tableName . ' prev')
            ->where(['<', 'prev.id', $current_id]);

        if ($wheres) {
            $subQuery->andWhere($wheres);
        }

        $this->where(['=', 'id', $subQuery]);

        return $this;
    }


}
