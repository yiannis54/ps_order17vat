<?php
/**
 * 2007-2020 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0).
 * It is also available through the world-wide-web at this URL: https://opensource.org/licenses/AFL-3.0
 */

namespace PrestaShop\Module\Order17Vat\Controller\Admin;

use PrestaShop\Module\Order17Vat\Exception\CannotCreateVatException;
use PrestaShop\Module\Order17Vat\Exception\CannotToggleVat17StatusException;
use PrestaShop\Module\Order17Vat\Exception\VatException;
use PrestaShop\Module\Order17Vat\Entity\Vat;
use Prestashop\Prestashop\Core\Domain\Order\Exception\OrderException;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\RedirectResponse;

class Order17VatController extends FrameworkBundleAdminController
{
    /**
     * Catches the toggle action of order vat.
     *
     * @param int $orderId
     *
     * @return RedirectResponse
     */
    public function toggleIsOrder17VatAction(int $orderId): RedirectResponse
    {
        try {
            $vatId = $this->get('order17vat.repository.vat')->findIdByOrder($orderId);

            $vat17 = new Vat((int) $vatId);
            if ($vat17->id >= 0) {
                $vat17 = $this->createVat17IfNeeded($orderId);
            }
            $vat17->is_vat_17 = (bool) !$vat17->is_vat_17;

            try {
                if (false === $vat17->update()) {
                    throw new CannotToggleVat17StatusException(
                        sprintf('Failed to change status for vat with id "%s"', $vat17->id)
                    );
                }
            } catch (\PrestaShopException $exception) {
                throw new CannotToggleVat17StatusException(
                    'An unexpected error occurred when updating vat status'
                );
            }

            $this->addFlash('success', $this->trans('Successful update.', 'Admin.Notifications.Success'));
        } catch (VatException $e) {
            $this->addFlash('error', $this->getErrorMessageForException($e, $this->getErrorMessageMapping()));
        }

        return $this->redirectToRoute('admin_orders_index');
    }

    /**
     * Gets error message mappings which are later used to display friendly user error message instead of the
     * exception message.
     *
     * @return array
     */
    private function getErrorMessageMapping(): array
    {
        return [
            OrderException::class => $this->trans(
                'Something bad happened when trying to get order id',
                'Modules.Order17Vat.Order17VatController'
            ),
            CannotCreateVatException::class => $this->trans(
                'Failed to create 17 vat',
                'Modules.Order17Vat.Order17VatController'
            ),
            CannotToggleVat17StatusException::class => $this->trans(
                'An error occurred while updating the status.',
                'Modules.Order17Vat.Order17VatController'
            ),
        ];
    }

    /**
     * Creates a vat record. Used when toggle action is used on order which data is empty.
     *
     * @param int $orderId
     *
     * @return Vat
     *
     * @throws CannotCreateVatException
     */
    protected function createVat17IfNeeded(int $orderId): Vat
    {
        try {
            $vat17 = new Vat();
            $vat17->id_order = $orderId;
            $vat17->is_vat_17 = 0;

            if (false === $vat17->save()) {
                throw new CannotCreateVatException(
                    sprintf(
                        'An error occurred when creating vat17 for order id "%s"',
                        $orderId
                    )
                );
            }
        } catch (\PrestaShopException $exception) {
            throw new CannotCreateVatException(
                sprintf(
                    'An unexpected error occurred when creating vat17 for order id "%s"',
                    $orderId
                ),
                0,
                $exception
            );
        }

        return $vat17;
    }
}
