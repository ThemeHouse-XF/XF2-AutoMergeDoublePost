<?php

namespace ThemeHouse\AutoMergeDoublePost\BbCode\Tag;

use XF\PreEscaped;

class AutoMerge {
    /**
     * @param $tagChildren
     * @param $tagOption
     * @param $tag
     * @param array $options
     * @param \XF\BbCode\Renderer\AbstractRenderer $renderer
     * @return string
     */
    public static function renderTag($tagChildren, $tagOption, $tag, array $options, \XF\BbCode\Renderer\AbstractRenderer $renderer)
    {
        $string = $renderer->renderSubTreePlain($tagChildren);

        return \XF::app()->templater()->renderTemplate('public:kl_amdp_merge_message', [
            'time' => $string,
        ]);
    }
}