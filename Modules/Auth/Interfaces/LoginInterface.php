<?php

namespace Modules\Auth\Interfaces;

interface LoginInterface
{
    public function redirectTo(): string;
}
