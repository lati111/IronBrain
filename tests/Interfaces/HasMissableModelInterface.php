<?php

namespace Tests\Interfaces;

interface HasMissableModelInterface
{
    /**
     * Gets the message for when an a required item was not found
     * @return string The message
     */
    public function getNotFoundMessage(): string;
}
