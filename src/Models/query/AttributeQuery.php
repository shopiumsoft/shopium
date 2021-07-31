<?php

namespace panix\mod\shop\models\query;


use yii\db\ActiveQuery;
use panix\engine\traits\query\DefaultQueryTrait;
use panix\engine\traits\query\TranslateQueryTrait;

/**
 * Class AttributeQuery
 * @package panix\mod\shop\models\query
 * @use ActiveQuery
 */
class AttributeQuery extends ActiveQuery
{

    use DefaultQueryTrait;

    public function useInFilter()
    {
        return $this->andWhere([$this->modelClass::tableName().'.use_in_filter' => 1]);
    }

    /**
     *
     * @return $this
     */
    public function useInVariants()
    {
        $this->andWhere([$this->modelClass::tableName().'.use_in_variants' => 1]);
        return $this;
    }

    /**
     *
     * @return $this
     */
    public function useInCompare()
    {
        return $this->andWhere([$this->modelClass::tableName().'.use_in_compare' => 1]);
    }

    /**
     * Отобрадение атрибутов в товаре
     * @return $this
     */
    public function displayOnFront()
    {
        return $this->andWhere([$this->modelClass::tableName().'.display_on_front' => 1]);
    }

    /**
     * Отобрадение атрибутов в списке
     * @return $this
     */
    public function displayOnList()
    {
        return $this->andWhere([$this->modelClass::tableName().'.display_on_list' => 1]);
    }

    /**
     * Отобрадение атрибутов в сетке
     * @return $this
     */
    public function displayOnGrid()
    {
        return $this->andWhere([$this->modelClass::tableName().'.display_on_grid' => 1]);
    }

    /**
     * Отобрадение атрибутов в корзине
     * @return $this
     */
    public function displayOnCart()
    {
        return $this->andWhere([$this->modelClass::tableName().'.display_on_cart' => 1]);
    }

    /**
     * Отобрадение атрибутов в pdf печатей
     * @return $this
     */
    public function displayOnPdf()
    {
        return $this->andWhere([$this->modelClass::tableName().'.display_on_pdf' => 1]);
    }

}
