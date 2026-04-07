<?php

namespace Zhanghongfei\OrgRbac\Enums;

enum DataScope: string
{
    case Self = 'self';
    case Department = 'department';
    case Subtree = 'subtree';
    case Tenant = 'tenant';
}
