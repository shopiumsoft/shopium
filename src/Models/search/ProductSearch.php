<?php

namespace panix\mod\shop\models\search;

use Yii;
use yii\base\Model;
use panix\engine\data\ActiveDataProvider;
use panix\mod\shop\models\Product;

/**
 * ProductSearch represents the model behind the search form about `panix\mod\shop\models\Product`.
 */
class ProductSearch extends Product
{

    public $exclude = null;
    public $price_min;
    public $price_max;
    // public $image;
    //public $commentsCount;
    public $name;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['price_min', 'price_max', 'supplier_id', 'manufacturer_id', 'main_category_id', 'type_id', 'currency_id', 'availability'], 'integer'],
            // [['image'],'boolean'],
            [['slug', 'sku', 'price', 'id', 'type_id'], 'safe'], //commentsCount
            [['name'], 'string'],
            [['switch', 'use_configurations'], 'boolean'],
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
        //$query->sort();


        $className = substr(strrchr(__CLASS__, "\\"), 1);


        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => self::getSort(),
        ]);
        if (isset($params[$className]['price']['min'])) {
            $this->price_min = $params[$className]['price']['min'];
            if (!is_numeric($this->price_min)) {
                $this->addError('price', Yii::t('yii', '{attribute} must be a number.', ['attribute' => 'min']));
                return $dataProvider;
            }
        }
        if (isset($params[$className]['price']['max'])) {
            $this->price_max = $params[$className]['price']['max'];
            if (!is_numeric($this->price_max)) {
                $this->addError('price', Yii::t('yii', '{attribute} must be a number.', ['attribute' => 'max']));
                return $dataProvider;
            }
        }


        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }


        if (isset($params[$className]['eav'])) {
            $result = array();
            foreach ($params[$className]['eav'] as $name => $eav) {
                if (!empty($eav)) {
                    $result[$name][] = $eav;
                }
            }

            $query->getFindByEavAttributes2($result);
        }

        if (isset($params[$className]['price']['max'])) {
             $query->applyPrice($params[$className]['price']['max'], '<=');
        }
        if (isset($params[$className]['price']['min'])) {
             $query->applyPrice($params[$className]['price']['min'], '>=');
        }

        // Id of product to exclude from search
        if ($this->exclude) {
            foreach ($this->exclude as $id) {
                $query->andFilterWhere(['!=', self::tableName() . '.id', $id]);
            }

        }
        if (isset($configure['conf'])) {
            $query->andWhere(['IN', 'id', $configure['conf']]);
        }
        if (strpos($this->id, ',')) {
            $query->andFilterWhere(['in',
                self::tableName() . '.id', explode(',', $this->id),
            ]);
        } else {
            $query->andFilterWhere([
                self::tableName() . '.id' => $this->id,
            ]);
            $query->andFilterWhere(['like', 'name_' . Yii::$app->language, $this->name]);
            //$query->andFilterWhere(['like', 'name_ru', $this->name]);
        }

        /*$query->andFilterWhere([
            '>=',
            'date_update',
            $this->date_update
        ]);*/


        // $query->andFilterWhere(['between', 'date_update', $this->start, $this->end]);
        //$query->andFilterWhere(['like', "DATE(CONVERT_TZ('date_update', 'UTC', '".Yii::$app->timezone."'))", $this->date_update.' 23:59:59']);
        //  $query->andFilterWhere(['like', "DATE(CONVERT_TZ('date_create', 'UTC', '".Yii::$app->timezone."'))", $this->date_create.]);


        $query->andFilterWhere(['like', 'sku', $this->sku]);
        $query->andFilterWhere(['supplier_id' => $this->supplier_id]);
        $query->andFilterWhere(['type_id' => $this->type_id]);
        $query->andFilterWhere(['manufacturer_id' => $this->manufacturer_id]);
        if ($this->main_category_id) {
            $query->joinWith(['categorization categories']);
            $query->andFilterWhere(['categories.category' => $this->main_category_id]);

        }

        if ($this->switch) {
            $query->andFilterWhere(['switch' => 0]);
        }
        if ($this->use_configurations) {
            $query->andFilterWhere(['use_configurations' => $this->use_configurations]);
        }
        if ($this->currency_id) {
            $query->andFilterWhere(['currency_id' => $this->currency_id]);
        }
        if ($this->availability) {
            $query->andFilterWhere(['availability' => $this->availability]);
        }


        // echo $query->createCommand()->rawSql; die;
        return $dataProvider;
    }

    public function attributeLabels()
    {
        return [
            'use_configurations' => Yii::t('shop/Product', 'USE_CONFIGURATIONS'),
            'type_id' => Yii::t('shop/Product', 'TYPE_ID'),
            'switch' => Yii::t('shop/Product', 'Только скрытые'),
            'currency_id' => Yii::t('shop/Product', 'CURRENCY_ID'),
            'availability' => Yii::t('shop/Product', 'AVAILABILITY')
        ];
    }

}
