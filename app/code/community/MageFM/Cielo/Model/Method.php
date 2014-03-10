<?php

class MageFM_Cielo_Model_Method extends Mage_Payment_Model_Method_Cc
{

    protected $_code = 'magefm_cielo';
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;

    public function authorize(Varien_Object $payment, $amount)
    {
        die(__METHOD__);
    }

    public function capture(Varien_Object $payment, $amount)
    {
        die(__METHOD__);
    }

    public function void(Varien_Object $payment)
    {
        die(__METHOD__);
    }

    public function cancel(Varien_Object $payment)
    {
        die(__METHOD__);
    }

}
