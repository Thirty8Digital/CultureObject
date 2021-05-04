<?php

namespace CultureObject\Exception;

class NotYetImplementedException extends Exception
{
    protected $message = 'This functionality is not yet implemented';

    function __construct()
    {
        $this->message = __('This functionality is not yet implemented', 'culture-object');
    }
}
