<?php

namespace CultureObject\Exception;

class Exception extends \Exception { }
class ProviderException extends Exception { }

class NotYetImplementedException extends Exception {
  protected $message = 'This functionality is not yet implemented';
}