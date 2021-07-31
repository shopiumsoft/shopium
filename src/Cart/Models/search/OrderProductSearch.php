<?php

namespace panix\mod\cart\models\search;

use panix\engine\data\ActiveDataProvider;
use panix\mod\cart\models\OrderProduct;

class OrderProductSearch extends OrderProduct {

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['id', 'order_id'], 'integer'],
            [['name', 'slug'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios() {
        // bypass scenarios() implementation in the parent class
        return \yii\base\Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params) {
        $query = OrderProduct::find();
        $query->joinWith(['originalProduct']);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
			'pagination'=>['pageSize'=>999999]
        ]);

        $this->load($params);


        if (!$this->validate()) {
            // uncomment the following line if you do not want to any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id' => $this->id,
            'order_id' => $this->order_id,
        ]);



        $query->andFilterWhere(['like', 'name', $this->name]);


        return $dataProvider;
    }

}
