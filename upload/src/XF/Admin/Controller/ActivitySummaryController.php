<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\SortPlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Entity\ActivitySummarySection;
use XF\Entity\Option;
use XF\Finder\ActivitySummarySectionFinder;
use XF\Http\Request;
use XF\Job\ActivitySummaryEmail;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Repository\ActivitySummaryRepository;

class ActivitySummaryController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('activitySummary');
	}

	public function actionIndex()
	{
		$activitySummaryRepo = $this->getActivitySummaryRepo();

		$sectionFinder = $activitySummaryRepo->findActivitySummarySectionsForList();
		$sections = $sectionFinder->fetch();

		$options = $this->em()->findByIds(Option::class, ['activitySummaryEmail', 'activitySummaryEmailBatchLimit']);

		$viewParams = [
			'sections' => $sections,
			'testSuccess' => $this->filter('test_email_success', 'bool'),
			'isImportRunning' => $this->app->import()->manager()->isImportRunning(),
			'options' => $options,
		];
		return $this->view('XF:ActivitySummary\SectionListing', 'activity_summary_section_list', $viewParams);
	}

	protected function sectionAddEdit(ActivitySummarySection $section)
	{
		$viewParams = [
			'section' => $section,
			'handler' => $section->handler,
			'definition' => $section->ActivitySummaryDefinition,
		];
		return $this->view('XF:ActivitySummary\SectionEdit', 'activity_summary_section_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$section = $this->assertSectionExists($params->section_id);
		return $this->sectionAddEdit($section);
	}

	public function actionAdd()
	{
		$definitionId = $this->filter('definition_id', 'str');
		if (!$definitionId)
		{
			if (!$this->isPost())
			{
				$activitySummaryRepo = $this->getActivitySummaryRepo();
				$definitions = $activitySummaryRepo->findActivitySummaryDefinitionsForList(true)->fetch();

				$viewParams = [
					'definitions' => $definitions,
				];
				return $this->view('XF:ActivitySummary\Add', 'activity_summary_definition_chooser', $viewParams);
			}
		}
		if ($this->isPost())
		{
			if ($definitionId)
			{
				return $this->redirect($this->buildLink('activity-summary/add', [], ['definition_id' => $definitionId]), '');
			}
			else
			{
				return $this->error(\XF::phrase('must_select_activity_summary_definition_for_your_new_section'));
			}
		}
		$section = $this->em()->create(ActivitySummarySection::class);
		$section->definition_id = $definitionId;

		return $this->sectionAddEdit($section);
	}

	protected function sectionSaveProcess(ActivitySummarySection $section)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'definition_id' => 'str',
			'display_order' => 'uint',
			'show_value' => 'bool',
			'active' => 'bool',
		]);

		$form->validate(function (FormAction $form) use ($section)
		{
			$options = $this->filter('options', 'array');
			$request = new Request($this->app->inputFilterer(), $options, [], []);
			$handler = $section->getHandler();
			if ($handler && !$handler->verifyOptions($request, $options, $error))
			{
				if ($error)
				{
					$form->logError($error);
				}
			}
			$section->options = $options;
		});

		$form->basicEntitySave($section, $input);

		$extraInput = $this->filter([
			'title' => 'str',
		]);
		$form->apply(function () use ($extraInput, $section)
		{
			$title = $section->getMasterPhrase();
			$title->phrase_text = $extraInput['title'];
			$title->save();
		});

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params->section_id)
		{
			$section = $this->assertSectionExists($params->section_id);
		}
		else
		{
			$section = $this->em()->create(ActivitySummarySection::class);
		}

		$this->sectionSaveProcess($section)->run();

		return $this->redirect($this->buildLink('activity-summary') . $this->buildLinkHash($section->section_id));
	}

	public function actionDelete(ParameterBag $params)
	{
		$section = $this->assertSectionExists($params->section_id);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$section,
			$this->buildLink('activity-summary/delete', $section),
			$this->buildLink('activity-summary/edit', $section),
			$this->buildLink('activity-summary'),
			$section->title
		);
	}

	public function actionToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(ActivitySummarySectionFinder::class);
	}

	public function actionSort()
	{
		$activitySummaryRepo = $this->getActivitySummaryRepo();

		$sectionFinder = $activitySummaryRepo->findActivitySummarySectionsForList();
		$sections = $sectionFinder->fetch();

		if ($this->isPost())
		{
			$sortData = $this->filter('sections', 'json-array');

			/** @var SortPlugin $sorter */
			$sorter = $this->plugin(SortPlugin::class);
			$sorter->sortFlat($sortData, $sections);

			return $this->redirect($this->buildLink('activity-summary'));
		}
		else
		{
			$viewParams = [
				'sections' => $sections,
			];
			return $this->view('XF:ActivitySummary\SectionSort', 'activity_summary_section_sort', $viewParams);
		}
	}

	public function actionSendTestEmail()
	{
		$activitySummaryRepo = $this->getActivitySummaryRepo();

		$visitor = \XF::visitor();
		$sections = $activitySummaryRepo->findActivitySummarySectionsForDisplay()->fetch();

		if (!$sections->count() || !$visitor->email)
		{
			return $this->noPermission();
		}

		if ($this->isPost())
		{
			$id = $this->app->jobManager()->enqueueUnique('activitySummaryEmail', ActivitySummaryEmail::class, [
				'test_mode' => true, // skip checks in User::canReceiveActivitySummaryEmail
				'user_ids' => [\XF::visitor()->user_id],
				'section_ids' => $sections->keys(),
			]);

			return $this->redirect(
				$this->buildLink('tools/run-job', null, [
					'only_id' => $id,
					'_xfRedirect' => $this->buildLink('activity-summary', null, ['test_email_success' => 1]),
				])
			);
		}
		else
		{
			$viewParams = [];
			return $this->view('XF:ActivitySummary\SendTestEmail', 'activity_summary_send_test_email', $viewParams);
		}
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return ActivitySummarySection
	 */
	protected function assertSectionExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(ActivitySummarySection::class, $id, $with, $phraseKey);
	}

	/**
	 * @return ActivitySummaryRepository
	 */
	protected function getActivitySummaryRepo()
	{
		return $this->repository(ActivitySummaryRepository::class);
	}
}
