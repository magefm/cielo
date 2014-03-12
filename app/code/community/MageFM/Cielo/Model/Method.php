<?php

class MageFM_Cielo_Model_Method extends Mage_Payment_Model_Method_Cc
{

    protected $_code = 'magefm_cielo';
    protected $_formBlockType = 'magefm_cielo/form';
    protected $_infoBlockType = 'magefm_cielo/info';
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canSaveCc = true;
    protected $apiModel;

    public function authorize(Varien_Object $payment, $amount)
    {
        $cc = array(
            'number' => $this->getInfoInstance()->getCcNumber(),
            'type' => $this->getInfoInstance()->getCcType(),
            'expiration' => sprintf('%4d%02d', $this->getInfoInstance()->getCcExpYear(), $this->getInfoInstance()->getCcExpMonth()),
            'name' => $this->getInfoInstance()->getCcOwner(),
            'cid' => $this->getInfoInstance()->getCcCid()
        );

        $order = array(
            'id' => $this->getInfoInstance()->getOrder()->getIncrementId(),
            'amount' => (string) ($this->getInfoInstance()->getOrder()->getGrandTotal() * 100),
            'datetime' => '2014-03-12T14:30:01',
                // 'datetime' => $this->getInfoInstance()->getOrder()->getCreatedAt(),
        );

        try {
            $response = $this->getApiModel()->authorize($cc, $order);

            $transaction = Mage::getModel('sales/order_payment_transaction');
            $transaction->setOrderPaymentObject($payment);
            $transaction->setTxnId((string) $response->tid);
            $transaction->setTxnType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);
            $transaction->setAdditionalInformation('response', json_encode($response));
            $transaction->setIsClosed(false);
            $transaction->save();
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }

        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        $authorizationTransaction = $payment->getAuthorizationTransaction();

        if (!$authorizationTransaction) {
            $this->authorize($payment, $amount);
            $authorizationTransaction = $payment->getAuthorizationTransaction();
        }

        $tid = $authorizationTransaction->getTxnId();

        try {
            $response = $this->getApiModel()->capture($tid, (string) ($amount * 100));

            $transaction = Mage::getModel('sales/order_payment_transaction');
            $transaction->setOrderPaymentObject($payment);
            $transaction->setTxnId($authorizationTransaction->getTxnId() . '-capture');
            $transaction->setTxnType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
            $transaction->setAdditionalInformation('response', json_encode($response));
            $transaction->setIsClosed(true);
            $transaction->setParentId($authorizationTransaction->getId());
            $transaction->setParentTxnId($authorizationTransaction->getTxnId());
            $transaction->save();
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }

        return $this;
    }

    public function void(Varien_Object $payment)
    {
        $authorizationTransaction = $payment->getAuthorizationTransaction();
        $amount = (float) $payment->getAmountAuthorized();
        $tid = $authorizationTransaction->getTxnId();

        try {
            $response = $this->getApiModel()->void($tid);

            $payment->setAmountCanceled($amount);

            $transaction = Mage::getModel('sales/order_payment_transaction');
            $transaction->setOrderPaymentObject($payment);
            $transaction->setTxnId($payment->getTransactionId());
            $transaction->setTxnType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID);
            $transaction->setAdditionalInformation('response', json_encode($response));
            $transaction->setIsClosed(true);
            $transaction->setParentId($authorizationTransaction->getId());
            $transaction->setParentTxnId($authorizationTransaction->getTxnId());
            $transaction->save();
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }

        return $this;
    }

    public function cancel(Varien_Object $payment)
    {
        $this->void($payment);
        return $this;
    }

    public function validate()
    {
        // abstract validation
        $info = $this->getInfoInstance();

        if ($info instanceof Mage_Sales_Model_Order_Payment) {
            $billingCountry = $info->getOrder()->getBillingAddress()->getCountryId();
        } else {
            $billingCountry = $info->getQuote()->getBillingAddress()->getCountryId();
        }

        if (!$this->canUseForCountry($billingCountry)) {
            Mage::throwException(Mage::helper('payment')->__('Selected payment type is not allowed for billing country.'));
        }

        $availableTypes = explode(',', $this->getConfigData('cctypes'));

        // validate cc type
        if (!in_array($info->getCcType(), $availableTypes)) {
            Mage::throwException(Mage::helper('payment')->__('Credit card type is not allowed for this payment method.'));
        }

        $ccNumber = $info->getCcNumber();
        $ccNumber = preg_replace('/[\-\s]+/', '', $ccNumber);
        $info->setCcNumber($ccNumber);

        // validate cc num
        switch ($info->getCcType()) {
            case 'visa':
                if (!preg_match('/^4[0-9]{12}([0-9]{3})?$/', $ccNumber)) {
                    Mage::throwException(Mage::helper('payment')->__('Invalid Credit Card Number'));
                }
                break;
            case 'mastercard':
                if (!preg_match('/^5[1-5][0-9]{14}$/', $ccNumber)) {
                    Mage::throwException(Mage::helper('payment')->__('Invalid Credit Card Number'));
                }
                break;
            case 'amex':
                if (!preg_match('/^3[47][0-9]{13}$/', $ccNumber)) {
                    Mage::throwException(Mage::helper('payment')->__('Invalid Credit Card Number'));
                }
                break;
            default:
                Mage::throwException('Credit card number validation not implemented.');
        }

        // validate cc cid
        switch ($info->getCcType()) {
            case 'amex':
                if (!preg_match('/^[0-9]{4}$/', $info->getCcCid())) {
                    Mage::throwException(Mage::helper('payment')->__('Invalid credit card verification number.'));
                }
                break;
            default:
                if (!preg_match('/^[0-9]{3}$/', $info->getCcCid())) {
                    Mage::throwException(Mage::helper('payment')->__('Invalid credit card verification number.'));
                }
        }

        // validate expiration
        if (!$this->_validateExpDate($info->getCcExpYear(), $info->getCcExpMonth())) {
            Mage::throwException(Mage::helper('payment')->__('Incorrect credit card expiration date.'));
        }

        return $this;
    }

    protected function getApiModel()
    {
        if (is_null($this->apiModel)) {
            $this->apiModel = Mage::getModel('magefm_cielo/api');
            $this->apiModel->setAffiliation($this->getConfigData('affiliation'));
            $this->apiModel->setKey($this->getConfigData('key'));
            $this->apiModel->setSoftdescriptor($this->getConfigData('softdescriptor'));
            $this->apiModel->setEndpoint('https://qasecommerce.cielo.com.br/servicos/ecommwsec.do');
        }

        return $this->apiModel;
    }

}
