<?php

namespace panix\mod\shop\models\query;

use yii\db\ActiveQuery;
use panix\engine\traits\query\DefaultQueryTrait;
use panix\engine\traits\query\TranslateQueryTrait;

class ManufacturerQuery extends ActiveQuery
{

    use DefaultQueryTrait, FilterQueryTrait;

}
