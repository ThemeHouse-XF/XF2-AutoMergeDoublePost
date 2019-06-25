<?php

namespace ThemeHouse\AutoMergeDoublePost\Repository;

use XF\Entity\Forum;
use XF\Entity\Node;
use XF\Mvc\Entity\Repository;

class MergeTime extends Repository {
    /**
     * @param Node|Forum $node
     * @return float|int
     */
    public function getVisitorMergeTime($node) {
        $visitor = \XF::visitor();

        $options = \XF::app()->options();
        $mergeMode = $options->kl_amdp_minimal_time;

        if($visitor->hasNodePermission($node->node_id, 'klAMDPBypassMerge')) {
            return 0;
        }

        if($mergeMode) {
            $userGroups = $visitor->secondary_group_ids;
            $userGroups[] = $visitor->user_group_id;

            $permissionFinder = $this->finder('XF:PermissionEntry');
            $permissions = $permissionFinder->where('permission_id', '=', 'klAMDPMergeTime')
                ->whereOr([
                    ['user_group_id', '=', $userGroups],
                    ['user_id', '=', $visitor->user_id]
                ])
                ->fetch();

            $contentPermissionFinder = $this->finder('XF:PermissionEntryContent');
            $contentPermissions = $contentPermissionFinder->where('permission_id', '=', 'klAMDPMergeTime')
                ->where('content_type', '=', 'node')
                ->where('content_id', '=', $node->node_id)
                ->whereOr([
                    ['user_group_id', '=', $userGroups],
                    ['user_id', '=', $visitor->user_id]
                ])
                ->fetch();

            $minTime = PHP_INT_MAX;
            foreach($permissions as $permission) {
                /** @var \XF\Entity\PermissionEntry $permission */
                $minTime = min($minTime, $permission->permission_value_int);
            }
            foreach($contentPermissions as $permission) {
                /** @var \XF\Entity\PermissionEntry $permission */
                $minTime = min($minTime, $permission->permission_value_int);
            }
            return $minTime * 60;
        }
        else {
            $mergeTime = $visitor->hasNodePermission($node->node_id, 'klAMDPMergeTime');

            if($mergeTime == -1) {
                $mergeTime = PHP_INT_MAX;
            }

            return $mergeTime * 60;
        }
    }
}