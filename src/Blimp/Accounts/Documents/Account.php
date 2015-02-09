<?php
namespace Blimp\Accounts\Documents;

use Blimp\DataAccess\Documents\BlimpDocument;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/** @ODM\Document */
class Account extends BlimpDocument {
    /** @ODM\Id(strategy="NONE") */
    protected $id;

    /** @ODM\String */
    protected $type;

    /** @ODM\Hash */
    protected $authData;

    /** @ODM\Hash */
    protected $profileData;

    public function setId($id) {
        $this->id = $id;
    }

    public function setType($type) {
        $this->type = $type;
    }
    public function getType() {
        return $this->type;
    }

    public function setAuthData($authData) {
        $this->authData = $authData;
    }
    public function getAuthData() {
        return $this->authData;
    }
    
    public function setProfileData($profileData) {
        $this->profileData = $profileData;
    }
    public function getProfileData() {
        return $this->profileData;
    }
}
