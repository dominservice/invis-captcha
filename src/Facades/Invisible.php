<?php
namespace Dominservice\Invisible\Facades;
use Illuminate\Support\Facades\Facade;

class Invisible extends Facade
{
    protected static function getFacadeAccessor() { return 'invis'; }
}
