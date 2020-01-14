<?php

namespace ThemeHouse\AutoMergeDoublePost\XF\Pub\Controller;

use XF\Entity\Post;
use XF\Mvc\ParameterBag;
use XF\Service\Post\Editor;

/**
 * Class Thread
 * @package ThemeHouse\AutoMergeDoublePost\XF\Pub\Controller
 */
class Thread extends XFCP_Thread
{
    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionAddReply(ParameterBag $params)
    {
        $this->assertPostOnly();

        /** @var \XF\Entity\Thread $thread */
        $thread = $this->assertViewableThread($params['thread_id']);

        /** @var Post $lastPost */
        $lastPost = $thread->LastPost;

        /* Skip post merge if last post is from a different user */
        if ($lastPost->user_id === \XF::visitor()->user_id) {
            /** @var \ThemeHouse\AutoMergeDoublePost\Repository\MergeTime $mergeRepo */
            $mergeRepo = $this->repository('ThemeHouse\AutoMergeDoublePost:MergeTime');
            $mergeTime = $mergeRepo->getVisitorMergeTime($thread->Forum);


            if (\XF::$time <= $mergeTime + $lastPost->post_date) {
                return $this->mergeReply($params, $lastPost);
            }
        }

        return parent::actionAddReply($params);
    }

    /**
     * @param ParameterBag $params
     * @param Post $post
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\Reroute|\XF\Mvc\Reply\View
     */
    protected function mergeReply(ParameterBag $params, Post $post)
    {
        /* Captcha check */
        if ($this->filter('no_captcha', 'bool')) // JS is disabled so user hasn't seen Captcha.
        {
            $this->request->set('requires_captcha', true);
            return $this->rerouteController(__CLASS__, 'reply', $params);
        } else {
            if (!$this->captchaIsValid()) {
                return $this->error(\XF::phrase('did_not_complete_the_captcha_verification_properly'));
            }
        }

        /** @var \XF\ControllerPlugin\Editor $editorPlugin */
        $editorPlugin = $this->plugin('XF:Editor');
        $secondMessage = $editorPlugin->fromInput('message');

        if (!strlen(trim($secondMessage))) {
            return $this->error(\XF::phrase('please_enter_valid_message'));
        }

        $options = \XF::app()->options();
        if ($options->kl_amdp_merge_message) {
            $time = \XF::$time;
            $mergeMessage = "\n[automerge]{$time}[/automerge]";
        } else {
            $mergeMessage = '';
        }
        $message = "{$post->message}{$mergeMessage}\n{$secondMessage}";

        /** @var Editor $editor */
        $editor = $this->service('XF:Post\Editor', $post);
        $editor->setMessage($message);

        /* Add attachments */
        $forum = $post->Thread->Forum;
        if ($forum->canUploadAndManageAttachments()) {
            $editor->setAttachmentHash($this->filter('attachment_hash', 'str'));
        }

        $editor->checkForSpam();

        if (!$editor->validate($errors)) {
            return $this->error($errors);
        }

        $editor->save();

        $this->finalizePostMerge($editor, $secondMessage);

        /* Delete Draft */
        $post->Thread->draft_reply->delete();

        return $this->redirect($this->buildLink('posts', $post), \XF::phrase('kl_amdp_post_has_been_merged'));
    }

    /**
     * @param Editor $editor
     * @param string $secondMessage
     */
    protected function finalizePostMerge(Editor $editor, $secondMessage)
    {
        $options = \XF::app()->options();
        $post = $editor->getPost();

        if ($options->kl_amdp_send_merge_alert) {

            /* Send merge alert to visitor */
            $visitor = \XF::visitor();
            /** @var \XF\Repository\UserAlert $alertRepo */
            $alertRepo = $this->repository('XF:UserAlert');
            $alertRepo->alert(
                $visitor,
                $visitor->user_id, '',
                'user', $visitor->user_id,
                "post_merged", [
                    'post_id' => $post->post_id,
                    'thread_id' => $post->thread_id,
                    'title' => $post->Thread->title
                ]
            );
        }

        if ($options->kl_amdp_merge_notifications) {
            /** @var \XF\Service\Message\Preparer $messagePreparer */
            $messagePreparer = $this->service('XF:Message\Preparer', 'post');
            $messagePreparer->prepare($secondMessage);

            /** @var \XF\Entity\User $user */
            $user = $post->User;
            $mentions = $user->getAllowedUserMentions($messagePreparer->getMentionedUsers());
            $quoted = $messagePreparer->getQuotesKeyed('member');

            /** @var \XF\Service\Post\Notifier $notifier */
            $notifier = $this->service('XF:Post\Notifier', $post, 'reply');
            $notifier->setMentionedUserIds(array_keys($mentions));
            $notifier->setQuotedUserIds(array_keys($quoted));
            $notifier->notifyAndEnqueue(3);
        }
    }
}
