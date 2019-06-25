<?php

namespace ThemeHouse\AutoMergeDoublePost;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepResult;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Entity\PermissionEntry;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait {
        install as public traitInstall;
    }
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    /**
     * @param array $stepParams
     *
     * @return null|StepResult
     * @throws \XF\PrintableException
     */
    public function install(array $stepParams = [])
    {
        /** @var \XF\Entity\AddOn $legacyAddOn */
        $legacyAddOn = \XF::em()->find('XF:AddOn', 'KL/UserCriteriaExtended');
        if ($legacyAddOn) {
            $this->db()->delete('xf_addon', "addon_id = 'ThemeHouse/UserCriteria'");
            $legacyAddOn->addon_id = 'ThemeHouse/UserCriteria';
            $legacyAddOn->save();
            return null;
        }

        return $this->traitInstall($stepParams);
    }

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