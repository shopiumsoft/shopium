<?php

namespace Shopium\Components;

use panix\engine\CMS;
use panix\mod\shop\models\ProductVariant;
use Yii;
use yii\base\Component;
use panix\mod\shop\models\Product;
use panix\mod\shop\models\Currency;
use yii\web\Response;
use yii\web\Session;

class Cart extends Component
{

    /**
     * Array of products added to cart.
     * E.g:
     * array(
     *      'product_id'      => 1,
     *      'variants'        => array(ProductVariant_id),
     *      'configurable_id' => 2, // Id of configurable product or false.
     *      'quantity'        => 3,
     *      'price'           => 123 // Price of one item
     * )
     * @var array
     */
    private $_items = [];

    public $totalPrice = 0;
    /**
     * @var Session
     */
    public $session;
    public $data = [];

    /** @var \panix\mod\shop\models\Product */
    protected $productModel;

    public function init()
    {
        $this->session = Yii::$app->session;
        //$this->session->id = 'cart';
        $this->session->setTimeout(86000);
        $this->session->setCookieParams(['lifetime' => 86000]);
        if (!isset($this->session['cart_data']) || !is_array($this->session['cart_data'])) {
            $this->session['cart_data'] = [];
        }

        if (!isset($this->session['test']) || !is_array($this->session['test'])) {
            $this->session['test'] = [];
        }
        /** @var \panix\mod\shop\models\Product $productModel */
        $this->productModel = Yii::$app->getModule('shop')->model('Product');

    }

    /**
     * Add product to cart
     * <pre>
     *      Yii::$app->cart->add([
     *         'product_id'      => $model->id,
     *         'variants'        => $variants,// e.g: [1,2,3,...]
     *         'configurable_id' => $configurable_id,
     *         'quantity'        => (int) Yii::$app->request->post('quantity', 1),
     *         'price'           => $model->price,
     *      ]);
     * </pre>
     * @param array $data
     */
    public function add(array $data)
    {
        $itemIndex = $this->getItemIndex($data);

        $currentData = $this->getData();
        if (isset($currentData['items'][$itemIndex])) {
            //echo $currentData[$itemIndex]['quantity'];
            //die();
            if ($currentData['items'][$itemIndex]['quantity']) {
                $currentData['items'][$itemIndex]['quantity'] += (int)$data['quantity'];
                if ($currentData['items'][$itemIndex]['quantity'] > 999) {
                    $currentData['items'][$itemIndex]['quantity'] = 999;
                }
            }
        } else {
            $currentData['items'][$itemIndex] = $data;
        }
        $this->session['cart_data'] = $currentData;
    }

    public function acceptPoint($bonus = 0)
    {
        $data = $this->getData();
        $this->session['cart_data'] = [
            'items' => $data['items'],
            'bonus' => $bonus
        ];
    }

    /**
     * Removed item from cart
     * @param $index string generated by self::getItemIndex() method
     */
    public function remove($index)
    {
        $currentData = $this->getData();
        if (isset($currentData['items'][$index])) {
            unset($currentData['items'][$index]);
            //$this->session['cart_data'] = $currentData;
            $this->session['cart_data'] = $currentData;
        }
    }

    /**
     * Clear all cart data
     */
    public function clear()
    {
        $this->session['cart_data'] = [];
    }

    /**
     * @return array current cart data
     */
    public function getData()
    {
        return $this->session['cart_data'];
    }

    /**
     * Load products added to cart
     * @return array
     */
    public function getDataWithModels()
    {
        $data = $this->getData();

        if (empty($data['items']))
            return [];


        foreach ($data['items'] as $index => &$item) {

            $item['variant_models'] = [];
            $item['model'] = $this->productModel::findOne($item['product_id']);
            $model = $item['model'];
            // If product was deleted during user session!.
            if (!$model) {
                unset($data['items'][$index]);
                $this->remove($index);

                continue;
            }

            // Load configurable product
            if ($item['configurable_id'])
                $item['configurable_model'] = $this->productModel::findOne($item['configurable_id']);

            $item['attributes_data'] = json_decode($item['attributes_data']);


            $configurable = isset($item['configurable_model']) ? $item['configurable_model'] : 0;
            $this->totalPrice += $this->productModel::calculatePrices($model, $item['variants'], $configurable, $item['quantity']);


            // Process variants @todo PANIX need test
            if (!empty($item['variants']))
                $item['variant_models'] = ProductVariant::find()
                    ->joinWith(['productAttribute', 'option'])
                    ->where([ProductVariant::tableName() . '.id' => $item['variants']])
                    ->all();


        }

        unset($item);

        $this->data = $data;
        return $this->data;
    }


    /**
     * Count total price
     */
    public function getTotalPrice($onlyDiscount = false)
    {
        $result = 0;
        $data = $this->getDataWithModels();
        if (isset($data['items'])) {
            foreach ($data['items'] as $item) {
                $configurable = isset($item['configurable_model']) ? $item['configurable_model'] : 0;
                if ($onlyDiscount) {
                    if (!$item['model']->hasDiscount) {
                        $result += $this->productModel::calculatePrices($item['model'], $item['variants'], $configurable, $item['quantity']) * $item['quantity'];
                    }
                } else {
                    $result += $this->productModel::calculatePrices($item['model'], $item['variants'], $configurable, $item['quantity']) * $item['quantity'];
                }

            }
        }
        //if(isset($data['bonus'])){
        //     $result -= $data['bonus'];
        // }
        return $result;
    }
    /*
        public function ss($orderTotal)
        {

            //$result=[];
            // $result['success']=false;
            $config = Yii::$app->settings->get('user');
            $totalPrice = 100000;
            $points = (Yii::$app->user->identity->points * (int)$config->bonus_value);


            // $profit = round((($totalPrice-$pc)/$totalPrice)*100,2);
            $profit = (($orderTotal - $points) / $orderTotal) * 100;
            if ($profit >= (int)$config->bonus_max_use_order) {
                $points2 = Yii::$app->request->post('bonus');
                $this->acceptPoint($points2);
                return true;
            } else {
                $this->acceptPoint(0);
                return false;
            }
        }
    */
    /**
     * @param $data
     * @return array
     */
    public function ajaxRecount($data)
    {

        if (!is_array($data) || empty($data))
            return;

        $currentData = $this->getData();
        $rowTotal = 0;
        $calcPrice = 0;

        foreach ($data as $index => $quantity) {
            if ((int)$quantity < 1)
                $quantity = 1;


            if (isset($currentData['items'][$index])) {

                $currentData['items'][$index]['quantity'] = (int)$quantity;
                $data = $currentData['items'][$index];


                $productModel = $this->productModel::findOne($data['product_id']);

                $calcPrice = $this->productModel::calculatePrices($productModel, $data['variants'], $data['configurable_id'], $data['quantity']);
                if ($data['configurable_id']) {

                    $rowTotal = $calcPrice * $data['quantity'];
                } else {
                    //if ($productModel->hasDiscount) {
                    //$priceTotal = ;
                    //} else {
                    //     $priceTotal = $data['price'];
                    //}

                    //if ($data['quantity'] > 1 && ($pr = $productModel->getPriceByQuantity($data['quantity']))) {
                    //    $calcPrice = $pr->value;
                    //}

                    $rowTotal = $calcPrice * $data['quantity'];

                }
            }
            //$total+=$rowTotal;
        }

        // $this->session['cart_data'] = $currentData;


        $points2 = 0;
        if (isset(Yii::$app->request->post('OrderCreateForm')['points'])) {
            $totalSummary = $this->getTotalPrice(true);
            $total = $this->getTotalPrice();

            $points2 = (Yii::$app->request->post('OrderCreateForm')['points'])?Yii::$app->request->post('OrderCreateForm')['points']:0;
            $bonusData = [];
            $config = Yii::$app->settings->get('user');
            $points = ($points2 * (int)$config->bonus_value);
            // $profit = round((($totalPrice-$pc)/$totalPrice)*100,2);
            $profit = (($totalSummary - $points) / $totalSummary) * 100;
            // echo $total;die;

            if ($points2 > 0) {
                if ($points2 <= Yii::$app->user->identity->points) {
                    if ($profit >= (int)$config->bonus_max_use_order) {
                        $bonusData['message'] = Yii::t('default', 'BONUS_ACTIVE', $points2);
                        $bonusData['success'] = true;
                        $bonusData['value'] = $points2;
                        $total -= $points2;
                    } else {
                        $points2 = 0;
                        $bonusData['message'] = Yii::t('default', 'BONUS_NOT_ENOUGH');
                        $bonusData['success'] = false;
                    }

                } else {
                    $points2 = 0;
                    $bonusData['message'] = Yii::t('default', 'BONUS_NOT_ENOUGH');
                    $bonusData['success'] = false;
                }
            } else {
                $points2 = 0;
                $bonusData['message'] = '???? ???????????????? ????????????';
                $bonusData['success'] = false;

            }
            $response['bonus'] = $bonusData;

        }


        $this->session['cart_data'] = [
            'items' => $currentData['items'],
            'bonus' => $points2
        ];


        //$this->session['cart_data'] = $currentData;


        $response['unit_price'] = Yii::$app->currency->number_format(Yii::$app->currency->convert($calcPrice));
        $response['rowTotal'] = Yii::$app->currency->number_format($rowTotal);
        $response['total_price'] = Yii::$app->currency->number_format((isset($total)) ? $total : $this->getTotalPrice());

        return $response;
    }

    /**
     * Recount quantity by index
     * @param $data array(index=>quantity)
     */
    public function recount($data)
    {
        if (!is_array($data) || empty($data))
            return;

        $currentData = $this->getData();
        foreach ($data['items'] as $index => $quantity) {
            if ((int)$quantity < 1)
                $quantity = 1;

            if (isset($currentData[$index]))
                $currentData['items'][$index]['quantity'] = (int)$quantity;
        }


        $this->session['cart_data'] = $currentData;

    }

    /**
     * @return int number of items in cart
     */
    public function countItems()
    {
        $result = 0;

        if (isset($this->session['cart_data']['items'])) {
            foreach ($this->session['cart_data']['items'] as $row)
                $result += $row['quantity'];
        }
        return $result;
    }

    /**
     * Create item index base on data
     * @param $data
     * @return string
     */
    public function getItemIndex($data)
    {

        return $data['product_id'] . implode('_', $data['variants']) . $data['configurable_id'];
    }

}
