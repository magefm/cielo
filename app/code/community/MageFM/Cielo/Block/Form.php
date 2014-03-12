<?php

class MageFM_Cielo_Block_Form extends Mage_Payment_Block_Form_Cc
{

    protected function _construct()
    {
        $this->setTemplate('magefm/cielo/form.phtml');
    }

    public function getCcAvailableTypes()
    {
        $types = Mage::getModel('magefm_cielo/source_cctype')->toKeyValueArray();

        if ($method = $this->getMethod()) {
            $availableTypes = $method->getConfigData('cctypes');
            if ($availableTypes) {
                $availableTypes = explode(',', $availableTypes);
                foreach ($types as $code => $name) {
                    if (!in_array($code, $availableTypes)) {
                        unset($types[$code]);
                    }
                }
            }
        }

        return $types;
    }

}
