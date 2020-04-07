<?php


namespace App\Infrastructure\Services;


use App\Core\Constants;
use App\Domain\Contracts\IVAOApiServiceContract;
use Illuminate\Support\Facades\Http;

class IVAOApiService implements IVAOApiServiceContract
{
    private $IVAOTOKEN;
    private $data;
    private const ENDPOINT = Constants::IVAO_API_URL;
    private $httpClient;

    /**
     * IVAOApiService constructor.
     * @param $IVAOTOKEN
     */

    public function __construct($IVAOTOKEN, Http $httpClient)
    {
        $this->IVAOTOKEN = $IVAOTOKEN;
        $this->httpClient = $httpClient;
    }

    public function getUserData()
    {
        if($this->data){
            return $this->data;
        }

        $response = $this->httpClient->get(self::ENDPOINT, [ 'token' => $this->IVAOTOKEN, 'type' => 'json']);

        return $this->data = $response->json();
    }
}
