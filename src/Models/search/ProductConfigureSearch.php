<?php

namespace panix\mod\shop\models\search;

use panix\engine\CMS;
use panix\mod\shop\models\Attribute;
use Yii;
use yii\base\Model;
use panix\engine\data\ActiveDataProvider;
use panix\mod\shop\models\Product;

/**
 * ProductConfigureSearch represents the model behind the search form about `panix\mod\shop\models\Product`.
 */
class ProductConfigureSearch extends Product
{

    public $exclude = null;
    public $ggeavAttributes;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['supplier_id', 'manufacturer_id', 'main_category_id'], 'integer'],
            // [['image'],'boolean'],
            [['slug', 'sku', 'price', 'id'], 'safe'], //commentsCount
            [['name'], 'string'],
            [['created_at', 'updated_at'], 'date', 'format' => 'php:Y-m-d']
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     * @param array $configure
     * @return ActiveDataProvider
     */
    public function search($params, $configure = [])
    {
        $query = Product::find();
        $query->sort();

        $query->joinWith(['categorization categories']); //, 'commentsCount'

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => self::getSort(),
            'pagination' => ['pageSize' => 50]
            /*'sort22' => [
                //'defaultOrder' => ['created_at' => SORT_ASC],
                'attributes' => [
                    'price',
                    'created_at',
                    'name' => [
                        'asc' => ['translations.name' => SORT_ASC],
                        'desc' => ['translations.name' => SORT_DESC],
                    ]
                ],
            ],*/
        ]);


        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }


        /*if (isset($params[$className]['eav'])) {
            $result = array();
            foreach ($params[$className]['eav'] as $name => $eav) {
                if (!empty($eav)) {
                    $result[$name][] = $eav;
                }
            }

            $query->getFindByEavAttributes2($result);
        }*/


        // Id of product to exclude from search
        if ($this->exclude) {
            foreach ($this->exclude as $id) {
                $query->andFilterWhere(['!=', self::tableName() . '.id', $id]);
            }

        }
        //if (isset($configure['conf'])) {
        //    $query->andWhere(['IN', 'id', $configure['conf']]);
        // }
        /*if (strpos($this->id, ',')) {
            $query->andFilterWhere(['in',
                self::tableName() . '.id', explode(',', $this->id),
            ]);
        } else {*/
        $query->andFilterWhere([
            self::tableName() . '.id' => $this->id,
        ]);
        // $query->andFilterWhere(['like', 'name', $this->name]);
        $query->andFilterWhere(['like', 'name_' . Yii::$app->language, $this->name]);
        // }


        $query->andFilterWhere(['like', 'sku', $this->sku]);
        $query->andFilterWhere(['supplier_id' => $this->supplier_id]);
        $query->andFilterWhere(['manufacturer_id' => $this->manufacturer_id]);


        if ($this->eavAttributes)
            $query->withEavAttributes($this->eavAttributes);


        return $dataProvider;
    }

}
