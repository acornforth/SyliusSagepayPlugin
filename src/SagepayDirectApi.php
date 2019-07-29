<?php

declare(strict_types=1);

namespace Sbarbat\SyliusSagepayPlugin;

use Http\Message\MessageFactory;

use Payum\Core\HttpClientInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Request\RenderTemplate;
use Payum\Core\Exception\Http\HttpException;
use Sylius\Component\Core\Model\OrderInterface;
use Sbarbat\SyliusSagepayPlugin\Lib\SagepayUtil;
use Sylius\Component\Core\Model\PaymentInterface;

use Sbarbat\SyliusSagepayPlugin\Lib\SagepayRequest;
use Sbarbat\SyliusSagepayPlugin\Sanitizers\NameSanitizer;
use Sbarbat\SyliusSagepayPlugin\Sanitizers\SanitizerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SagepayDirectApi extends SagepayApi
{
    /**
     * @param array $fields
     *
     * @return array
     */
    protected function doRequest($method, $path, array $fields = [])
    {
        $headers = [
            "Authorization" => $this->getBasicAuthenticationHeader(),
            "Cache-Control" => 'no-cache',
            "Content-Type" => "application/json"
        ];

        $fileds = array_merge($fields, [
            'vendorName' => $this->options['vendorName'],
        ]);

        $url = $this->getApiEndpoint() . $path;
        $request = $this->messageFactory->createRequest($method, $url, $headers, http_build_query($fields));

        $response = $this->client->send($request);

        if (false == ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300)) {
            throw HttpException::factory($request, $response);
        }

        return $response;
    }

    public function getMerchantSessionKey()
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => $this->getApiEndpoint() . "merchant-session-keys",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => '{ "vendorName": "'.$this->options['vendorName'].'" }',
        CURLOPT_HTTPHEADER => array(
            "Authorization: " . $this->getBasicAuthenticationHeader(),
            "Cache-Control: no-cache",
            "Content-Type: application/json"
        ),
        ));
        
        $response = json_decode(curl_exec($curl));
        $err = curl_error($curl);
 
        curl_close($curl);

        if (isset($response->code)) {
            throw new NotFoundHttpException(sprintf('Error getting merchant session key: %s', json_encode($response)));
        }

        return json_encode($response);
    }

    public function validate3DAuthResponse($transactionId, $paRes)
    {
        $curl = curl_init();
 
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->getApiEndpoint()  . "transactions/" . $transactionId . "/3d-secure",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => '{ "paRes": "' . $paRes . '" }',
            CURLOPT_HTTPHEADER => array(
                "Authorization: " . $this->getBasicAuthenticationHeader(),
                "Cache-Control: no-cache",
                "Content-Type: application/json"
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response);
    }

    public function getTransactionOutcome($transactionId)
    {
        $curl = curl_init();
 
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->getApiEndpoint()  . "transactions/" . $transactionId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                "Authorization: " . $this->getBasicAuthenticationHeader(),
                "Cache-Control: no-cache",
            ),
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);

        return json_decode($response);
    }

    public function is3DAuthResponse($httpRequest): bool
    {
        return isset($httpRequest) && 'POST' === $httpRequest->method && isset($httpRequest->request['PaRes']);
    }

    public function getBasicAuthenticationHeader()
    {
        $str = 'Basic %s';

        $token = base64_encode($this->getIntegrationKey() .':'. $this->getIntegrationPassword());

        return sprintf($str, $token);
    }

    public function getIntegrationKey()
    {
        return $this->options['sandbox'] ? $this->options['integrationKeyTest'] : $this->options['integrationKeyLive'];
    }

    public function getIntegrationPassword()
    {
        return $this->options['sandbox'] ? $this->options['integrationPasswordTest'] : $this->options['integrationPasswordLive'];
    }
}
