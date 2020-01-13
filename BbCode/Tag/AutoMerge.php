<?php

namespace ThemeHouse\AutoMergeDoublePost\BbCode\Tag;

use XF\BbCode\Renderer\AbstractRenderer;

/**
 * Class AutoMerge
 * @package ThemeHouse\AutoMergeDoublePost\BbCode\Tag
 */
class AutoMerge
{
    /**
     * @param $tagChildren
     * @param $tagOption
     * @param $tag
     * @param array $options
     * @param AbstractRenderer $renderer
     * @return string
     */
    public static function renderTag($tagChildren, $tagOption, $tag, array $options, AbstractRenderer $renderer)
    {
        $string = $renderer->renderSubTreePlain($tagChildren);

        return \XF::app()->templater()->renderTemplate('public:kl_amdp_merge_message', [
            'time' => $string,
        ]);
    }
}