<?php
namespace GDO\PaymentPaypal\Method;

use GDO\Core\Website;
use GDO\Language\Trans;
use GDO\Payment\GDT_Money;
use GDO\Payment\MethodPayment;
use GDO\Payment\GDO_Order;
use GDO\PaymentPaypal\Module_PaymentPaypal;
use GDO\PaymentPaypal\Paypal_Util;

final class InitPayment extends MethodPayment
{
	public function execute()
	{
		if (!($order = $this->getOrder()))
		{
			return $this->error('err_order');
		}
		return $this->initCheckout($order);
	}

	private function initCheckout(GDO_Order $order)
	{
		Module_PaymentPaypal::instance()->includePaypal();
		
		$id = $order->getID();
		$gdo = $order->getOrderable();
		$user = $order->getUser();
		
		/* The servername and serverport tells PayPal where the buyer
		 should be directed back to after authorizing payment.
		 In this case, its the local webserver that is running this script
		 Using the servername and serverport, the return URL is the first
		 portion of the URL that buyers will return to after authorizing payment
		 */
		
		/* The returnURL is the location where buyers return when a
		 payment has been succesfully authorized.
		 The cancelURL is the location buyers are sent to when they hit the
		 cancel button during authorization of payment during the PayPal flow
		 */
		$successURL = urlencode(url('PaymentPaypal', 'ConfirmCheckout', "id={$id}"));
		$cancelURL = urlencode($gdo->getOrderCancelURL($user));
		$no_shipping = '1';
		
		/* Construct the parameter string that describes the PayPal payment
		 the varialbes were set in the web form, and the resulting string
		 is stored in $nvpstr
		 */
		$paymentAmount = round($order->getPrice(), 2);
		$paymentType = "Sale";
		$currencyCodeType = GDT_Money::$CURRENCY;
		$nvpstr = "&Amt=$paymentAmount".
				"&PAYMENTACTION=$paymentType".
				"&ReturnUrl=$successURL".
				"&CANCELURL=$cancelURL".
				"&CURRENCYCODE=$currencyCodeType".
				"&no_shipping=$no_shipping".
				"&LOCALECODE=".strtoupper(Trans::$ISO);
		
		/* Make the call to PayPal to set the Express Checkout token
		 If the API call succeded, then redirect the buyer to PayPal
		 to begin to authorize payment.  If an error occured, show the
		 resulting errors
		 */
		$resArray = Paypal_Util::hash_call('SetExpressCheckout', $nvpstr);
		
		$ack = strtoupper($resArray["ACK"]);
		if($ack=="SUCCESS")
		{
			// Redirect to paypal.com here
			$token = urldecode($resArray["TOKEN"]);
// 			$order->saveVar('order_xtoken', $token);
			return Website::redirect(PAYPAL_URL . $token);
		}
		else
		{
			return Paypal_Util::paypalError($resArray);
		}
	}
}
