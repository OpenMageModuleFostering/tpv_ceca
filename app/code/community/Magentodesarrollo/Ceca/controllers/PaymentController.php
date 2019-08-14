<?php

/**
 * TPV Ceca
 */
class Magentodesarrollo_Ceca_PaymentController extends Mage_Core_Controller_Front_Action {

	public function redirectAction() {
		
		$ordernumber = Mage::getSingleton('checkout/session')->getLastRealOrderId();
		//si no acabas de comprar este if te saca al carrito
		if(isset($ordernumber))
		{
			$order = Mage::getModel('sales/order')->loadByIncrementId($ordernumber);
			//comprueba si esta pagada
			if (!$this->_isOrderAlreadyPaid($order)) {
			//coge el html del formulario que hara el redirect automaticamente
				echo ($this->pintarhtml($order));
			} else {
				$this->_redirect('checkout/cart');
			}
        } else {$this->_redirect('checkout/cart');}
    }
	/**
	* 
	*/
	protected function _isOrderAlreadyPaid(Mage_Sales_Model_Order $order = null) {
        $order = !isset($order) ? $this->_getOrder($errorMessage) : $order;

        if ($order) {
            $payment = $order->getPayment();
            return $payment->getLastTransId() ? true : false;
        }
        return false;
    }

	/**
	* coger formulario
	*/
	public function pintarhtml($order){
		$cecatpv = Mage::getModel('ceca/paymentmethod');
		$url = (Mage::getStoreConfig('payment/ceca/produccion')?'https://pgw.ceca.es/cgi-bin/tpv':'http://tpv.ceca.es:8000/cgi-bin/tpv');
		$form = new Varien_Data_Form();
        $form->setAction($url)
                ->setId('ceca_standard_checkout')
                ->setName('ceca_standard_checkout')
                ->setMethod('POST')
                ->setUseContainer(true);

        foreach ($cecatpv->getCheckoutFormFields($order) as $field => $value) {
            $form->addField($field, 'hidden', array('name' => $field, 'value' => $value));
        }
		$html = '<html>';
        $html .= '<head>';
        $html .= '</head>';
        $html .= '<body>';
        $html.= '<div class="ceca-container">';
        $html.= '<p class="redirecting-text">' . 'You will be redirected to the ServiRed website in a few seconds.' . '</p>';
        $html.= $form->toHtml();
		$html.='</div>';
		$html.= '<script type="text/javascript">document.getElementById("ceca_standard_checkout").submit();</script>';
        $html.= '</body></html>';
		return $html;
	}
	
	public function responseAction() {
		//Mage::log($_POST);
			// Recogemos datos de respuesta
			$clave     = Mage::getStoreConfig('payment/ceca/clave_encriptacion');
			$total     = $_POST["Importe"];
			$orderId    = $_POST["Num_operacion"];
			$moneda    = $_POST["TipoMoneda"];
			$firma_remota = $_POST["Firma"];
			$exponente = $_POST["Exponente"];
			$transid = $_POST["Referencia"];
			$merchant     = $_POST["MerchantID"];
			$acquirer     = $_POST["AcquirerBIN"];
			$terminal     = $_POST["TerminalID"];
			$numaut = $_POST["Num_aut"];
			//$     = $_POST[""];
			//$     = $_POST[""];
			//$     = $_POST[""];
			//$     = $_POST[""];
			$mensaje = $clave . $merchant . $acquirer . $terminal . $orderId . $total . $moneda . $exponente . $transid;
			$firma =sha1($mensaje);
			
			if(($firma==$firma_remota)&&($numaut==101000)){
				
				//Grava la orden
				$order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
				$payment = $order->getPayment();
				$payment->setTransactionId($transid);
				$payment->Capture(NULL);
				$order->save();
				
				//manda correo
				$invoice = $payment->getCreatedInvoice();
				if ($invoice && Mage::getStoreConfig('payment/ceca/enviafactura')){
					$invoice->sendEmail(true);
				}
				
				
			}//firma no coincide
			else{
				Mage::log("Posible estafa de pago por firma erronea.");
			}
			
			
	}
	
	public function failAction() {
	$orderid = Mage::getSingleton('checkout/session')->getLastOrderId();
	$order = Mage::getModel('sales/order')->load($orderid);
	if($order->getStatus()=='pending'){
		$state = 'new';
		$status = 'canceled';
		$comment = 'Ceca ha actualizado el estado del pedido con el valor "'.$status.'"';
		$isCustomerNotified = true;
		$order->setState($state, $status, $comment, $isCustomerNotified);
		$order->registerCancellation("")->save();
		$order->save();
		}
	$this->_redirect('checkout/onepage/failure');
	}
	/*
	public function testAction(){

	}*/

}