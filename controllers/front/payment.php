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
 * Class ChequePaymentModuleFrontController
 */
class ChequePaymentModuleFrontController extends ModuleFrontController
{
    // @codingStandardsIgnoreStart
    /** @var bool $ssl */
    public $ssl = true;
    /** @var bool $display_column_left */
    public $display_column_left = false;
    /** @var Cheque $module */
    public $module;
    // @codingStandardsIgnoreEnd

    /**
     * @see FrontController::initContent()
     * @throws PrestaShopException
     * @throws Exception
     */
    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;
        if (!$this->module->checkCurrency($cart)) {
            Tools::redirect('index.php?controller=order');
        }

        $this->context->smarty->assign([
            'nbProducts'       => $cart->nbProducts(),
            'cust_currency'    => $cart->id_currency,
            'currencies'       => $this->module->getCurrency((int) $cart->id_currency),
            'total'            => $cart->getOrderTotal(true, Cart::BOTH),
            'isoCode'          => $this->context->language->iso_code,
            'chequeName'       => $this->module->chequeName,
            'chequeAddress'    => Tools::nl2br($this->module->address),
            'this_path'        => $this->module->getPathUri(),
            'this_path_cheque' => $this->module->getPathUri(),
            'this_path_ssl'    => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/',
        ]);

        $this->setTemplate('payment_execution.tpl');
    }
}
