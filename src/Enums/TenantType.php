<?php

namespace Zhanghongfei\OrgRbac\Enums;

enum TenantType: string
{
    case Platform = 'platform';
    case Organisation = 'organisation';
    case Department = 'department';
    case Team = 'team';
}
