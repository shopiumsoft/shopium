<?php

namespace Shopium\Models\Search;

use panix\engine\data\ActiveDataProvider;
use panix\mod\shop\models\ProductNotifications;

class ProductNotificationsSearch extends ProductNotifications {

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['id', 'product_id'], 'integer'],
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
        $query = ProductNotifications::find();
        $query->joinWith('product');
        $query->groupBy('product_id');
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => self::getSort()
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere(['id' => $this->id]);

        return $dataProvider;
    }

}
