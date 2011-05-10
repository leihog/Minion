<?php
namespace Bot\Event;

class Plugin extends Event
{
    public function getPlugin()
    {
        return $this->params['plugin'];
    }
}