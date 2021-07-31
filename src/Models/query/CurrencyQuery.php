<?php

namespace panix\mod\shop\models\query;

use panix\engine\behaviors\nestedsets\NestedSetsQueryBehavior;
use panix\engine\traits\query\TranslateQueryTrait;
use panix\engine\traits\query\DefaultQueryTrait;
use yii\db\ActiveQuery;
use yii\helpers\Url;

/**
 * Class CurrencyQuery
 * @package panix\mod\shop\models\query
 * @use ActiveQuery
 */
class CurrencyQuery extends ActiveQuery
{

    use DefaultQueryTrait;

}
