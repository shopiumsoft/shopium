<?php

namespace panix\mod\shop\models\search;

use panix\engine\CMS;
use panix\mod\shop\models\Attribute;
use Yii;
use yii\base\Model;
use panix\engine\data\ActiveDataProvider;
use panix\mod\shop\models\Product;

/**
 * ProductRelatedSearch represents the model behind the search form about `panix\mod\shop\models\Product`.
 */
class ProductRelatedSearch extends Product
{

    public $exclude = null;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'price','sku'], 'string'],
            [['id'], 'integer'],
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

       // $query->joinWith(['categorization categories']); //, 'commentsCount'
       // $className = substr(strrchr(__CLASS__, "\\"), 1);


        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => self::getSort(),
            'pagination' => ['pageSize' => 20]
        ]);


        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }


        // Id of product to exclude from search
        if ($this->exclude) {
            foreach ($this->exclude as $id) {
                $query->andFilterWhere(['!=', self::tableName() . '.id', $id]);
            }

        }

        $query->andFilterWhere(['id' => $this->id]);
        $query->andFilterWhere(['like', 'name_' . Yii::$app->language, $this->name]);
        $query->andFilterWhere(['like', 'sku', $this->sku]);

        return $dataProvider;
    }

}
