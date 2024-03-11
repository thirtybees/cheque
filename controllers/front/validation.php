<?php
/**
 * 2007-2016 PrestaShop
 *
 * Thirty Bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017-2018 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    Thirty Bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017-2018 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

if (!defined('_TB_VERSION_')) {
    return;
}

/**
 * Class ChequeValidationModuleFrontController
 */
class ChequeValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @var Cheque $module
     */
    public $module;

    /**
     * @return void
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function postProcess()
    {
        $cart = $this->context->cart;

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        try {
            foreach (Module::getPaymentModules() as $module) {
                if ($module['name'] == 'cheque') {
                    $authorized = true;
                    break;
                }
            }
        } catch (PrestaShopException $e) {
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = $this->context->currency;
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);

        try {
            $mailVars = [
                '{cheque_name}'         => Configuration::get('CHEQUE_NAME'),
                '{cheque_address}'      => Configuration::get('CHEQUE_ADDRESS'),
                '{cheque_address_html}' => str_replace("\n", '<br />', Configuration::get('CHEQUE_ADDRESS')),
            ];
        } catch (PrestaShopException $e) {
            $mailVars = [
                '{cheque_name}'         => '',
                '{cheque_address}'      => '',
                '{cheque_address_html}' => '',
            ];
        }

        try {
            $this->module->validateOrder((int) $cart->id, Configuration::get('PS_OS_CHEQUE'), $total, $this->module->displayName, null, $mailVars, (int) $currency->id, false, $customer->secure_key);
        } catch (PrestaShopException $e) {
            Logger::addLog("Cheque module error during order validation: {$e->getMessage()}");
        }
        Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int) $cart->id.'&id_module='.(int) $this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
    }
}
