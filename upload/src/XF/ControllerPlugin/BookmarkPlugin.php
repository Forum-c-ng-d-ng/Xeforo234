<?php

namespace XF\ControllerPlugin;

use XF\Entity\BookmarkItem;
use XF\Entity\BookmarkTrait;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Repository\BookmarkRepository;
use XF\Service\AbstractService;
use XF\Service\Bookmark\CreatorService;
use XF\Service\Bookmark\EditorService;

class BookmarkPlugin extends AbstractPlugin
{
	/**
	 * @param Entity $content
	 * @param string $confirmUrl
	 *
	 * @return AbstractReply
	 *
	 * @throws Exception
	 */
	public function actionBookmark(Entity $content, $confirmUrl)
	{
		if ($this->isPost())
		{
			return $this->actionSaveBookmark($content, $confirmUrl);
		}
		else
		{
			return $this->actionConfirm($content, $confirmUrl);
		}
	}

	/**
	 * @param Entity|BookmarkTrait $content
	 * @param string $confirmUrl
	 *
	 * @return AbstractReply
	 *
	 * @throws Exception
	 */
	public function actionConfirm(Entity $content, $confirmUrl)
	{
		if (!$content->isBookmarked() && !$content->canBookmark($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		return $this->getBookmarkEditReply($content, $confirmUrl, [
			'delete' => $this->filter('delete', 'bool'),
			'tooltip' => $this->filter('tooltip', 'bool'),
		]);
	}

	/**
	 * @param Entity|BookmarkTrait $content
	 * @param string $confirmUrl
	 * @param array $modifiers
	 * @param BookmarkItem|null $bookmark
	 *
	 * @return AbstractReply
	 */
	public function getBookmarkEditReply(
		Entity $content,
		$confirmUrl,
		array $modifiers = [],
		?BookmarkItem $bookmark = null
	)
	{
		$modifiers = array_replace([
			'delete' => false,
			'tooltip' => false,
			'added' => false,
		], $modifiers);

		if ($bookmark === null)
		{
			$bookmark = $content->getBookmark() ?: $content->getNewBookmark();
		}

		/** @var BookmarkRepository $bookmarkRepo */
		$bookmarkRepo = $this->repository(BookmarkRepository::class);

		$labelFinder = $bookmarkRepo->findLabelsForUser(\XF::visitor()->user_id);
		$labels = $labelFinder->fetch()->pluckNamed('label', 'label');

		$viewParams = [
			'bookmark' => $bookmark,
			'confirmUrl' => $confirmUrl,
			'content' => $content,
			'allLabels' => $labels,
		];

		if ($content->isBookmarked() && $modifiers['delete'])
		{
			return $this->view('XF:Bookmark\Delete', 'bookmark_delete', $viewParams);
		}
		else
		{
			$viewParams['tooltip'] = $modifiers['tooltip'];
			$viewParams['added'] = $modifiers['added'];
			return $this->view('XF:Bookmark\Edit', 'bookmark_edit', $viewParams);
		}
	}

	/**
	 * @param Entity|BookmarkTrait $content
	 *
	 * @return CreatorService
	 */
	protected function setupBookmarkCreator(Entity $content)
	{
		/** @var CreatorService $creator */
		$creator = $this->service(CreatorService::class, $content);

		$message = $this->filter('message', 'str');
		$creator->setMessage($message);

		$labels = $this->filter('labels', 'str');
		$creator->setLabels($labels);

		return $creator;
	}

	/**
	 * @param CreatorService $creator
	 */
	protected function finalizeBookmarkCreator(CreatorService $creator)
	{
	}

	/**
	 * @param BookmarkItem $bookmark
	 *
	 * @return AbstractService|EditorService
	 */
	protected function setupBookmarkEditor(BookmarkItem $bookmark)
	{
		/** @var EditorService $editor */
		$editor = $this->service(EditorService::class, $bookmark);

		$message = $this->filter('message', 'str');
		$editor->setMessage($message);

		$labels = $this->filter('labels', 'str');
		$editor->setLabels($labels);

		return $editor;
	}

	/**
	 * @param EditorService $editor
	 */
	protected function finalizeBookmarkEditor(EditorService $editor)
	{
	}

	/**
	 * @param Entity|BookmarkTrait $content
	 * @param string $confirmUrl
	 *
	 * @return AbstractReply
	 *
	 * @throws Exception
	 */
	public function actionSaveBookmark(Entity $content, $confirmUrl)
	{
		$isBookmarked = $content->isBookmarked();

		if (!$isBookmarked && !$content->canBookmark($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		$contentType = $content->getEntityContentType();
		if (!$contentType)
		{
			throw new \InvalidArgumentException("Provided entity must define a content type in its structure");
		}

		if ($isBookmarked)
		{
			$bookmark = $content->getBookmark();

			if ($this->request->exists('delete'))
			{
				$bookmark->delete();
				$bookmark = null;

				$switchKey = 'bookmarkremoved';
				$text = \XF::phrase('add_bookmark');
			}
			else
			{
				$errors = [];

				$editor = $this->setupBookmarkEditor($bookmark);
				if (!$editor->validate($errors))
				{
					throw $this->exception($this->error($errors));
				}
				$editor->save();

				$this->finalizeBookmarkEditor($editor);

				$switchKey = 'bookmarked';
				$text = \XF::phrase('edit_bookmark');
			}
		}
		else
		{
			$creator = $this->setupBookmarkCreator($content);
			if (!$creator->validate($errors))
			{
				throw $this->exception($this->error($errors));
			}
			$creator->save();
			$bookmark = $creator->getBookmark();

			$this->finalizeBookmarkCreator($creator);

			$switchKey = 'bookmarked';
			$text = \XF::phrase('edit_bookmark');
		}

		if ($this->filter('_xfWithData', 'bool'))
		{
			$message = $switchKey == 'bookmarked'
				? \XF::phrase('bookmark_saved_successfully')
				: \XF::phrase('bookmark_deleted_successfully');

			if ($switchKey == 'bookmarked' && $this->filter('tooltip', 'bool'))
			{
				$reply = $this->getBookmarkEditReply($content, $confirmUrl, ['tooltip' => true, 'added' => true], $bookmark);
				$reply->setJsonParam('message', $message);
			}
			else
			{
				$reply = $this->redirect($this->getDynamicRedirect(), $message);
			}

			$reply->setJsonParam('switchKey', $switchKey);
			$reply->setJsonParam('text', $text);
			return $reply;
		}
		else
		{
			throw $this->exception($this->redirect($this->getDynamicRedirect()));
		}
	}
}
