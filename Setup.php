<?php

namespace ThemeHouse\AutoMergeDoublePost;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Entity\PermissionEntry;

/**
 * Class Setup
 * @package ThemeHouse\AutoMergeDoublePost
 */
class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    /**
     *
     */
    public function upgrade1010151Step1()
    {
        \XF::db()->beginTransaction();

        $perms = \XF::finder('XF:PermissionEntry')
            ->where('permission_id', '=', 'klAMDPMergeTime')
            ->where('permission_value_int', '<>', '-1')
            ->fetch();

        foreach ($perms as $perm) {
            /** @var PermissionEntry $perm */
            $perm->fastUpdate('permission_value_int', $perm->permission_value_int * 60);
        }

        $this->app->jobManager()->enqueue('XF:PermissionRebuild');

        \XF::db()->commit();
    }
}