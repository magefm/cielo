<?php

class MageFM_Cielo_Model_Api
{

    protected $affiliation;
    protected $key;
    protected $softdescriptor;
    protected $endpoint;

    public function setAffiliation($affiliation)
    {
        $this->affiliation = $affiliation;
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    public function setSoftdescriptor($softdescriptor)
    {
        $this->softdescriptor = $softdescriptor;
    }

    public function setEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;
    }

    public function authorize($cc, $order)
    {
        $requestXML = $this->createAuthorizeXML($cc, $order);
        $responseXML = $this->sendXML($requestXML);

        $response = simplexml_load_string($responseXML);

        Mage::log("Authorization | Request: {$requestXML}\r\nResponse: {$responseXML}", null, 'magefm-cielo.log', true);

        if (!empty($response->autorizacao->codigo) && (string) $response->autorizacao->codigo === '4') {
            return $response;
        }

        throw new Exception('Authorization failed.');
    }

    public function capture($tid, $amount)
    {
        $requestXML = $this->createCaptureXML($tid, $amount);
        $responseXML = $this->sendXML($requestXML);

        $response = simplexml_load_string($responseXML);

        Mage::log("Capture | Request: {$requestXML}\r\nResponse: {$responseXML}", null, 'magefm-cielo.log', true);

        if (!empty($response->captura->codigo) && (string) $response->captura->codigo === '6') {
            return $response;
        }

        throw new Exception('Capture failed.');
    }

    public function void($tid)
    {
        $requestXML = $this->createVoidXML($tid);
        $responseXML = $this->sendXML($requestXML);

        $response = simplexml_load_string($responseXML);

        Mage::log("Void | Request: {$requestXML}\r\nResponse: {$responseXML}", null, 'magefm-cielo.log', true);

        if (empty($response->cancelamentos)) {
            throw new Exception('Void failed.');
        }


        foreach ($response->cancelamentos as $cancelamento) {
            if (!empty($cancelamento->cancelamento->codigo) && (string) $cancelamento->cancelamento->codigo === '9') {
                return $response;
            }
        }

        throw new Exception('Void failed.');
    }

    protected function createAuthorizeXML($cc, $order)
    {
        $root = simplexml_load_string('<requisicao-transacao id="f094958b-3b68-4c0b-9e68-3137f24fb308" versao="1.3.0"/>');

        $dadosEC = $root->addChild('dados-ec');
        $dadosEC->addChild('numero', $this->affiliation);
        $dadosEC->addChild('chave', $this->key);

        $dadosPortador = $root->addChild('dados-portador');
        $dadosPortador->addChild('numero', $cc['number']);
        $dadosPortador->addChild('validade', $cc['expiration']);
        $dadosPortador->addChild('indicador', '1');
        $dadosPortador->addChild('codigo-seguranca', $cc['cid']);
        $dadosPortador->addChild('nome-portador', $cc['name']);

        $dadosPedido = $root->addChild('dados-pedido');
        $dadosPedido->addChild();
        $dadosPedido->addChild('numero', $order['id']);
        $dadosPedido->addChild('valor', $order['amount']);
        $dadosPedido->addChild('moeda', '986');
        $dadosPedido->addChild('data-hora', $order['datetime']);
        $dadosPedido->addChild('soft-descriptor', $this->softdescriptor);

        $formaPagamento = $root->addChild('forma-pagamento');
        $formaPagamento->addChild('bandeira', $cc['type']);
        $formaPagamento->addChild('produto', '1');
        $formaPagamento->addChild('parcelas', '1');

        $root->addChild('autorizar', '3');
        $root->addChild('capturar', 'false');
        $root->addChild('gerar-token', 'false');

        return $root->asXML();
    }

    protected function createCaptureXML($tid, $amount)
    {
        $root = simplexml_load_string('<requisicao-captura id="adbc9961-8a39-452b-b7fd-15b44b464a97" versao="1.3.0"/>');

        $root->addChild('tid', $tid);

        $dadosEC = $root->addChild('dados-ec');
        $dadosEC->addChild('numero', $this->affiliation);
        $dadosEC->addChild('chave', $this->key);

        $root->addChild('valor', $amount);

        return $root->asXML();
    }

    protected function createVoidXML($tid)
    {
        $root = simplexml_load_string('<requisicao-cancelamento id="adbc9961-8a39-452b-b7fd-15b44b464a97" versao="1.3.0"/>');

        $root->addChild('tid', $tid);

        $dadosEC = $root->addChild('dados-ec');
        $dadosEC->addChild('numero', $this->affiliation);
        $dadosEC->addChild('chave', $this->key);

        return $root->asXML();
    }

    protected function sendXML($xml)
    {
        $client = new Zend_Http_Client($this->endpoint);
        $client->setMethod(Zend_Http_Client::POST);
        $client->setParameterPost('mensagem', $xml);

        $result = $client->request();
        return $result->getBody();
    }

}
