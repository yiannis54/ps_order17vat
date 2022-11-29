<?php

use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\Module\Order17Vat\Entity\Vat;
use PrestaShop\Module\Order17Vat\Exception\CannotCreateVatException;
use PrestaShop\Module\Order17Vat\Exception\CannotToggleVat17StatusException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ToggleColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface;
use Prestashop\Prestashop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Module\Exception\ModuleErrorException;
use PrestaShop\PrestaShop\Core\Search\Filters\OrderFilters;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\YesAndNoChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

require __DIR__ . '/vendor/autoload.php';

/**
 * Class Order17Vat holds extra vat information for order orders.
 */
class Order17Vat extends Module
{

    public function __construct()
    {
        $this->name = 'order17vat';
        $this->version = '1.0.0';
        $this->author = 'Yiannis K';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->getTranslator()->trans(
            'Order VAT 17%',
            [],
            'Modules.Order17Vat.Admin'
        );

        $this->description =
            $this->getTranslator()->trans(
                'Hold extra vat information for orders',
                [],
                'Modules.Order17Vat.Admin'
            );

        $this->ps_versions_compliancy = [
            'min' => '1.7.7.0',
            'max' => _PS_VERSION_,
        ];
    }

    /**
     * This function is required in order to make module compatible with new translation system.
     *
     * @return bool
     */
    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    /**
     * Installs table to hold vat info.
     *
     * @return bool
     */
    private function installTables(): bool
    {
        $sql = '
            CREATE TABLE IF NOT EXISTS `' . pSQL(_DB_PREFIX_) . 'order17vat` (
                `id_vat17` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,                
                `id_order` INT(10) UNSIGNED NOT NULL,
                `is_vat_17` TINYINT(1) NOT NULL,
                PRIMARY KEY (`id_vat17`)
            ) ENGINE=' . pSQL(_MYSQL_ENGINE_) . ' COLLATE=utf8_unicode_ci;
        ';

        return Db::getInstance()->execute($sql);
    }

    private function uninstallTables(): bool
    {
        $sql = 'DROP TABLE IF EXISTS `' . pSQL(_DB_PREFIX_) . 'order17vat`';
        return Db::getInstance()->execute($sql);
    }

    public function install(): bool
    {
        return parent::install() &&
            $this->registerHook('actionOrderGridDefinitionModifier') &&
            $this->registerHook('actionOrderGridQueryBuilderModifier') &&
            $this->registerHook('actionOrderGridDataModifier') &&
            $this->registerHook('actionOrderFormBuilderModifier') &&
            $this->registerHook('actionAfterCreateOrderFormHandler') &&
            $this->registerHook('actionAfterUpdateOrderFormHandler') &&
            $this->installTables();
    }

    public function uninstall(): bool
    {
//        return parent::uninstall() && $this->uninstallTables();
        return true;
    }

    public function hookActionOrderGridDefinitionModifier(array $params)
    {
        if (empty($params['definition'])) {
            return;
        }

        /** @var GridDefinitionInterface $definition */
        $definition = $params['definition'];

        $translator = $this->getTranslator();

        $definition
            ->getColumns()
            ->addAfter(
                'optin',
                (new ToggleColumn('is_vat_17'))
                    ->setName($translator->trans('Order 17 Vat', [], 'Modules.Order17Vat.Admin'))
                    ->setOptions([
                        'field' => 'is_vat_17',
                        'primary_field' => 'id_order',
                        'route' => 'order17vat_toggle_is_vat_17',
                        'route_param_name' => 'orderId',
                    ])
            );

        $definition->getFilters()->add(
            (new Filter('is_vat_17', YesAndNoChoiceType::class))
                ->setAssociatedColumn('is_vat_17')
        );
    }

    public function hookActionOrderGridQueryBuilderModifier(array $params)
    {
        /** @var QueryBuilder $searchQueryBuilder */
        $searchQueryBuilder = $params['search_query_builder'];

        /** @var OrderFilters $searchCriteria */
        $searchCriteria = $params['search_criteria'];

        $searchQueryBuilder->addSelect(
            'IF(cuva.`is_vat_17` IS NULL,0,cuva.`is_vat_17`) AS `is_vat_17`'
        );

        $searchQueryBuilder->leftJoin(
            'c',
            '`' . pSQL(_DB_PREFIX_) . 'order17vat`',
            'cuva',
            'cuva.`id_order` = c.`id_order`'
        );

        if ('is_vat_17' === $searchCriteria->getOrderBy()) {
            $searchQueryBuilder->orderBy('cuva.`is_vat_17`', $searchCriteria->getOrderWay());
        }

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if ('is_vat_17' === $filterName) {
                $searchQueryBuilder->andWhere('cuva.`is_vat_17` = :is_vat_17');
                $searchQueryBuilder->setParameter('is_vat_17', $filterValue);

                if (!$filterValue) {
                    $searchQueryBuilder->orWhere('cuva.`is_vat_17` IS NULL');
                }
            }
        }
    }

    /**
     * Hook allows to modify Order grid data since 1.7.7.0
     *
     * @param array $params
     */
    public function hookActionOrderGridDataModifier(array $params) {
        if (empty($params['data'])) {
            return;
        }

        $result = false;
        if ($params['id'] !== null) {
            $result = $this->get('order17vat.repository.vat')->getVat17Status((int) $params['id']);
        }

        /** @var GridData $gridData */
        $gridData = $params['data'];
        $modifiedRecords = $gridData->getRecords()->all();

        foreach ($modifiedRecords as $key=> $data) {
            $modifiedRecords[$key]['is_vat_17'] = $result;
        }

        $params['data'] = new GridData(
            new PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection($modifiedRecords),
            $gridData->getRecordsTotal(),
            $gridData->getQuery()
        );
    }

    /**
     * @param array $params
     * @throws Exception
     */
    public function hookActionOrderFormBuilderModifier(array $params)
    {
        /** @var FormBuilderInterface $formBuilder */
        $formBuilder = $params['form_builder'];
        $formBuilder->add(
            'is_vat_17',
            SwitchType::class, [
                'label' => $this->getTranslator()->trans('VAT 17%', [], 'Modules.Order17Vat.Admin'),
                'required' => false,
        ]);

        $result = false;
        if ($params['id'] !== null) {
            $result = $this->get('order17vat.repository.vat')->getVat17Status((int) $params['id']);
        }

        $params['data']['is_vat_17'] = $result;

        $formBuilder->setData($params['data']);
    }

    /**
     * Hook allows to modify Orders form and add additional form fields as well as modify or add new data to the forms.
     *
     * @param array $params
     *
     * @throws OrderException
     */
    public function hookActionAfterCreateOrderFormHandler(array $params) {
        $this->updateOrderVat17Status($params);
    }

    /**
     * Hook allows to modify Orders form and add additional form fields as well as modify or add new data to the forms.
     *
     * @param array $params
     *
     * @throws OrderException
     */
    public function hookActionAfterUpdateOrderFormHandler(array $params)
    {
        $this->updateOrderVat17Status($params);
    }

    /**
     * @param array $params
     *
     * @throws ModuleErrorException
     * @throws CannotCreateVatException|CannotToggleVat17StatusException
     * @throws Exception
     */
    private function updateOrderVat17Status(array $params)
    {
        $orderId = $params['id'];
        /** @var array $orderFormData */
        $orderFormData = $params['form_data'];
        $isVat17 = (bool) $orderFormData['is_vat_17'];

        $customerId = $this->get('order17vat.repository.vat')->findCustomerIdByOrder($orderId);
        $vat17Id = $this->get('order17vat.repository.vat')->findIdByCust($customerId);

        $vat17 = new Vat($vat17Id);
        if (0 >= $vat17->id) {
            $vat17 = $this->createVat($customerId);
        }
        $vat17->is_vat_17 = $isVat17;

        try {
            if (false === $vat17->update()) {
                throw new CannotToggleVat17StatusException(
                    sprintf('Failed to change status for vat17 with id "%s"', $vat17->id)
                );
            }
        } catch (PrestaShopException $exception) {
            throw new CannotToggleVat17StatusException(
                'An unexpected error occurred when updating vat17 status'
            );
        }
    }

    /**
     * Creates a reviewer.
     *
     * @param int $customerId
     *
     * @return Vat
     *
     * @throws CannotCreateVatException
     */
    protected function createVat(int $customerId): Vat
    {
        try {
            $vat17 = new Vat();
            $vat17->id_order = $customerId;
            $vat17->is_vat_17 = 0;

            if (false === $vat17->save()) {
                throw new CannotCreateVatException(
                    sprintf(
                        'An error occurred when creating vat17 with order id "%s"',
                        $customerId
                    )
                );
            }
        } catch (PrestaShopException $exception) {
            throw new CannotCreateVatException(
                sprintf(
                    'An unexpected error occurred when creating vat17 with order id "%s"',
                    $customerId
                ),
                0,
                $exception
            );
        }

        return $vat17;
    }
}