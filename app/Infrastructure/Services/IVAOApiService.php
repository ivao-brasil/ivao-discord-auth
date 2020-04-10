<?php


namespace App\Infrastructure\Services;


use App\Core\Constants;
use App\Domain\Contracts\IVAOApiServiceContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IVAOApiService implements IVAOApiServiceContract
{
    private $IVAOTOKEN;

    /**
     * @return mixed
     */
    public function getIVAOTOKEN()
    {
        if($this->IVAOTOKEN)
        {
            return $this->IVAOTOKEN;
        }
        else {
            return request()->session()->get('IVAOTOKEN');
        }
    }
    private $data;
    private const ENDPOINT = Constants::IVAO_API_URL;
    private $httpClient;

    /**
     * IVAOApiService constructor.
     * @param $IVAOTOKEN
     */

    public function __construct(Http $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getUserData()
    {
        if($this->data){
            return $this->data;
        }
        $response = $this->httpClient::get(self::ENDPOINT, [ 'token' => $this->getIVAOTOKEN(), 'type' => 'json']);
        return $this->data = $response->json();
    }
}
