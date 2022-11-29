<?php

namespace PrestaShop\Module\Order17Vat\Entity;

use PrestaShop\PrestaShop\Adapter\Entity\ObjectModel;

/**
 * This entity database state is managed by PrestaShop ObjectModel
 */
class Vat extends ObjectModel
{
    /**
     * @var int
     */
    public $id_order;

    /**
     * @var int
     */
    public $is_vat_17;

    public static $definition = [
        'table' => 'order17vat',
        'primary' => 'id_vat17',
        'fields' => [
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'is_vat_17' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
        ],
    ];
}
