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
 * Class Cheque
 */
class Cheque extends PaymentModule
{
    const CHEQUE_NAME = 'CHEQUE_NAME';
    const CHEQUE_ADDRESS = 'CHEQUE_ADDRESS';
    const ORDER_STATUS = 'PS_OS_CHEQUE';

    /**
     * @var string $chequeName
     */
    public $chequeName;

    /**
     * @var string $address
     */
    public $address;

    /**
     * @var array $extraMailVars
     */
    public $extraMailVars;

    /**
     * Cheque constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'cheque';
        $this->tab = 'payments_gateways';
        $this->version = '3.1.0';
        $this->author = 'thirty bees';
        $this->controllers = ['payment', 'validation'];
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $config = Configuration::getMultiple([static::CHEQUE_NAME, static::CHEQUE_ADDRESS]);
        if (isset($config['CHEQUE_NAME'])) {
            $this->chequeName = $config['CHEQUE_NAME'];
        }
        if (isset($config['CHEQUE_ADDRESS'])) {
            $this->address = $config['CHEQUE_ADDRESS'];
        }

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Payments by cheque');
        $this->description = $this->l('This module allows you to accept payments by cheque.');
        $this->confirmUninstall = $this->l('Are you sure you want to delete these details?');

        if ((empty($this->chequeName) || empty($this->address))) {
            $this->warning = $this->l('The "Pay to the order of" and "Address" fields must be configured before using this module.');
        }
        $paymentCurrencies = Currency::checkPaymentCurrencies($this->id);
        if (!is_array($paymentCurrencies) || empty($paymentCurrencies)) {
            $this->warning = $this->l('No currency has been set for this module.');
        }

        $this->extraMailVars = [
            '{cheque_name}'         => Configuration::get(static::CHEQUE_NAME),
            '{cheque_address}'      => Configuration::get(static::CHEQUE_ADDRESS),
            '{cheque_address_html}' => str_replace("\n", '<br />', Configuration::get(static::CHEQUE_ADDRESS)),
        ];
    }

    /**
     * Install this module
     *
     * @return bool
     * @throws PrestaShopException
     */
    public function install()
    {
        return (
            parent::install() &&
            $this->registerHook('payment') &&
            $this->registerHook('displayPaymentEU') &&
            $this->registerHook('paymentReturn')
        );
    }

    /**
     * Uninstall this module
     *
     * @return bool
     * @throws PrestaShopException
     */
    public function uninstall()
    {
        if (!Configuration::deleteByName(static::CHEQUE_NAME) || !Configuration::deleteByName(static::CHEQUE_ADDRESS) || !parent::uninstall()) {
            return false;
        }

        return true;
    }

    /**
     * Module config page
     *
     * @return string
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function getContent()
    {
        /** @var AdminController $controller */
        $controller = $this->context->controller;

        if (Tools::isSubmit('btnSubmit')) {
            $this->postValidation();
            if (!count($controller->errors)) {
                $this->postProcess($controller);
            }
        }

        return (
            $this->display(__FILE__, 'infos.tpl') .
            $this->renderForm($controller)
        );
    }

    /**
     * @param array $params
     *
     * @return string
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookPayment($params)
    {
        if (!$this->active) {
            return '';
        }
        if (!$this->checkCurrency($params['cart'])) {
            return '';
        }

        $this->smarty->assign([
            'this_path'        => $this->_path,
            'this_path_cheque' => $this->_path,
            'this_path_ssl'    => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/',
        ]);


        return $this->display(__FILE__, 'payment.tpl');
    }

    /**
     * Displayed on advanced EU checkout
     *
     * @param array $params
     *
     * @return array
     *
     * @throws PrestaShopException
     */
    public function hookDisplayPaymentEU($params)
    {
        if (!$this->active) {
            return [];
        }
        if (!$this->checkCurrency($params['cart'])) {
            return [];
        }

        return [
            'cta_text' => $this->l('Pay by cheque'),
            'logo'     => Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/cheque.jpg'),
            'action'   => $this->context->link->getModuleLink($this->name, 'validation', [], true),
        ];
    }

    /**
     * Displayed on order confirmation page
     *
     * @param array $params
     *
     * @return string
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return '';
        }

        $state = $params['objOrder']->getCurrentState();
        if (in_array($state, [$this->getOrderStatusId(), Configuration::get('PS_OS_OUTOFSTOCK'), Configuration::get('PS_OS_OUTOFSTOCK_UNPAID')])) {
            $this->smarty->assign([
                'total_to_pay'  => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
                'chequeName'    => $this->chequeName,
                'chequeAddress' => Tools::nl2br($this->address),
                'status'        => 'ok',
                'id_order'      => $params['objOrder']->id,
            ]);
            if (isset($params['objOrder']->reference) && !empty($params['objOrder']->reference)) {
                $this->smarty->assign('reference', $params['objOrder']->reference);
            }
        } else {
            $this->smarty->assign('status', 'failed');
        }

        return $this->display(__FILE__, 'payment_return.tpl');
    }

    /**
     * Check currency
     *
     * @param Cart $cart
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function checkCurrency($cart)
    {
        $currencyId = (int)$cart->id_currency;
        foreach (Currency::getPaymentCurrencies((int)$this->id) as $currencyModule) {
            if ($currencyId === (int)$currencyModule['id_currency']) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param AdminController $controller
     *
     * @return string
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function renderForm($controller)
    {
        $contactForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Contact details'),
                    'icon'  => 'icon-envelope',
                ],
                'input'  => [
                    [
                        'type'     => 'text',
                        'label'    => $this->l('Pay to the order of (name)'),
                        'name'     => static::CHEQUE_NAME,
                        'required' => true,
                    ],
                    [
                        'type'     => 'textarea',
                        'label'    => $this->l('Address'),
                        'desc'     => $this->l('Address where the cheque should be sent to.'),
                        'name'     => static::CHEQUE_ADDRESS,
                        'required' => true,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $settingsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Order status'),
                    'icon'  => 'icon-envelope',
                ],
                'input'  => [
                    [
                        'type'     => 'select',
                        'label'    => $this->l('Order status'),
                        'name'     => static::ORDER_STATUS,
                        'options'  => [
                            'query'   => OrderState::getOrderStates((int)$this->context->language->id),
                            'id'      => 'id_order_state',
                            'name'    => 'name',
                            'orderby' => 'id_order_state',
                        ],
                        'class'    => 'fixed-width-xxl',
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages'    => $controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        ];

        return $helper->generateForm([
            $settingsForm,
            $contactForm
        ]);
    }

    /**
     * @return array
     * @throws PrestaShopException
     */
    public function getConfigFieldsValues()
    {
        return [
            static::CHEQUE_NAME    => Tools::getValue(static::CHEQUE_NAME, Configuration::get(static::CHEQUE_NAME)),
            static::CHEQUE_ADDRESS => Tools::getValue(static::CHEQUE_ADDRESS, Configuration::get(static::CHEQUE_ADDRESS)),
            static::ORDER_STATUS   => (int)Tools::getValue(static::ORDER_STATUS, $this->getOrderStatusId()),
        ];
    }

    /**
     * Post validation
     */
    protected function postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue(static::CHEQUE_NAME)) {
                $this->context->controller->errors[] = $this->l('The "Pay to the order of" field is required.');
            } elseif (!Tools::getValue(static::CHEQUE_ADDRESS)) {
                $this->context->controller->errors[] = $this->l('The "Address" field is required.');
            }
        }
    }

    /**
     *
     * Post process
     *
     * @param AdminController $controller
     *
     * @throws PrestaShopException
     */
    protected function postProcess($controller)
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('CHEQUE_NAME', Tools::getValue('CHEQUE_NAME'));
            Configuration::updateValue('CHEQUE_ADDRESS', Tools::getValue('CHEQUE_ADDRESS'));
        }
        $controller->confirmations[] = $this->l('Settings updated');
    }

    /**
     * @return int
     * @throws PrestaShopException
     */
    public function getOrderStatusId()
    {
        $status = (int)Configuration::get(static::ORDER_STATUS);
        if (! $status) {
            $status = (int)Configuration::get('PS_OS_PAYMENT');
        }
        return $status;
    }
}
