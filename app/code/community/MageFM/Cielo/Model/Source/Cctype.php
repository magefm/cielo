<?php

class MageFM_Cielo_Model_Source_Cctype
{

    public function toOptionArray()
    {
        return array(
            array('value' => 'visa', 'label' => 'Visa'),
            array('value' => 'mastercard', 'label' => 'MasterCard'),
            array('value' => 'amex', 'label' => 'American Express'),
            array('value' => 'diners', 'label' => 'Diners'),
            array('value' => 'discover', 'label' => 'Discover'),
            array('value' => 'elo', 'label' => 'Elo'),
            array('value' => 'jcb', 'label' => 'JCB'),
            array('value' => 'aura', 'label' => 'Aura')
        );
    }

    public function toKeyValueArray()
    {
        $items = array();

        foreach ($this->toOptionArray() as $item) {
            $items[$item['value']] = $item['label'];
        }

        return $items;
    }

}
