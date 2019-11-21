<?php

	define('tokenUrlLive', 'https://oppwa.com/v1/checkouts');
	define('tokenUrlTest', 'https://test.oppwa.com/v1/checkouts');

	define('sadadStatusTestUrl','https://stg.sadad.hyperpay.com/PayWareHub/api/PayWare/GetCheckoutStatus');
	define('sadadStatusLiveUrl','https://sadad.hyperpay.com/PayWareHub/api/PayWare/GetCheckoutStatus');
	define('sadadRequestTestUrl','https://stg.sadad.hyperpay.com/PayWareHub/api/PayWare/SetCheckout');
	define('sadadRequestLivetUrl','https://sadad.hyperpay.com/PayWareHub/api/PayWare/SetCheckout');
	define('sadadTestRedirectUrl','https://stg.sadad.hyperpay.com/PayWareHub/Pages/Checkout/Checkout.aspx?id=');
	define('sadadLiveRedirectUrl','https://sadad.hyperpay.com/PayWareHub/Pages/Checkout/Checkout.aspx?id=');

	define('jsUrlLive', 'https://oppwa.com/v1/paymentWidgets.js?checkoutId=');
	define('jsUrlTest', 'https://test.oppwa.com/v1/paymentWidgets.js?checkoutId=');

	define('captureUrlLive','https://oppwa.com/v1/payments/');
	define('captureUrlTest','https://test.oppwa.com/v1/payments/');

	//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++=

	if(!function_exists('getPostParameter')){
		function getPostParameter($dataCust,$dataTransaction) 
		{
            if($dataTransaction['tx_mode'] != "live" ) {
                $dataCust['amount'] = (int) $dataCust['amount'];
            }else {
                $dataCust['amount'] = number_format($dataCust['amount'], 2, '.', '');
            }

            $data = "entityId=".$dataTransaction['entityId'].
                "&amount=".$dataCust['amount'].
                "&currency=".$dataCust['currency'].
                "&paymentType=".$dataTransaction['payment_type'].
                "&customer.ip=".$dataCust['ip'].
                "&customer.email=".$dataCust['email'].
                "&shipping.customer.email=".$dataCust['email'].
                "&merchantTransactionId=". $dataTransaction['orderId'];
            $data .= getBillingAndShippingAddress($dataTransaction,$dataCust);
            if(!empty($dataTransaction['risk_channel_id'])) {
                $data .= "&risk.channelId=".$dataTransaction['risk_channel_id'].
                    "&risk.serviceId=I".
                    "&risk.amount=".$dataCust['amount'].
                    "&risk.parameters[USER_DATA1]=Mobile";
            }



            if ($dataTransaction['tx_mode'] == "test") {
                $data .= "&testMode=EXTERNAL";
            }
            /*   .
            "&shipping.method=".$shippingMethod*/
            if($dataTransaction['method']=='sadadncb') {
                $data .="&bankAccount.country=SA";
            }

            return $data;
		}	
	}
	if (!function_exists('getBillingAndShippingAddress')){
		function getBillingAndShippingAddress($trans,$data)
		{
			$result = "";
            $firstName = str_replace("&","",$data['first_name']);
            $surName = str_replace("&","",$data['last_name']);
            $country = $data['country_code'];
            $postCode = $data['zip'];
            $city = str_replace("&","",$data['city']);
            $street= str_replace("&","",$data['street']);
            $tel = str_replace("&","",$data['phone']);

            if(!($trans['connector']=='migs' && isThisEnglishText($city)==false)) {
                $result.="&shipping.city=".$city;
                $result.="&billing.city=".$city;
            }

            if(!($trans['connector']=='migs' && isThisEnglishText($country)==false)) {
                $result.="&shipping.country=".$country;
                $result.="&billing.country=".$country;

            }

            if(!($trans['connector']=='migs' && isThisEnglishText($postCode)==false)) {
                $result.="&shipping.postcode=".$postCode;
                $result.="&billing.postcode=".$postCode;

            }
            if(!($trans['connector']=='migs' && isThisEnglishText($firstName)==false)) {
                $result.="&shipping.customer.givenName=".$firstName;
                $result.="&customer.givenName=".$firstName;

            }

            if(!($trans['connector']=='migs' && isThisEnglishText($surName)==false)) {
                $result.="&shipping.customer.surname=".$surName;
                $result.="&customer.surname=".$surName;

            }

            if(!($trans['connector']=='migs' && isThisEnglishText($street)==false)) {
                $result.="&billing.street1=".$street;
                $result.="&shipping.street1=".$street;
            }
            if(!($trans['connector']=='migs' && isThisEnglishText($tel)==false)) {
				$result.="&customer.phone=".$tel;
				$result.="&shipping.customer.phone=".$tel;
            }
			return $result;
		}
	}
	if(!function_exists('getTokenUrl')){
		function getTokenUrl($server)
		{
			if ($server=="live")
			{
				$url = tokenUrlLive;
				}
			else
			{
				$url =  tokenUrlTest;
			}
			
			return $url;
		}
	}
	
	if(!function_exists('getToken')){
		function getToken($postData,$url,$server,$auth)
		{
            $test = true;
            if ($server=='live')
            {
                $test = false;
            }
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
             curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Authorization:Bearer '.$auth));
            setCurlOptions($test,$ch);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $responseData = curl_exec($ch);
            if(curl_errno($ch)) {
                throw new Exception( curl_error($ch));
            }
            curl_close($ch);

			$obj=json_decode($responseData,true);
			return $obj;
		}
	}

	if(!function_exists('getCaptureUrl')){
		function getCaptureUrl($server)
		{
			if ($server=="live")
				$url = captureUrlLive;
			else
				$url = captureUrlTest;

			return $url;
		}
	}
	
	if(!function_exists('getPostCaptureOrRefund')){
		function getPostCaptureOrRefund($dataTransaction)
		{

            $data = "entityId=".$dataTransaction['entityId'].
                "&currency=".$dataTransaction['currency'].
                "&amount=".$dataTransaction['amount'].
                "&paymentType=".$dataTransaction['payment_type'];
            if ($dataTransaction['tx_mode'] == "test") {
                $data .= "&testMode=EXTERNAL";
            }
			return $data;
		}
	}

	if(!function_exists('buildResponseArray')){
		function buildResponseArray($response)
		{
			$result = array();
			$entries = explode("&", $response);
			foreach ($entries as $entry) {
				$pair = explode("=", $entry);
				$result[$pair[0]] = urldecode($pair[1]);
			}
			return $result;
		}
	}
	
	if(!function_exists('getStatusUrl')){
		function getStatusUrl($server, $token,$order){
            $code = $order->getPayment()->getMethodInstance()->getCode();
            $entityId = Mage::getStoreConfig('payment/' . $code . '/entityId', $order->getStoreId());
			if ($server=="live")
				$url = tokenUrlLive .'/'. $token.'/payment';
			else
				$url = tokenUrlTest .'/'. $token.'/payment';

			$url .= "?entityId=".$entityId;
			return $url;
		}
	}
	
	if(!function_exists('checkStatusPayment')){
		function checkStatusPayment($server,$url,$order)
		{
            $auth = Mage::getStoreConfig('payment/hyperpay/auth', $order->getStoreId());
			$test = true;
			if ($server=='live')
			{
				$test = false;
			}
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            setCurlOptions($test,$ch);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                   'Authorization:Bearer '.$auth));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $responseData = curl_exec($ch);
            if(curl_errno($ch)) {
                throw new Exception(curl_error($ch));
            }
            curl_close($ch);
			$resultJson = json_decode($responseData, true);
			
			return $resultJson;
		}
	}

	if(!function_exists('getJsUrl')){
		function getJsUrl($token,$server){
			if ($server=="live")
				$url = jsUrlLive . $token;
			else
				$url = jsUrlTest . $token;
			return $url;
		}
	}
	/**
	* method to check if test passed is English
	*
	* @param (string) $text to be checked.
	* @return (bool) true|false.
	*/
	if (!function_exists('isThisEnglishText'))
	{
	 function isThisEnglishText($text)
	{
	return preg_match("/^[\w\s\.\-\,]*$/", $text); 
		}
	}
	if (!function_exists('getSadadReqUrl'))
	{
		function getSadadReqUrl($server)
		{
            if ($server=="live")
            {
                return sadadRequestLivetUrl;
            }

            return sadadRequestTestUrl;

        }
	}
	if (!function_exists('sadadCurlRequest'))
	{
		function sadadCurlRequest($url,$data,$test)
		{

            $curl = curl_init($url);
            $headers = array(
                'Content-Type: application/json',
                'Content-Length: '. strlen($data));

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
			setCurlOptions($test,$curl);

            $curl_response = curl_exec($curl);
            $http_status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($curl_response === false) {
                $info = curl_getinfo($curl);
                curl_close($curl);
                throw new Exception('error occured during curl exec. Additioanl info: ' . var_export($info));
            }
            curl_close($curl);
            if ($http_status_code == 200) {
                return json_decode($curl_response);
            }
            return '';
		}
	if (!function_exists('replaceArrayKeys')) {
    	function replaceArrayKeys($array)
    	{
            $replacedKeys = str_replace('_', '.', array_keys($array));
            return array_combine($replacedKeys, $array);
        }
    	}
	}
	if (!function_exists('getSadadRedirectUrl'))
	{
    	function getSadadRedirectUrl($server)
    	{
        if ($server=="live")
        {
            return sadadLiveRedirectUrl;
        }

        return sadadTestRedirectUrl;

    	}
	}
	if (!function_exists('setCurlOptions'))
	{
     	function setCurlOptions($test,$curl)
    	{
        if($test) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        } else {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        }
    	}
	}
	if (!function_exists('getSadadStatusUrl'))
	{
    	function getSadadStatusUrl($server)
    	{
        	if ($server=="live")
        	{
            	return sadadStatusLiveUrl;
        	}
        return sadadStatusTestUrl;

    	}
	}
	if (!function_exists('getMerchantId'))
	{
    	function getMerchantId($order)
    	{
            $code = $order->getPayment()->getMethodInstance()->getCode();
            return Mage::getStoreConfig('payment/' . $code . '/merchantid', $this->getOrder()->getStoreId());

    	}
	}

?>
