<?php
/**
 * Created by PhpStorm.
 * User: nts
 * Date: 31.3.18.
 * Time: 16.48
 */

namespace PrestaShop\Models;


use PrestaShop\Utils\Model;

class Customer extends Model
{

    protected $primaryKey = 'id';
    protected $entity     = '/customers';

}