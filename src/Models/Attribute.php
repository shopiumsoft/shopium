<?php

namespace panix\mod\shop\models;

use panix\engine\CMS;
use panix\mod\shop\components\ExternalFinder;
use Yii;
use yii\caching\DbDependency;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use panix\mod\shop\models\query\AttributeQuery;
use panix\engine\db\ActiveRecord;

/**
 * This is the model class for table "Attribute".
 *
 * The followings are the available columns in table 'Attribute':
 * @property integer $id
 * @property string $name
 * @property string $hint
 * @property string $title
 * @property integer $type
 * @property boolean $display_on_front
 * @property boolean $display_on_list
 * @property integer $ordern
 * @property boolean $required
 * @property boolean $use_in_compare
 * @property string $abbreviation
 * @property integer $sort
 * @property AttributeOption[] $options
 * @property TypeAttribute[] $types
 * @property boolean $use_in_filter Display attribute options as filter on front
 * @property boolean $use_in_variants Use attribute and its options to configure products
 * @property boolean $select_many Allow to filter products on front by more than one option value.
 * @method Category useInFilter()
 */
class Attribute extends ActiveRecord
{

    const TYPE_TEXT = 1;
    const TYPE_TEXTAREA = 2;
    const TYPE_DROPDOWN = 3;
    const TYPE_SELECT_MANY = 4;
    const TYPE_RADIO_LIST = 5;
    const TYPE_CHECKBOX_LIST = 6;
    const TYPE_YESNO = 7;

    const TYPE_SLIDER = 8;//todo new Under construction
    const TYPE_COLOR = 9;//todo new Under construction

    const MODULE_ID = 'shop';
   // public $translationClass = AttributeTranslate::class;


    public static function find()
    {
        return new AttributeQuery(get_called_class());
    }

    public function getGridColumns()
    {
        return [
            'title' => [
                'attribute' => 'title',
                'contentOptions' => ['class' => 'text-left'],
            ],
            'name' => [
                'attribute' => 'name',
                'contentOptions' => ['class' => 'text-left'],
            ],
            'group_id' => [
                'attribute' => 'group_id',
                'filterInputOptions' => ['class' => 'custom-select d-inline', 'id' => null, 'prompt' => Yii::t('app/default', 'ALL')],
                'filter' => ArrayHelper::map(AttributeGroup::find()->all(), 'id', 'name'),
                'value' => 'group.name',
                'contentOptions' => ['class' => 'text-center'],
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

    public function transactions2()
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
        return '{{%shop__attribute}}';
    }

    public function getOptions()
    {
        // $table = self::tableName();
        // $dependency = new DbDependency();
        // $dependency->sql = "SELECT MAX(updated_at) FROM {$table}";

        return $this->hasMany(AttributeOption::class, ['attribute_id' => 'id']);//->orderBy([AttributeOption::tableName().".ordern" => SORT_DESC]);//->cache(3600, $dependency);
    }


    public function getOptionsArray()
    {
        // $table = self::tableName();
        // $dependency = new DbDependency();
        // $dependency->sql = "SELECT MAX(updated_at) FROM {$table}";
        return $this->hasMany(AttributeOption::class, ['attribute_id' => 'id'])->asArray();//->cache(3600, $dependency);
    }

    public function getGroups()
    {
        return $this->hasMany(AttributeGroup::class, ['id' => 'group_id']);
    }

    public function getGroup()
    {
        return $this->hasOne(AttributeGroup::class, ['id' => 'group_id']);
    }

    public function getTypes()
    {
        return $this->hasMany(TypeAttribute::class, ['attribute_id' => 'id']);
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            [['name', 'title', 'type'], 'required'],
            [['name', 'title'], 'trim'],
            ['name', '\panix\engine\validators\UrlValidator',
                'attributeCompare' => 'title',
                'attributeSlug' => 'name',
                'replacement'=>'_',
                'message' => self::t('ID_BUSY')
            ],
            [['name', 'title', 'abbreviation'], 'string', 'max' => 255],
            [[
                'required',
                'use_in_compare',
                'use_in_filter',
                'select_many',
                'display_on_front',
                'display_on_list',
                'display_on_grid',
                'display_on_cart',
                'display_on_pdf',
                'use_in_variants'
            ], 'boolean'],
            ['name', 'match',
                'pattern' => '/^([a-z0-9-\_])+$/i',
                'message' => Yii::t('app/default', 'PATTERN_URL')
            ],
            [['sort'], 'default', 'value' => null],
            [['hint', 'abbreviation'], 'string'],
            [['id', 'group_id', 'sort', 'type'], 'integer'],
            [['id', 'name', 'title', 'type'], 'safe'],
        ];
    }

    public function behaviors()
    {
        return ArrayHelper::merge([
            'slug' => [
                'class' => '\yii\behaviors\SluggableBehavior',
                'attribute' => 'title',
                'slugAttribute' => 'name',
            ],
            'translate' => [
                'class' => '\panix\mod\shop\components\TranslateBehavior',
                'translationAttributes' => ['title','abbreviation','hint']
            ]
        ], parent::behaviors());
    }

    /**
     * Get types as key value list
     * @static
     * @return array
     */
    public static function typesList()
    {
        return [
            self::TYPE_TEXT => 'Text',
            self::TYPE_TEXTAREA => 'Textarea',
            self::TYPE_DROPDOWN => 'Dropdown (Filter)',
            self::TYPE_SELECT_MANY => 'Multiple Select (Filter)',
            self::TYPE_RADIO_LIST => 'Radio List (Filter)',
            self::TYPE_CHECKBOX_LIST => 'Checkbox List (Filter)',
            self::TYPE_YESNO => 'Yes/No',
            //self::TYPE_SLIDER => 'Слайдер',
            //self::TYPE_COLOR => 'Цвет',
        ];
    }

    /**
     * Get sorting list
     * @return array
     */
    public static function sortList()
    {
        return [
            SORT_ASC => self::t('SORT_ASC'),
            SORT_DESC => self::t('SORT_DESC'),
        ];
    }

    public static function getSort()
    {
        return new \yii\data\Sort([
            'attributes' => [
                'title' => [
                    'asc' => ['title' => SORT_ASC],
                    'desc' => ['title' => SORT_DESC],
                ],
            ],
        ]);
    }

    /**
     * Validate duplicates values of options
     * @return bool
     */
    public function validateOptions()
    {
        $post = Yii::$app->request->post();

        if (isset($post['options'])) {
            $opt = [];
            foreach ($post['options'] as $k => $v) {
                $opt[] = $v[0];
            }

            if (CMS::hasDuplicates($opt)) {
                $this->tab_errors[($this->type == self::TYPE_COLOR) ? 'color' : 'options'] = self::t('ERROR_DUPLICATE_OPTIONS');
                $this->addError('name', self::t('ERROR_DUPLICATE_OPTIONS'));
                return false;
            }
        }
        return true;
    }


    /**
     * @param null $value
     * @param string $inputClass
     * @return string html field based on attribute type
     */
    public function renderField($value = null, $inputClass = '')
    {

        $name = 'Attribute[' . $this->type . '][' . $this->name . ']';//@todo added $this->type[' . $this->type . ']
        switch ($this->type) {
            case self::TYPE_TEXT:
                return Html::textInput($name, $value, ['class' => 'form-control ' . $inputClass]);
                break;
            case self::TYPE_TEXTAREA:
                return Html::textarea($name, $value, ['class' => 'form-control ' . $inputClass]);
                break;
            case self::TYPE_DROPDOWN:
                $data = ArrayHelper::map($this->options, 'id', 'value');
                return Html::dropDownList($name, $value, $data, [
                    'class' => 'form-control ' . $inputClass,
                    'prompt' => html_entity_decode(Yii::t('app/default', 'EMPTY_LIST'))
                ]);
                //return Yii::app()->controller->widget('ext.bootstrap.selectinput.SelectInput',array('data'=>$data,'value'=>$value,'htmlOptions'=>array('name'=>$name,'empty'=>Yii::t('app/default','EMPTY_LIST'))),true);
                break;
            case self::TYPE_SELECT_MANY:
                $data = ArrayHelper::map($this->options, 'id', 'value');
                return Html::dropDownList($name . '[]', $value, $data, [
                    'class' => 'form-control ' . $inputClass,
                    'multiple' => 'multiple',
                    'prompt' => html_entity_decode(Yii::t('app/default', 'EMPTY_LIST'))
                ]);
                break;
            case self::TYPE_RADIO_LIST:
                $data = ArrayHelper::map($this->options, 'id', 'value');
                return Html::radioList($name, $value, $data, ['separator' => '<br/>']);
                break;
            case self::TYPE_CHECKBOX_LIST:
                $data = ArrayHelper::map($this->options, 'id', 'value');

                return Html::checkboxList($name . '[]', $value, $data, [
                    'separator' => '',
                    'tag' => false,
                    'item' => function ($index, $label, $name, $checked, $value) {
                        return '<div class="custom-control custom-checkbox">
' . Html::checkbox($name, $checked, ['value' => $value, 'class' => 'custom-control-input', 'id' => $this->getIdByName() . $index]) . '
' . Html::label($label, $this->getIdByName() . $index, ['class' => 'custom-control-label']) . '
</div>';
                    }
                ]);
                break;
            case self::TYPE_YESNO:
                $data = [
                    1 => Yii::t('app/default', 'YES'),
                    2 => Yii::t('app/default', 'NO')
                ];
                return Html::dropDownList($name, $value, $data);
                break;
            case self::TYPE_COLOR:

                $data = ArrayHelper::map($this->options, 'id', 'value');
                return Html::dropDownList($name . '[]', $value, $data, [
                    'class' => 'form-control ' . $inputClass,
                    'prompt' => html_entity_decode(Yii::t('app/default', 'EMPTY_LIST'))
                ]);
                break;


        }
    }

    /**
     * Get attribute value
     * @param $value
     * @return string attribute value
     */
    public function renderValue($value)
    {
        switch ($this->type) {
            case self::TYPE_TEXT:
            case self::TYPE_TEXTAREA:
                return $value;
                break;
            case self::TYPE_DROPDOWN:
            case self::TYPE_COLOR:
            case self::TYPE_RADIO_LIST:
                $data = ArrayHelper::map($this->options, 'id', 'value');
                if (!is_array($value) && isset($data[$value]))
                    return $data[$value];
                break;
            case self::TYPE_SELECT_MANY:
            case self::TYPE_CHECKBOX_LIST:
                $data = ArrayHelper::map($this->options, 'id', 'value');
                $result = [];

                if (!is_array($value))
                    $value = [$value];

                foreach ($data as $key => $val) {
                    if (in_array($key, $value))
                        $result[] = $val;
                }
                return implode(', ', $result);
                break;
            case self::TYPE_YESNO:
                $data = [
                    1 => Yii::t('app/default', 'YES'),
                    2 => Yii::t('app/default', 'NO')
                ];
                if (isset($data[$value]))
                    return $data[$value];
                break;

        }
    }

    /**
     * @return string html id based on name
     */
    public function getIdByName()
    {
        // echo $this->formName();die;
        $name = $this->formName() . '-' . $this->name;
        //return Html::getInputId($this, $this->name);
        return mb_strtolower($name);
    }

    /**
     * Get type label
     * @static
     * @param $type
     * @return string
     */
    public static function getTypeTitle($type)
    {
        $list = self::typesList();
        return $list[$type];
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if (!in_array($this->type, [self::TYPE_DROPDOWN, self::TYPE_RADIO_LIST, self::TYPE_CHECKBOX_LIST, self::TYPE_SELECT_MANY, self::TYPE_COLOR])) {
            $this->use_in_filter = false;
            $this->select_many = false;
        }
        return parent::beforeSave($insert);
    }


    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        // Delete options
        foreach ($this->options as $o)
            $o->delete();

        // Delete relations used in product type.
        TypeAttribute::deleteAll(['attribute_id' => $this->id]);

        // Delete attributes assigned to products
        self::getDb()->createCommand()->delete(ProductAttributesEav::tableName(), "`attribute`='{$this->name}'")->execute();
        if (Yii::$app->hasModule('csv')) {
            $external = new ExternalFinder('{{%csv}}');
            $external->deleteObject(ExternalFinder::OBJECT_ATTRIBUTE, $this->id);
        }

        return parent::afterDelete();
    }

}
