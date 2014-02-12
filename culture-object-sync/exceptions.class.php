<?php

class Culture_Object_Sync_Exception extends Exception { }
class Culture_Object_Sync_Provider_Exception extends Exception { }

class Culture_Object_Sync_Not_Yet_Implemented_Exception extends Exception {
  protected $message = 'This functionality is not yet implemented';
}