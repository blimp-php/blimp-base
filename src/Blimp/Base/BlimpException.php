<?php
namespace Blimp\Base;

use Symfony\Component\HttpKernel\Exception\HttpException;

class BlimpException extends HttpException implements \JsonSerializable {
    private $internalCode;
    private $description;

    public function __construct($statusCode = 500, $message = null, $description = null, $previous = NULL, array $headers = array()) {
        parent::__construct($statusCode, $message, $previous, $headers);

        $this->description = $description;
    }

    public function setDescription($description) {
        $this->description = $description;
    }

    public function getDescription() {
        return $this->description;
    }

    public function jsonSerialize() {
        $obj = [];

        $obj["code"] = $this->getStatusCode();

        $message = $this->getMessage();
        if(!empty($message)) {
            $obj["error"] = $message;
        }

        if(!empty($this->description)) {
            if(is_scalar($this->description)) {
                $obj["error_description"] = $this->description;
            } else {
                $obj = array_merge($obj, $this->description);
            }
        }

        return $obj;
    }
}
