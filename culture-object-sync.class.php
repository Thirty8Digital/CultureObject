<?php

require_once('twitter-hashtag-race/core.class.php');
require_once('twitter-hashtag-race/exceptions.class.php');
require_once('twitter-hashtag-race/provider.class.php');
require_once('twitter-hashtag-race/providers/culturegrid.class.php');

class Culture_Object_Sync extends Culture_Object_Sync_Core {
  
  function __construct() {
    throw new Culture_Object_Sync_Not_Yet_Implemented_Exception();
  }
  
}