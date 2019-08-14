<?php

/**
 *
 * TPV Ceca
 */
class Magentodesarrollo_Ceca_Model_Paymentmethod extends Mage_Payment_Model_Method_Abstract
{

	/**
    * unique internal payment method identifier
    *
    * @var string [a-z0-9_]
    */
    protected $_code = 'ceca';
	
	/**
     * Is this payment method a gateway (online auth/charge) ?
     */
    protected $_isGateway               = true;
 
    /**
     * Can authorize online?
     */
    protected $_canAuthorize            = true;
 
    /**
     * Can capture funds online?
     */
    protected $_canCapture              = true;
 
    /**
     * Can capture partial amounts online?
     */
    protected $_canCapturePartial       = false;
 
    /**
     * Can refund online?
     */
    protected $_canRefund               = true;//no funciona bien en true 
	protected $_canRefundInvoicePartial = false;
    /**
     * Can void transactions online?
     */
    protected $_canVoid                 = true;
 
    /**
     * Can use this payment method in administration panel?
     */
    protected $_canUseInternal          = true;
 
    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout          = true;
 
    /**
     * Is this payment method suitable for multi-shipping checkout?
     */
    protected $_canUseForMultishipping  = true;
 
    /**
     * Can save credit card information for future processing?
     */
    protected $_canSaveCc = false;

	protected $_isInitializeNeeded      = false;

	 public function authorize(Varien_Object $payment, $amount)
    {
        //Mage::log('Authorizing!');
    }

    public function capture(Varien_Object $payment, $amount)
    {
        //Mage::log('** Capturing **');
    }

    public function assignData($data)
    {
        //Mage::log('Assigning Data');
    }
	
	public function refund(Varien_Object $payment, $amount)
	{
		//preparacion del array
		$orderId=$payment->getOrder()->getIncrementId();
		$invoice = $payment->getOrder()->getInvoiceCollection()
				->addAttributeToSort('created_at', 'DSC')
				->setPage(1, 1)
				->getFirstItem();
		
		$transId=$invoice->getTransactionId();
		$url = (Mage::getStoreConfig('payment/ceca/produccion')?'https://pgw.ceca.es/cgi-bin/tpvanular':'http://tpv.ceca.es:8000/cgi-bin/tpvanular');
		$clave=Mage::getStoreConfig('payment/ceca/clave_encriptacion');
		$merchant=Mage::getStoreConfig('payment/ceca/merchant_id');
		$acquirer=Mage::getStoreConfig('payment/ceca/acquirer_bin');
		$terminal=Mage::getStoreConfig('payment/ceca/terminal_id');
		$precio=intval(100*$amount);
		Mage::log($amount);
		$mensaje = $clave . $merchant . $acquirer . $terminal . $orderId . $precio."9782".$transId."SHA1";
		$firma =sha1($mensaje);

		$fields = array(
				"MerchantID" => $merchant,
				"AcquirerBIN" =>$acquirer,
				"TerminalID" => $terminal,
				"Firma" => $firma,
				"Num_operacion" => $orderId,
				"Importe" => $precio,
				"TipoMoneda" => "978",
				"Exponente" => "2",
				"Referencia" => $transId,
				"Cifrado" => "SHA1"
				);
		$fields_string="";
		foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
		rtrim($fields_string, '&');
		//open connection
		$ch = curl_init();
		//set the url, number of POST vars, POST data
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_POST, count($fields));
		curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		//execute post
		$result = curl_exec($ch);
		Mage::log($result,null,'serversidevalidation.log');
		$resok = preg_replace("/<.*?>/", "", $result);
		$resok = preg_match("/400/", $resok);
		if(!$resok){
			$resko = preg_replace("/[\n|\r]/", "", $result);
			$resko = preg_replace("/<SCRIPT.*<\/SCRIPT>/", "", $resko);
			$resko = preg_match("/<BODY.*<\/BODY>/", $resko, $res);
			Mage::throwException($res[0]);
		}
		$curlinfo = curl_getinfo($ch);
		$curlinfo =$curlinfo['http_code'];
		//close connection
		curl_close($ch);
		//comprobación de conexión correcta
		if($curlinfo!=200){
			Mage::throwException("Fallo de conexión");
		}
	
		return $this;
	}
	
	//donde va cuando pulsas place order
	public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('ceca/payment/redirect');
    }
	/*public function getCheckoutRedirectUrl()
    {
        return Mage::getUrl('checkout/onepage/success');
    }//esto se lanza antes del checkoutrevision*/
	
	
	public function getCheckoutFormFields($order){
	$clave=Mage::getStoreConfig('payment/ceca/clave_encriptacion');
	$merchant=Mage::getStoreConfig('payment/ceca/merchant_id');
	$acquirer=Mage::getStoreConfig('payment/ceca/acquirer_bin');
	$terminal=Mage::getStoreConfig('payment/ceca/terminal_id');
	$urlOk=Mage::getUrl('checkout/onepage/success');
	$urlKo=Mage::getUrl('ceca/payment/fail');//Mage::getUrl('checkout/onepage/failure');
	$precio=intval(100*$order->getGrandTotal());
	$orderId=$order->getIncrementId();
	
	$mensaje = $clave . $merchant . $acquirer . $terminal . $orderId . $precio."9782SHA1".$urlOk.$urlKo;
	$firma =sha1($mensaje);
	
	$Arr = array(
            "MerchantID" => $merchant,
			"AcquirerBIN" =>$acquirer,
			"TerminalID" => $terminal,
			"URL_OK" => $urlOk,
			"URL_NOK" => $urlKo,
			"Firma" => $firma,
			"Cifrado" => "SHA1",
			"Num_operacion" => $orderId,
			"Importe" => $precio,
			"TipoMoneda" => "978",
			"Exponente" => "2",
			"Pago_soportado" => "SSL",
			"Idioma" => "1"
			);
	return $Arr;
	}

}