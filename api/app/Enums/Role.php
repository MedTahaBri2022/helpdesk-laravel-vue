<?php

namespace App\Enums;

enum Role: string
{
    case Customer = 'customer';
    case Agent = 'agent';
    case Admin = 'admin';

    /** Agents and administrators handle tickets; customers only see their own. */
    public function isStaff(): bool
    {
        return $this !== self::Customer;
    }
}
