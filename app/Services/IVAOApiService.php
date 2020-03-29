<?php

namespace App\Services;
use App\Services\Contracts\IVAOApiServiceContract;
use App\Exceptions\InvalidIVAOTokenException;

class IVAOApiService implements  IVAOApiServiceContract{

    private $IVAOTOKEN;
    private $vid;

    /**
     * @return mixed
     */
    public function getIVAOTOKEN()
    {
        return $this->IVAOTOKEN;
    }

    /**
     * @return mixed
     */
    public function getVid()
    {
        return $this->vid;
    }

    /**
     * @return mixed
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * @return mixed
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * @return mixed
     */
    public function getRatingATC()
    {
        return $this->ratingATC;
    }

    /**
     * @return mixed
     */
    public function getRatingPilot()
    {
        return $this->ratingPilot;
    }

    /**
     * @return mixed
     */
    public function getDivision()
    {
        return $this->division;
    }

    /**
     * @return mixed
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @return mixed
     */
    public function getStaff()
    {
        return $this->staff;
    }
    private $firstName;
    private $lastName;
    private $ratingATC;
    private $ratingPilot;
    private $division;
    private $country;
    private $staff;

    public function __construct($IVAOTOKEN){
        $this->IVAOTOKEN = $IVAOTOKEN;
        if($IVAOTOKEN == 'error'){
            throw new InvalidIVAOTokenException();
        }
        $this->getUserData();
    }

    public function getUserData(){
        $client = new \GuzzleHttp\Client();
        $token = $this->IVAOTOKEN;
        $ApiEndpoint = "http://login.ivao.aero/api.php?token=$token";
        $response = $client->get($ApiEndpoint);
        $userData = new \SimpleXMLElement($response->getBody());

        if($userData->result == 0){
            return redirect()->route('login');
        }

        $this->vid = $userData->vid;
        $this->firstName = $userData->firstname;
        $this->lastName = $userData->lastname;
        $this->ratingATC = $userData->ratingatc;
        $this->ratingPilot = $userData->ratingpilot;
        $this->division = $userData->division;
        $this->country = $userData->country;
        if($userData->staff == null){
            $this->staff = [];
        }
        else {
            $this->staff = explode(":",$userData->staff);
        }
    }
}

?>
