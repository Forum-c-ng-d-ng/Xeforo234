<?php

namespace XF\Option;

use XF\Entity\Option;
use XF\Repository\UserGroupRepository;

class UserGroup extends AbstractOption
{
	public static function renderSelect(Option $option, array $htmlParams)
	{
		$data = static::getSelectData($option, $htmlParams);

		return static::getTemplater()->formSelectRow(
			$data['controlOptions'],
			$data['choices'],
			$data['rowOptions']
		);
	}

	public static function renderSelectMultiple(Option $option, array $htmlParams)
	{
		$data = static::getSelectData($option, $htmlParams);
		$data['controlOptions']['multiple'] = true;
		$data['controlOptions']['size'] = 8;

		return static::getTemplater()->formSelectRow(
			$data['controlOptions'],
			$data['choices'],
			$data['rowOptions']
		);
	}

	protected static function getSelectData(Option $option, array $htmlParams)
	{
		/** @var UserGroupRepository $userGroupRepo */
		$userGroupRepo = \XF::repository(UserGroupRepository::class);

		$choices = $userGroupRepo->getUserGroupOptionsData(true, 'option');
		$choices = array_map(function ($v)
		{
			$v['label'] = \XF::escapeString($v['label']);
			return $v;
		}, $choices);

		return [
			'choices' => $choices,
			'controlOptions' => static::getControlOptions($option, $htmlParams),
			'rowOptions' => static::getRowOptions($option, $htmlParams),
		];
	}
}