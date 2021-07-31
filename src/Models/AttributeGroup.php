<?php

namespace panix\mod\shop\models;

use Yii;
use panix\mod\shop\models\translate\AttributeGroupTranslate;
use panix\mod\shop\models\query\AttributeGroupQuery;
use panix\engine\db\ActiveRecord;


class AttributeGroup extends ActiveRecord
{

    const MODULE_ID = 'shop';

    public static function find2()
    {
        return new AttributeGroupQuery(get_called_class());
    }

    public function getGridColumns()
    {
        return [
            [
                'attribute' => 'name',
                'contentOptions' => ['class' => 'text-left'],
            ],

            'DEFAULT_CONTROL' => [
                'class' => 'panix\engine\grid\columns\ActionColumn',
            ],
            'DEFAULT_COLUMNS' => [
                [
                    'class' => \panix\engine\grid\sortable\Column::class,
                    'url' => ['/admin/shop/attribute/sortable']
                ],
                ['class' => 'panix\engine\grid\columns\CheckboxColumn'],
            ],
        ];
    }

    public function transactions()
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_INSERT | self::OP_UPDATE,
        ];
    }

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%shop__attribute_group}}';
    }


    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['name'], 'trim'],
            [['switch'], 'boolean'],
            [['id', 'name', 'switch'], 'safe'],
        ];
    }


    public static function getSort()
    {
        return new \yii\data\Sort([
            'attributes' => [
                'name' => [
                    'asc' => ['name' => SORT_ASC],
                    'desc' => ['name' => SORT_DESC],
                ],
            ],
        ]);
    }


}
