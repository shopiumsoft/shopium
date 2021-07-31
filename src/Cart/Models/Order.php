<?php

namespace Shopium\Cart\Models;

use panix\engine\CMS;
use panix\mod\cart\models\search\OrderSearch;
use panix\mod\news\models\search\NewsSearch;
use panix\mod\novaposhta\models\Warehouses;
use panix\mod\shop\models\ProductType;
use Yii;
use yii\base\ModelEvent;
use yii\helpers\ArrayHelper;
use panix\engine\Html;
use panix\engine\db\ActiveRecord;
use panix\mod\cart\components\events\EventProduct;
use panix\mod\cart\components\HistoricalBehavior;

/**
 * Class Order
 * @property integer $id
 * @property integer $user_id
 * @property integer $status_id
 * @property integer $payment_id
 * @property integer $delivery_id
 * @property integer $promocode_id
 * @property string $secret_key
 * @property float $total_price
 * @property float $total_price_purchase
 * @property float $delivery_price
 * @property float $full_price
 * @property string $user_name
 * @property string $user_email
 * @property string $user_lastname
 * @property string $delivery_address
 * @property string $user_phone
 * @property string $delivery_city
 * @property string $user_comment
 * @property string $admin_comment
 * @property string $user_agent
 * @property string $discount
 * @property string $ttn
 * @property integer $created_at
 * @property integer $updated_at
 * @property integer $points
 * @property boolean $paid
 * @property boolean $buyOneClick
 * @property boolean $apply_user_points
 * @property boolean $call_confirm
 * @property string $delivery_type
 * @property OrderStatus $status
 * @property OrderProduct[] $products
 * @property Delivery $deliveryMethod
 * @property Payment $paymentMethod
 * @property PromoCode $promoCode
 *
 * @property string $_ttn
 *
 * @package panix\mod\cart\models
 */
class Order extends ActiveRecord
{

    const MODULE_ID = 'cart';
    const route = '/admin/cart/default';

    const STATUS_NEW = 1; //Новый
    const STATUS_DELETE = 2; //Удален
    const STATUS_SUBMITTED = 3; //Отправлен
    const STATUS_COMPLETED = 4; //Выполнен
    const STATUS_RETURN = 5; //Возврат

    /**
     * @var string
     */
    private $_ttn;
    //public $delivery_type;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%order}}';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $a = [];
        $a['historical'] = [
            'class' => HistoricalBehavior::class,
        ];
        return ArrayHelper::merge($a, parent::behaviors());
    }

    public static function getTotal($provider, $fieldName)
    {
        $total = 0;

        foreach ($provider as $item) {
            $total += $item[$fieldName];
        }

        return Yii::$app->currency->number_format($total) . ' ' . Yii::$app->currency->main['symbol'];
    }

    public static function find()
    {
        return new query\OrderQuery(get_called_class());
    }

    public function getPromoCode()
    {
        return $this->hasOne(PromoCode::class, ['id' => 'promocode_id']);
    }

    /**
     * Relation
     * @return \yii\db\ActiveQuery
     */
    public function getDeliveryMethod()
    {
        return $this->hasOne(Delivery::class, ['id' => 'delivery_id']);
    }

    /**
     * Relation
     * @return \yii\db\ActiveQuery
     */
    public function getPaymentMethod()
    {
        return $this->hasOne(Payment::class, ['id' => 'payment_id']);
    }

    /**
     * Relation
     * @return \yii\db\ActiveQuery
     */
    public function getStatus()
    {
        return $this->hasOne(OrderStatus::class, ['id' => 'status_id']);
    }

    /**
     * Relation
     * @return \yii\db\ActiveQuery
     */
    public function getProducts()
    {
        return $this->hasMany(OrderProduct::class, ['order_id' => 'id']);
    }

    /**
     * Relation
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(Yii::$app->user->identityClass, ['id' => 'user_id']);
    }

    /**
     * Relation
     * @return int|string
     */
    public function getProductsCount()
    {
        return $this->hasMany(OrderProduct::class, ['order_id' => 'id'])->count();
    }

    public function getUrl()
    {
        return ['/cart/default/view', 'secret_key' => $this->secret_key];
    }

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios['buyOneClick'] = ['user_phone'];
        return $scenarios;
    }

    public function rules()
    {
        return [
            ['user_phone', 'panix\ext\telinput\PhoneInputValidator', 'on' => self::SCENARIO_DEFAULT],
            ['user_phone', 'string', 'on' => 'buyOneClick'],
            [['user_name', 'user_email', 'delivery_id', 'payment_id', 'user_phone'], 'required'],
            ['user_email', 'email'],
            [['user_comment', 'admin_comment', 'delivery_city'], 'string', 'max' => 500],
            [['delivery_address'], 'string', 'max' => 255],
            [['user_phone'], 'string', 'max' => 30],
            [['user_name', 'user_email', 'discount', 'ttn'], 'string', 'max' => 100],
            [['ttn'], 'default'],
            [['invoice'], 'string', 'max' => 50],
            [['paid', 'apply_user_points'], 'boolean'],
            [['delivery_city_ref', 'delivery_warehouse_ref', 'delivery_type', 'user_lastname'], 'string'],
            ['delivery_id', 'validateDelivery'],
            ['payment_id', 'validatePayment'],
            ['status_id', 'validateStatus'],
            ['promocode_id', 'validatePromoCode'],
        ];
    }

    public function attributeLabels()
    {
        return array_merge(['delivery_type' => self::t('DELIVERY_TYPE')], parent::attributeLabels());
    }

    public function validatePromoCode($attribute)
    {
        $value = $this->{$attribute};

        if (is_string($value)) {
            $promo = PromoCode::find()->where(['code' => $value])->one();
            if ($promo) {
                $this->{$attribute} = $promo->id;
            } else {
                $this->addError($attribute, 'Error promocode');
            }
        }

    }

    /**
     * Check if delivery method exists
     */
    public function validateDelivery()
    {
        if (Delivery::find()->where(['id' => $this->delivery_id])->count() == 0)
            $this->addError('delivery_id', Yii::t('cart/admin', 'Необходимо выбрать способ доставки.'));
    }

    public function validatePayment()
    {
        if (Payment::find()->where(['id' => $this->payment_id])->count() == 0)
            $this->addError('payment_id', Yii::t('cart/admin', 'Необходимо выбрать способ оплаты.'));
    }

    /**
     * Check if status exists
     */
    public function validateStatus()
    {
        if ($this->status_id && OrderStatus::find()->where(['id' => $this->status_id])->count() == 0)
            $this->addError('status_id', Yii::t('cart/admin', 'Ошибка проверки статуса.'));
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {

        if ($this->isNewRecord) {
            $this->secret_key = $this->createSecretKey();
            $this->ip_create = Yii::$app->request->getUserIP();


            if (!Yii::$app->user->isGuest)
                $this->user_id = Yii::$app->user->id;
        }


        // Set `New` status
        if (!$this->status_id)
            $this->status_id = Order::STATUS_NEW;


        //isset($this->oldAttributes['status_id']) && $this->attributes['status_id'] &&
        if ($this->user_id && $this->apply_user_points) {
            if ($this->attributes['status_id'] == self::STATUS_RETURN) {
                $this->user->unsetPoints(floor($this->total_price * Yii::$app->settings->get('user', 'bonus_ratio')));
                $this->apply_user_points = false;
            }
        }


        /*OLD if (isset($this->oldAttributes['status_id']) && $this->attributes['status_id'] && $this->apply_user_points) {
            if ($this->oldAttributes['status_id'] == self::STATUS_SUBMITTED && $this->attributes['status_id'] != self::STATUS_SUBMITTED) {
                $this->user->unsetPoints(floor($this->total_price * Yii::$app->settings->get('user', 'bonus_ratio')));
                $this->apply_user_points = false;
            }
        }*/


        if ($this->status_id == self::STATUS_SUBMITTED && $this->user_id && !$this->apply_user_points) {
            $this->user->setPoints(floor($this->total_price * Yii::$app->settings->get('user', 'bonus_ratio')));
            $this->apply_user_points = true;
        }


        if ($this->isNewRecord) {
            $delivery = Delivery::findOne($this->delivery_id);
        } else {
            $delivery = $this->deliveryMethod;
        }
        if ($delivery) {
            if ($delivery->system == 'novaposhta') {

                // $this->delivery_city_ref = $this->form->delivery_city_ref;
                //$this->delivery_warehouse_ref = $this->form->delivery_warehouse;
                $warehouse = Warehouses::findOne($this->delivery_warehouse_ref);
                if ($warehouse) {
                    $this->delivery_city = $warehouse->getCityDescription();
                    $this->delivery_address = $warehouse->getDescription();
                }
            }
        }

        return parent::beforeSave($insert);
    }

    public function afterFind()
    {
        $this->_ttn = $this->ttn;
        parent::afterFind();
    }

    /**
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {


        $send_ttn = false;
        if ($this->ttn) {
            if (isset($this->oldAttributes['ttn']) && $this->oldAttributes['ttn'] != $this->ttn) {
                $send_ttn = true;
            }

            if ($this->ttn != $this->_ttn) {
                $send_ttn = true;
            }
            if ($send_ttn) {
                if ($this->user_email) {
                    $mailer = Yii::$app->mailer;
                    $mailer->htmlLayout = Yii::$app->getModule('cart')->mailPath . '/layouts/client';
                    $mailer->compose(['html' => Yii::$app->getModule('cart')->mailPath . '/ttn.tpl'], ['order' => $this])
                        ->setTo($this->user_email)
                        ->setSubject(Yii::t('cart/default', 'MAIL_TTN_SUBJECT', CMS::idToNumber($this->id)))
                        ->send();
                }
            }
        }


        if (isset($changedAttributes['status_id']) && Yii::$app->settings->get('cart', 'notify_changed_status')) {
            if ($changedAttributes['status_id'] != $this->status_id) {
                if ($this->user_email) {
                    $mailer = Yii::$app->mailer;
                    $mailer->htmlLayout = Yii::$app->getModule('cart')->mailPath . '/layouts/client';
                    $mailer->compose(['html' => Yii::$app->getModule('cart')->mailPath . '/changed_status.tpl'], ['order' => $this])
                        ->setTo([$this->user_email])
                        ->setSubject(Yii::t('cart/default', 'MAIL_CHANGE_STATUS_SUBJECT', CMS::idToNumber($this->id)))
                        ->send();
                }
            }
        }


        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * @return bool
     */
    public function afterDelete()
    {
        foreach ($this->products as $ordered_product)
            $ordered_product->delete();

        return parent::afterDelete();
    }

    /**
     * Create unique key to view orders
     * @param int $size
     * @return string
     */
    public function createSecretKey($size = 10)
    {

        $result = '';
        $chars = '1234567890qweasdzxcrtyfghvbnuioplkjnm';
        while (mb_strlen($result, 'utf8') < $size) {
            $result .= mb_substr($chars, rand(0, mb_strlen($chars, 'utf8')), 1);
        }

        if (static::find()->where(['secret_key' => $result])->count() > 0)
            $this->createSecretKey($size);

        return $result;
    }

    /**
     * Update total
     */
    public function updateTotalPrice()
    {

        $this->total_price = 0;
        $this->total_price_purchase = 0;
        $products = OrderProduct::find()->where(['order_id' => $this->id])->all();

        foreach ($products as $product) {
            /** @var OrderProduct $product */

            if ($product->originalProduct) {
                //if($product->currency_id && $product->currency_rate){
                //    $this->total_price += $product->price / $product->currency_rate * $product->quantity;
                //     $this->total_price_purchase += $product->price_purchase * $product->currency_rate * $product->quantity;
                // }else{
                $this->total_price += $product->price * $product->quantity;
                $this->total_price_purchase += $product->price_purchase * $product->quantity;
                // }

            }

        }

        /*if($this->promoCode){
            if ('%' === substr($this->promoCode->discount, -1, 1)) {
                $this->total_price -= $this->total_price * ((double) $this->promoCode->discount) / 100;
            }

        }*/

        $this->save(false);
    }

    /**
     * Update delivery price
     */
    public function updateDeliveryPrice()
    {
        if ($this->delivery_id) {
            $result = 0;
            $deliveryMethod = Delivery::findOne($this->delivery_id);

            if ($deliveryMethod) {
                if ($deliveryMethod->price > 0) {
                    if ($deliveryMethod->free_from > 0 && $this->total_price > $deliveryMethod->free_from)
                        $result = 0;
                    else
                        $result = $deliveryMethod->price;
                }
            }

            $this->delivery_price = $result;
            $this->save(false);
        }
    }

    public function getGridStatus()
    {
        return Html::tag('span', $this->getStatusName(), ['class' => 'badge', 'style' => 'background:' . $this->getStatusColor()]);
    }

    /**
     * @return mixed
     */
    public function getStatusName()
    {
        if ($this->status)
            return $this->status->name;
    }

    /**
     * @return mixed
     */
    public function getStatusColor()
    {
        if ($this->status)
            return $this->status->color;
    }

    /**
     * @return mixed
     */
    public function getDelivery_name()
    {
        $model = Delivery::findOne($this->delivery_id);
        if ($model)
            return $model->name;
    }

    public function getPayment_name()
    {
        $model = Payment::findOne($this->payment_id);
        if ($model)
            return $model->name;
    }

    /**
     * @return mixed
     */
    public function getFull_Price()
    {
        if (!$this->isNewRecord) {
            $result = $this->total_price;
            if ($this->discount) {
                $sum = $this->discount;
                if ('%' === substr($this->discount, -1, 1))
                    $sum = $result * (int)$this->discount / 100;
                $result -= $sum;
            }
            return $result;
        }
        return 0;
    }

    /**
     * Add product to existing order
     *
     * @param /panix/mod/shop/models/Product $product
     * @param integer $quantity
     * @param float $price
     */
    public function addProduct($product, $quantity, $price)
    {

        if (!$this->isNewRecord) {
            $ordered_product = new OrderProduct;
            $ordered_product->order_id = $this->id;
            $ordered_product->product_id = $product->id;
            $ordered_product->currency_id = $product->currency_id;
            $ordered_product->currency_rate = ($product->currency_id) ? Yii::$app->currency->getById($product->currency_id)->rate : NULL;
            $ordered_product->price_purchase = $product->price_purchase;
            $ordered_product->name = $product->name;
            $ordered_product->quantity = $quantity;
            $ordered_product->sku = $product->sku;
            $ordered_product->price = $price;
            $ordered_product->save();

            // Raise event
            $event = new EventProduct([
                'product_model' => $product,
                'ordered_product' => $ordered_product,
                'quantity' => $quantity
            ]);
            $this->eventProductAdded($event);


        }
    }

    /**
     * Delete ordered product from order
     *
     * @param $id
     */
    public function deleteProduct($id)
    {

        $model = OrderProduct::findOne($id);

        if ($model) {
            $model->delete();

            $event = new EventProduct([
                'ordered_product' => $model
            ]);
            $this->eventProductDeleted($event);
        }
    }

    /**
     * @return \panix\engine\data\ActiveDataProvider
     */
    public function getOrderedProducts()
    {
        $products = new search\OrderProductSearch();
        return $products->search([$products->formName() => ['order_id' => $this->id]]);
    }

    /**
     * @param $event
     */
    public function eventOrderStatusChanged($event)
    {
        $this->trigger(HistoricalBehavior::EVENT_ORDER_STATUS_CHANGED, $event);
    }

    /**
     * @param $event
     */
    public function eventProductAdded($event)
    {
        $this->trigger(HistoricalBehavior::EVENT_PRODUCT_ADDED, $event);
    }

    /**
     * @param $event
     */
    public function eventProductQuantityChanged($event)
    {
        $this->trigger(HistoricalBehavior::EVENT_PRODUCT_QUANTITY_CHANGED, $event);
    }

    public function eventProductDeleted($event)
    {
        $this->trigger(HistoricalBehavior::EVENT_PRODUCT_DELETED, $event);
    }

    /**
     * @param array $data
     */
    public function setProductQuantities(array $data)
    {
        foreach ($this->products as $product) {
            if (isset($data[$product->id])) {
                if ((int)$product->quantity !== (int)$data[$product->id]) {
                    $event = new ModelEvent($this, [
                        'ordered_product' => $product,
                        'new_quantity' => (int)$data[$product->id]
                    ]);
                    $this->onProductQuantityChanged($event);
                    //$this->trigger('onProductQuantityChanged');
                }

                $product->quantity = (int)$data[$product->id];
                $product->save(false);
            }
        }
    }

    public function getRelativeUrl()
    {
        return Yii::$app->urlManager->createUrl(['/cart/default/view', 'secret_key' => $this->secret_key]);
    }

    public function getAbsoluteUrl()
    {
        return Yii::$app->urlManager->createAbsoluteUrl(['/cart/default/view', 'secret_key' => $this->secret_key]);
    }

    /**
     * Load history
     *
     * @return array
     */
    public function getHistory()
    {
        return OrderHistory::find()
            ->where(['order_id' => $this->id])
            ->orderBy(['date_create' => SORT_ASC])
            ->all();
    }

    /**
     * @param array $emails Email recipients
     * @return \yii\mail\MailerInterface|\yii\swiftmailer\Mailer
     */
    public function sendAdminEmail($emails = [])
    {
        /** @var \yii\swiftmailer\Mailer $mailer */

        $tplPath = Yii::$app->settings->get('cart', 'mail_tpl_order');
        $mailer = Yii::$app->mailer;
        $mailer->compose(['html' => $tplPath], ['model' => $this, 'is_admin' => true])
            //->setFrom(['noreply@' . Yii::$app->request->serverName => Yii::$app->name . ' robot'])
            ->setTo($emails)
            ->setSubject(Yii::t('cart/default', 'MAIL_ADMIN_SUBJECT', $this->id))
            ->send();
        return $mailer;
    }

    /**
     * @param string|null $email Email recipient
     * @return bool|\yii\swiftmailer\Mailer
     */
    public function sendClientEmail($email = null)
    {
        if (!$email) {
            $email = $this->user_email;
        }
        if ($email) {

            $tplPath = Yii::$app->settings->get('cart', 'mail_tpl_order');
            /** @var \yii\swiftmailer\Mailer $mailer */
            $mailer = Yii::$app->mailer;
            $mailer->htmlLayout = Yii::$app->getModule('cart')->mailPath . '/layouts/client';
            $mailer->compose($tplPath, ['model' => $this, 'is_admin' => false])
                //->setFrom('noreply@' . Yii::$app->request->serverName)
                ->setTo($email)
                ->setSubject(Yii::t('cart/default', 'MAIL_CLIENT_SUBJECT', $this->id))
                ->send();

            return $mailer;
        }
        return false;
    }

    public function getGridColumns()
    {

        //  $price_max = self::find()->aggregatePrice('MAX')->asArray()->one();
        //  $price_min = self::find()->aggregatePrice('MIN')->asArray()->one();

        $columns = [];


        $columns['id'] = [
            'attribute' => 'id',
            'header' => Yii::t('cart/Order', 'ORDER_ID'),
            'format' => 'raw',
            'contentOptions' => ['class' => 'text-left'],
            'value' => function ($model) {
                /** @var $model static */
                Yii::$app->controller->view->registerCss("
                
                .mouse{
                border:1px solid red;
                width:13px;
                height:20px;
                position:relative;
                display: inline-block;
                border-radius: 7px;
               
                }
                .mouse:before{
                background-color:red;
                top:3px
                position:absolute;
                content:'';
                width:1px;
                height:5px;
                left:0;
                right:0;
                margin:0 auto;
                }
                
                ", [], 'css-mouse');
                $ss = '<span class="mouse"></span>';
                return $model->getGridStatus() . ' ' . \panix\engine\CMS::idToNumber($model->id);

            },
        ];
        $columns['user_name'] = [
            'attribute' => 'user_name',
            'header' => Yii::t('cart/Order', 'CONTACT'),
            'format' => 'raw',
            'contentOptions' => ['class' => 'text-left'],
            'value' => function ($model) {
                /** @var $model self */
                $badges = [];

                if (!$model->user_id) {
                    $badges[] = Html::tag('span', 'Гость', ['class' => 'badge badge-warning']);
                }
                if ($model->call_confirm) {
                    $badges[] = Html::tag('span', 'Не звонить', ['class' => 'badge badge-info']);
                }
                if ($model->buyOneClick) {
                    $badges[] = Html::tag('span', '1 клик', ['class' => 'badge badge-secondary']);
                }

                $phone = ($model->user_phone) ? Html::tel($model->user_phone) : $model->user_phone;
                return $model->user_name . ' ' . $model->user_lastname . ' ' . implode('', $badges) . '<br/>' . $phone . '<br/>' . Yii::$app->formatter->asEmail($model->user_email);

            },
        ];


        $columns['delivery_id'] = [
            'attribute' => 'delivery_id',
            'format' => 'raw',
            'contentOptions' => ['class' => 'text-left'],
            'filter' => ArrayHelper::map(Delivery::find()
                ->orderByName(SORT_ASC)
                ->all(), 'id', 'name'),
            'filterInputOptions' => ['class' => 'form-control', 'prompt' => html_entity_decode('&mdash;')],
            'value' => function ($model) {
                /** @var static $model */


                if ($model->deliveryMethod) {
                    $city = '';
                    $address = $model->delivery_address;
                    if ($model->deliveryMethod->system) {
                        $city = 'г. ' . $model->delivery_city;
                    }
                    return $model->deliveryMethod->name . '<br/>' . $city . '<br>' . $address;
                }
            }
        ];

        $columns['payment_id'] = [
            'attribute' => 'payment_id',
            'format' => 'raw',
            'contentOptions' => ['class' => 'text-center'],
            'filter' => ArrayHelper::map(Payment::find()
                ->orderByName(SORT_ASC)
                ->all(), 'id', 'name'),
            'filterInputOptions' => ['class' => 'form-control', 'prompt' => html_entity_decode('&mdash;')],
            'value' => function ($model) {
                /** @var static $model */
                return ($model->paymentMethod) ? $model->paymentMethod->name : null;
            }
        ];


        /*$columns['status_id'] = [
            'attribute' => 'status_id',
            'format' => 'raw',
            'contentOptions' => ['class' => 'text-center'],
            'value' => function ($model) {
                return $model->getGridStatus();
            },
            'filter' => ArrayHelper::map(OrderStatus::find()
                ->addOrderBy(['name' => SORT_ASC])
                ->all(), 'id', 'name'),
            'filterInputOptions' => ['class' => 'form-control', 'prompt' => html_entity_decode('&mdash;')],
        ];*/

        $columns['total_price'] = [
            'attribute' => 'total_price',
            'format' => 'raw',
            'class' => 'panix\engine\grid\columns\jui\SliderColumn',
            'max' => (int)Order::find()->aggregateTotalPrice('MAX'),
            'min' => (int)Order::find()->aggregateTotalPrice('MIN'),
            'prefix' => '<small>' . Yii::$app->currency->main['symbol'] . '</small>',
            'contentOptions' => ['class' => 'text-center', 'style' => 'position:relative'],
            'minCallback' => function ($value) {
                return Yii::$app->currency->number_format($value);
            },
            'maxCallback' => function ($value) {
                return Yii::$app->currency->number_format($value);
            },
            'value' => function ($model) {

                $priceHtml = Yii::$app->currency->number_format(Yii::$app->currency->convert($model->total_price));
                $symbol = Html::tag('small', Yii::$app->currency->main['symbol']);
                return Html::tag('span', $priceHtml, ['class' => 'text-success font-weight-bold h6']) . ' ' . $symbol;
            }
        ];


        $columns['created_at'] = [
            'attribute' => 'created_at',
            'class' => 'panix\engine\grid\columns\jui\DatepickerColumn',
            /*'filter' => \yii\jui\DatePicker::widget([
                'model' => new OrderSearch(),
                'attribute' => 'created_at',
                'dateFormat' => 'yyyy-MM-dd',
                'options' => ['class' => 'form-control']
            ]),
            'contentOptions' => ['class' => 'text-center'],
            'value' => function ($model) {
                return Yii::$app->formatter->asDatetime($model->created_at, 'php:d D Y H:i:s');
            }*/
        ];
        $columns['updated_at'] = [
            'attribute' => 'updated_at',
            'class' => 'panix\engine\grid\columns\jui\DatepickerColumn',
        ];

        $columns['DEFAULT_CONTROL'] = [
            'class' => 'panix\engine\grid\columns\ActionColumn',
            'template' => '{update}'
        ];
        //  $columns['DEFAULT_COLUMNS'] = [
        //  ['class' => 'panix\engine\grid\sortable\Column'],
        //['class' => 'panix\engine\grid\columns\CheckboxColumn']
        // ];

        return $columns;
    }

}
