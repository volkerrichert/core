<?php

/**
 * Isotope eCommerce for Contao Open Source CMS
 *
 * Copyright (C) 2009-2012 Isotope eCommerce Workgroup
 *
 * @package    Isotope
 * @link       http://www.isotopeecommerce.com
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 */

namespace Isotope\Model\Payment;

use Isotope\Isotope;
use Isotope\Interfaces\IsotopePayment;
use Isotope\Interfaces\IsotopePostsale;
use Isotope\Model\Payment;
use Isotope\Model\ProductCollection\Order;


/**
 * Class PaymentPostfinance
 *
 * Handle Postfinance (swiss post) payments
 * @copyright  Isotope eCommerce Workgroup 2009-2012
 * @author     Andreas Schempp <andreas.schempp@terminal42.ch>
 * @author     Fred Bliss <fred.bliss@intelligentspark.com>
 */
class Postfinance extends Payment implements IsotopePayment, IsotopePostsale
{
    /**
     * SHA1-Out relevant fields
     * @var array
     */
    private static $arrShaOut = array
    (
        'AAVADDRESS',
        'AAVCHECK',
        'AAVZIP',
        'ACCEPTANCE',
        'ALIAS',
        'AMOUNT',
        'BIN',
        'BRAND',
        'CARDNO',
        'CCCTY',
        'CN',
        'COMPLUS',
        'CREATION_STATUS',
        'CURRENCY',
        'CVCCHECK',
        'DCC_COMMPERCENTAGE',
        'DCC_CONVAMOUNT',
        'DCC_CONVCCY',
        'DCC_EXCHRATE',
        'DCC_EXCHRATESOURCE',
        'DCC_EXCHRATETS',
        'DCC_INDICATOR',
        'DCC_MARGINPERCENTAGE',
        'DCC_VALIDHOURS',
        'DIGESTCARDNO',
        'ECI',
        'ED',
        'ENCCARDNO',
        'FXAMOUNT',
        'FXCURRENCY',
        'IP',
        'IPCTY',
        'NBREMAILUSAGE',
        'NBRIPUSAGE',
        'NBRIPUSAGE_ALLTX',
        'NBRUSAGE',
        'NCERROR',
        'NCERRORCARDNO',
        'NCERRORCN',
        'NCERRORCVC',
        'NCERRORED',
        'ORDERID',
        'PAYID',
        'PM',
        'SCO_CATEGORY',
        'SCORING',
        'STATUS',
        'SUBBRAND',
        'SUBSCRIPTION_ID',
        'TRXDATE',
        'VC'
    );

    /**
     * Process payment on confirmation page.
     *
     * @access public
     * @return mixed
     */
    public function processPayment()
    {
        if (\Input::get('NCERROR') > 0) {
            \System::log('Order ID "' . \Input::get('orderID') . '" has NCERROR ' . \Input::get('NCERROR'), __METHOD__, TL_ERROR);

            return false;
        }

        if (($objOrder = Order::findByPk(\Input::get('orderID'))) === null) {
            \System::log('Order ID "' . \Input::get('orderID') . '" not found', __METHOD__, TL_ERROR);

            return false;
        }

        $this->postfinance_method = 'GET';

        if (!$this->validateSHASign()) {
            \System::log('Received invalid postsale data for order ID "' . $objOrder->id . '"', __METHOD__, TL_ERROR);

            return false;
        }

        // Validate payment data (see #2221)
        if ($objOrder->currency != $this->getRequestData('currency') || $objOrder->getTotal() != $this->getRequestData('amount')) {
            \System::log('Postsale checkout manipulation in payment for Order ID ' . $objOrder->id . '!', __METHOD__, TL_ERROR);
            \Isotope\Module\Checkout::redirectToStep('failed');
        }

        $objOrder->date_paid = time();
        $objOrder->updateOrderStatus($this->new_order_status);

        $objOrder->save();

        return true;
    }


    /**
     * Process post-sale requestion from the Postfinance payment server.
     * @param   IsotopeProductCollection
     */
    public function processPostsale(IsotopeProductCollection $objOrder)
    {
        if ($this->getRequestData('NCERROR') > 0) {
            \System::log('Order ID "' . $this->getRequestData('orderID') . '" has NCERROR ' . $this->getRequestData('NCERROR'), __METHOD__, TL_ERROR);

            return;
        }

        if (!$this->validateSHASign()) {
            \System::log('Received invalid postsale data for order ID "' . $objOrder->id . '"', __METHOD__, TL_ERROR);

            return;
        }

        // Validate payment data (see #2221)
        if ($objOrder->currency != $this->getRequestData('currency') || $objOrder->getTotal() != $this->getRequestData('amount')) {
            \System::log('Postsale checkout manipulation in payment for Order ID ' . $objOrder->id . '!', __METHOD__, TL_ERROR);

            return;
        }

        if (!$objOrder->checkout()) {
            \System::log('Post-Sale checkout for Order ID "' . $objOrder->id . '" failed', __METHOD__, TL_ERROR);

            return;
        }

        $objOrder->date_paid = time();
        $objOrder->save();
    }


    /**
     * {@inheritdoc}
     */
    public function getPostsaleOrder()
    {
        return Order::findByPk($this->getRequestData('orderID'));
    }


    /**
     * Return the payment form.
     *
     * @access public
     * @return string
     */
    public function checkoutForm()
    {
        if (($objOrder = Order::findOneBy('source_collection_id', Isotope::getCart()->id)) === null) {
            \Isotope\Module\Checkout::redirectToStep('failed');
        }

        $objAddress = Isotope::getCart()->getBillingAddress();

        $arrParams = array();
        $arrParams = array_merge($arrParams, $this->preparePSPParams($objOrder, $objAddress));

        // SHA-1 must be generated on alphabetically sorted keys.
        ksort($arrParams);

        $strSHASign = '';
        foreach($arrParams as $k => $v) {
            if ($v == '')
                continue;

            $strSHASign .= $k . '=' . htmlspecialchars_decode($v) . $this->postfinance_sha1_in;
        }

        $arrParams['SHASIGN'] = sha1($strSHASign);

        $objTemplate = new \Isotope\Template('iso_payment_postfinance');

        $objTemplate->action = 'https://e-payment.postfinance.ch/ncol/' . ($this->debug ? 'test' : 'prod') . '/orderstandard_utf8.asp';
        $objTemplate->params = $arrParams;
        $objTemplate->headline = $GLOBALS['TL_LANG']['MSC']['pay_with_redirect'][0];
        $objTemplate->message = $GLOBALS['TL_LANG']['MSC']['pay_with_redirect'][1];
        $objTemplate->slabel = $GLOBALS['TL_LANG']['MSC']['pay_with_redirect'][2];
        $objTemplate->id = $this->id;

        return $objTemplate->parse();
    }

    /**
     * Prepare regular PSP params
     * @param   Order
     * @param   Address
     * @return  array
     */
    private function preparePSPParams($objOrder, $objAddress)
    {
        $strFailedUrl = \Environment::get('base') . \Isotope\Module\Checkout::generateUrlForStep('failed');

        return array
        (
            'PSPID'         => $this->postfinance_pspid,
            'ORDERID'       => $objOrder->id,
            'AMOUNT'        => round((Isotope::getCart()->getTotal() * 100)),
            'CURRENCY'      => Isotope::getConfig()->currency,
            'LANGUAGE'      => $GLOBALS['TL_LANGUAGE'] . '_' . strtoupper($GLOBALS['TL_LANGUAGE']),
            'CN'            => $objAddress->firstname . ' ' . $objAddress->lastname,
            'EMAIL'         => $objAddress->email,
            'OWNERZIP'      => $objAddress->postal,
            'OWNERADDRESS'  => $objAddress->street_1,
            'OWNERADDRESS2' => $objAddress->street_2,
            'OWNERCTY'      => $objAddress->country,
            'OWNERTOWN'     => $objAddress->city,
            'OWNERTELNO'    => $objAddress->phone,
            'ACCEPTURL'     => \Environment::get('base') . \Isotope\Frontend::addQueryStringToUrl('uid=' . $objOrder->uniqid, \Isotope\Module\Checkout::generateUrlForStep('complete')),
            'DECLINEURL'    => $strFailedUrl,
            'EXCEPTIONURL'  => $strFailedUrl,
            'PARAMPLUS'     => 'mod=pay&amp;id=' . $this->id,
        );
    }


    /**
     * Gets the request data based on the chosen HTTP method
     * @param   string Key
     * @return  mixed
     */
    private function getRequestData($strKey)
    {
        if ($this->postfinance_method == 'GET') {
            return \Input::get($strKey);
        }

        return \Input::post($strKey);
    }


    /**
     * Validate SHA-OUT signature
     */
    private function validateSHASign()
    {
        $strSHASign = '';
        $arrParams = array();

        foreach (array_keys(($this->postfinance_method == 'GET' ? $_GET : $_POST)) as $key) {
            if (in_array(strtoupper($key), self::$arrShaOut)) {
                $arrParams[$key] = $this->getRequestData($key);
            }
        }

        uksort($arrParams, 'strcasecmp');

        foreach($arrParams as $k => $v ) {

            if ($v == '')
                continue;

            $strSHASign .= strtoupper($k) . '=' . $v . $this->postfinance_sha1_out;
        }

        if ($this->getRequestData('SHASIGN') == strtoupper(sha1($strSHASign))) {
            return true;
        }

        return false;
    }
}
