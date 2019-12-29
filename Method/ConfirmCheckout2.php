<?php
namespace GDO\PaymentPaypal\Method;

use GDO\Payment\MethodPayment;
use GDO\Payment\Module_Payment;
use GDO\Payment\GDO_Order;
use GDO\PaymentPaypal\Paypal_Util;
use GDO\Util\Common;

final class ConfirmCheckout2 extends MethodPayment
{
	public function execute()
	{
		$mp = Module_Payment::instance();
		
		if (false === ($gdo_token = Common::getPost('gdo_token'))) {
			return $mp->error('err_token');
		}
		
		if (false === ($order = GDO_Order::getByToken($gdo_token))) {
			return $mp->error('err_order');
		}
		
		if ($order->isProcessed()) {
			return $mp->message('err_already_done');
		}
		
		if (!$order->isCreated()) {
			return $mp->error('err_order');
		}
		
		/* Gather the information to make the final call to
		finalize the PayPal payment.  The variable nvpstr
		holds the name value pairs
		*/
		if (false === ($resArray = @unserialize($order->getOrderXToken())))
		{
			return $mp->error('err_xtoken', [sitename()]);
		}
		$token = $resArray["TOKEN"];
		$paymentAmount = $order->getOrderPriceTotal();
		$paymentType = "Sale";
		$currCodeType = $order->getOrderCurrency();
		$payerID = urlencode($resArray["PAYERID"]);
		$serverName = urlencode($_SERVER['SERVER_NAME']);
		$order->saveVar('order_email', $resArray["EMAIL"]);
		
		$nvpstr = '&TOKEN='.$token.'&PAYERID='.$payerID.'&PAYMENTACTION='.$paymentType.'&AMT='.$paymentAmount.'&CURRENCYCODE='.$currCodeType.'&IPADDRESS='.$serverName ;
		$nvpstr .= "&ITEMAMT=".$paymentAmount."&L_QTY0=1"."&L_NAME0=".urlencode($order->getOrderDescrAdmin())."&L_AMT0=".$paymentAmount;	
		 /* Make the call to PayPal to finalize payment
			If an error occured, show the resulting errors
		*/
		$resArray = Paypal_Util::hash_call('DoExpressCheckoutPayment', $nvpstr);
	
		/* Display the API response back to the browser.
		   If the response from PayPal was a success, display the response parameters'
		   If the response was an error, display the errors received using APIError.php.
		   */
		$ack = strtoupper($resArray["ACK"]);
	
		if($ack!="SUCCESS"){
			return Paypal_Util::paypalError($resArray);
		}
		
		// Get Payment module;
		$module2 = $order->getOrderModule();
		$module2->onLoadLanguage();
		
		Paypal_Util::logResArray($resArray);
		
		$status = strtoupper($resArray['PAYMENTSTATUS']);
		if ($status === 'COMPLETED')
		{
			return $mp->onExecuteOrder($module2, $order);
		}
		else
		{
			return $mp->onPendingOrder($module2, $order);
		}
	}
}
