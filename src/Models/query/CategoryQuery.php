<?php

namespace panix\mod\shop\models\query;

use panix\engine\behaviors\nestedsets\NestedSetsQueryBehavior;
use panix\engine\traits\query\TranslateQueryTrait;
use panix\engine\traits\query\DefaultQueryTrait;
use yii\db\ActiveQuery;
use yii\helpers\Url;

/**
 * Class CategoryQuery
 * @package panix\mod\shop\models\query
 * @use ActiveQuery
 */
class CategoryQuery extends ActiveQuery
{

    use DefaultQueryTrait;

    public function behaviors()
    {
        return [
            [
                'class' => NestedSetsQueryBehavior::class,
            ]
        ];
    }

    public function excludeRoot()
    {
        $this->andWhere(['!=', 'id', 1]);
        return $this;
    }

    /**
     * @param int $root
     * @param null $level
     * @param bool $absoluteUrl
     * @return array
     */
    public function tree($root = 0, $level = null, $absoluteUrl = false)
    {
        $data = array_values($this->prepareData($root, $level, $absoluteUrl));
        return $this->makeData2($data);
    }


    /**
     * @param int $root
     * @param null $level
     * @param bool $absoluteUrl
     * @return array
     */
    public function prepareData($root = 0, $level = null, $absoluteUrl)
    {
        $res = [];
        $totalCount = 0;
        if (is_object($root)) {
            /** @var \panix\mod\shop\models\Category|\panix\engine\behaviors\nestedsets\NestedSetsBehavior $root */
            $res[$root->{$root->idAttribute}]['key'] = $root->{$root->idAttribute};
            $res[$root->{$root->idAttribute}]['title'] = $root->{$root->titleAttribute};
            //$res[$root->{$root->idAttribute}]['count'] = $root->countItems;
            if (method_exists($root, 'getUrl'))
                $res[$root->{$root->idAttribute}]['url'] = Url::to($root->getUrl(), $absoluteUrl);
            if (isset($root->switch))
                $res[$root->{$root->idAttribute}]['switch'] = $root->switch;


            if ($level) {
                $totalCount = 0;
                foreach ($root->children()->all() as $childRoot) {
                    $aux = $this->prepareData($childRoot, $level - 1, $absoluteUrl);

                   // $res[$root->{$root->idAttribute}]['count'] = $childRoot->countItems;

                    if (isset($res[$root->{$root->idAttribute}]['children']) && !empty($aux)) {
                        $res[$root->{$root->idAttribute}]['folder'] = true;
                        $res[$root->{$root->idAttribute}]['children'] += $aux;
                    } elseif (!empty($aux)) {
                        $res[$root->{$root->idAttribute}]['folder'] = true;
                        $res[$root->{$root->idAttribute}]['children'] = $aux;
                    }
                    $totalCount += $res[$root->{$root->idAttribute}]['count'];
                }
                // print_r($res);die;
                // $res[$root->{$root->idAttribute}]['totalCount'] = $totalCount;
                $res[$root->{$root->idAttribute}]['totalCount'] = 0;
            } elseif (is_null($level)) {
                $totalCount = 0;
                foreach ($root->children()->all() as $childRoot) {
                  //  $res[$root->{$root->idAttribute}]['count'] = $childRoot->countItems;
                    $aux = $this->prepareData($childRoot, null,$absoluteUrl);
                    if (isset($res[$root->{$root->idAttribute}]['children']) && !empty($aux)) {
                        $res[$root->{$root->idAttribute}]['folder'] = true;
                        $res[$root->{$root->idAttribute}]['children'] += $aux;

                    } elseif (!empty($aux)) {
                        $res[$root->{$root->idAttribute}]['folder'] = true;
                        $res[$root->{$root->idAttribute}]['children'] = $aux;
                    }
                   // $totalCount += $res[$root->{$root->idAttribute}]['count'];
                }

            }
         //   $res[$root->{$root->idAttribute}]['totalCount'] = $root->countItems + $totalCount;
            $res[$root->{$root->idAttribute}]['totalCount'] = 0;
        } elseif (is_scalar($root)) {

            if ($root == 0) {
                foreach ($this->roots()->all() as $rootItem) {
                    if ($level) {
                        $res += $this->prepareData($rootItem, $level - 1, $absoluteUrl);
                    } elseif (is_null($level)) {
                        $res += $this->prepareData($rootItem, null, $absoluteUrl);
                    }
                }
            } else {
                $modelClass = $this->owner->modelClass;
                $model = new $modelClass;
                $root = $modelClass::find()
                    ->andWhere([$model->idAttribute => $root])
                    ->one();

                //New by panix
                /** @var \panix\engine\behaviors\nestedsets\NestedSetsBehavior $root */
                foreach ($root->children()->all() as $rootItem) {
                    if ($level) {
                        $res += $this->prepareData($rootItem, $level - 1, $absoluteUrl);
                    } elseif (is_null($level)) {
                        $res += $this->prepareData($rootItem, null, $absoluteUrl);
                    }
                }
                unset($model);
            }
        }
        return $res;
    }

    public function makeData2(&$data)
    {
        $tree = [];
        foreach ($data as $key => &$item) {
            if (isset($item['children'])) {
                $item['children'] = array_values($item['children']);
                $tree[$key] = $this->makeData2($item['children']);
            }
            $tree[$key] = $item;
        }
        return $tree;
    }
}
