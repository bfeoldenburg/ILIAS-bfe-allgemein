<?php

/* Copyright (c) 1998-2017 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Calendar agenda list
 *
 * @author Alex Killing <killing@leifos.de>
 * @ingroup ServicesCalendar
 * @ilCtrl_Calls ilCalendarAgendaListGUI: ilCalendarAppointmentPresentationGUI
 */
class ilCalendarAgendaListGUI
{
	const PERIOD_DAY = 1;
	const PERIOD_WEEK = 2;
	const PERIOD_MONTH = 3;
	const PERIOD_HALF_YEAR = 4;

	/**
	 * @var ilCtrl
	 */
	protected $ctrl;

	/**
	 * @var \ILIAS\UI\Factory
	 */
	protected $ui_fac;

	/**
	 * @var \ILIAS\UI\Renderer
	 */
	protected $ui_ren;

	/**
	 * @var ilObjUser
	 */
	protected $user;

	/**
	 * @var ilLanguage
	 */
	protected $lng;

	/**
	 * @var int
	 */
	protected $period = self::PERIOD_WEEK;

	/**
	 * @var string
	 */
	protected $seed;

	/**
	 * Constructor
	 */
	function __construct()
	{
		global $DIC;

		$this->ctrl = $DIC->ctrl();
		$this->ui_fac = $DIC->ui()->factory();
		$this->ui_ren = $DIC->ui()->renderer();
		$this->user = $DIC->user();
		$this->lng = $DIC->language();

		$this->ctrl->saveParameter($this, "cal_agenda_per");


		//$qp = $DIC->http()->request()->getQueryParams();
		$qp = $_GET;
		if ((int) $qp["cal_agenda_per"] > 0 && (int) $qp["cal_agenda_per"] <= 4)
		{
			$this->period = $qp["cal_agenda_per"];
		}

		$this->seed = $qp["seed"];
		$end_date = new ilDate($this->seed." 00:00:00", IL_CAL_DATETIME);
		switch ($this->period)
		{
			case self::PERIOD_DAY:
				$end_date->increment(IL_CAL_DAY, 1);
				break;

			case self::PERIOD_WEEK:
				$end_date->increment(IL_CAL_WEEK, 1);
				break;

			case self::PERIOD_MONTH:
				$end_date->increment(IL_CAL_MONTH, 1);
				break;

			case self::PERIOD_HALF_YEAR:
				$end_date->increment(IL_CAL_MONTH, 6);
				break;
		}
		$this->period_end_day = $end_date->get(IL_CAL_DATE);
	}

	/**
	 * Execute command
	 */
	function executeCommand()
	{
		$next_class = $this->ctrl->getNextClass($this);
		$cmd = $this->ctrl->getCmd("getHTML");

		switch ($next_class)
		{
			default:
				if (in_array($cmd, array("getHTML", "getModalForApp")))
				{
					return $this->$cmd();
				}
		}
	}

	/**
	 * Get output
	 *
	 * @param
	 * @return
	 */
	function getHTML()
	{
		$navigation = new ilCalendarHeaderNavigationGUI($this,new ilDate($this->seed, IL_CAL_DATE),ilDateTime::DAY);
		$navigation->getHTML();

		// get events
		$events = $this->getEvents();
		$events = ilUtil::sortArray($events, "dstart", "asc", true);

		$df = new \ILIAS\Data\Factory();
		$items = array();
		$groups = array();
		$modals = array();
		$cday = "";
		foreach ($events as $e)
		{
			$begin = new ilDatetime($e['dstart'],IL_CAL_UNIX);
			$end = new ilDatetime($e['dend'],IL_CAL_UNIX);
			$day = ilDatePresentation::formatDate(new ilDate($e['dstart'],IL_CAL_UNIX), false, true);

			// new group starts
			if ($cday != $day)
			{
				// terminate preceding group
				if ($cday != "")
				{
					$groups[] = $this->ui_fac->item()->group($cday, $items);
				}
				$cday = $day;
				$items = array();
			}

			// get calendar
			$cat_id = ilCalendarCategoryAssignments::_lookupCategory($e["event"]->getEntryId());
			$cat_info = ilCalendarCategories::_getInstance()->getCategoryInfo($cat_id);

			$properties = array();

			// properties: origin
			$title = $cat_info["title"];
			if ($cat_info['type'] == ilCalendarCategory::TYPE_OBJ)
			{
				//$type = ilObject::_lookupType($cat_info['obj_id']);
				$refs = ilObject::_getAllReferences($cat_info['obj_id']);
				include_once('./Services/Link/classes/class.ilLink.php');
				$href = ilLink::_getStaticLink(current($refs),ilObject::_lookupType($cat_info['obj_id']),true);
				$title = $this->ui_fac->button()->shy($title, $href);

			}
			$properties[$this->lng->txt('cal_origin')] = $title;

			// properties: last update

			$update = new ilDateTime($e["event"]->getLastUpdate()->get(IL_CAL_UNIX), IL_CAL_UNIX, $this->user->getTimeZone());
			$properties[$this->lng->txt('last_update')] = ilDatePresentation::formatDate($update);

			// properties: location
			if ($e["event"]->getLocation() != "")
			{
				$properties[$this->lng->txt('location')] = $e["event"]->getLocation();
			}

			// shy button for title
			$this->ctrl->setParameter($this, 'app_id', $e["event"]->getEntryId());
			$this->ctrl->setParameter($this, 'seed', $this->seed);
			$url = $this->ctrl->getLinkTarget($this, "getModalForApp", "", true, false);
			$this->ctrl->setParameter($this, "app_id", $_GET["app_id"]);
			$modal = $this->ui_fac->modal()->roundtrip('', [])->withAsyncRenderUrl($url);
			//$modal = $this->ui_fac->modal()->roundtrip('test', $this->ui_fac->legacy("Hello World."));
			$shy = $this->ui_fac->button()->shy($e["event"]->getPresentationTitle(), "")->withOnClick($modal->getShowSignal());
			$modals[] = $modal;

			/*$this->ctrl->setParameterByClass('ilcalendarappointmentgui', 'app_id', $e["event"]->getEntryId());
			$shy = $this->ui_fac->button()->shy($e["event"]->getPresentationTitle(),
				$this->ctrl->getLinkTargetByClass(array('ilcalendarinboxgui', 'ilcalendarappointmentgui'),'edit'));
			$this->ctrl->setParameterByClass('ilcalendarappointmentgui', 'app_id', "");*/

			$li = $this->ui_fac->item()->standard($shy)
				->withDescription("".$e["event"]->getDescription())
				->withLeadText(ilDatePresentation::formatPeriod($begin, $end, true))
				->withProperties($properties)
				->withColor($df->color('#'.$cat_info["color"]));

			// add type specific actions/properties
			include_once("./Services/Calendar/classes/class.ilCalendarAppointmentPresentationGUI.php");
			$app_gui = ilCalendarAppointmentPresentationGUI::_getInstance(new ilDate($this->seed, IL_CAL_DATE), $e);
			$app_gui->setListItemMode($li);
			$this->ctrl->getHTML($app_gui);
			$items[] = $app_gui->getListItem();

		}
		// terminate last group
		if ($cday != "")
		{
			$groups[] = $this->ui_fac->item()->group($cday, $items);
		}

		// list actions
		$items = array();
		$this->ctrl->setParameter($this, "cal_agenda_per", self::PERIOD_DAY);
		$items[] = $this->ui_fac->button()->shy("1 ".$this->lng->txt("day"), $this->ctrl->getLinkTarget($this, "getHTML"));
		$this->ctrl->setParameter($this, "cal_agenda_per", self::PERIOD_WEEK);
		$items[] = $this->ui_fac->button()->shy("1 ".$this->lng->txt("week"), $this->ctrl->getLinkTarget($this, "getHTML"));
		$this->ctrl->setParameter($this, "cal_agenda_per", self::PERIOD_MONTH);
		$items[] = $this->ui_fac->button()->shy("1 ".$this->lng->txt("month"), $this->ctrl->getLinkTarget($this, "getHTML"));
		$this->ctrl->setParameter($this, "cal_agenda_per", self::PERIOD_HALF_YEAR);
		$items[] = $this->ui_fac->button()->shy("6 ".$this->lng->txt("months"), $this->ctrl->getLinkTarget($this, "getHTML"));
		$this->ctrl->setParameter($this, "cal_agenda_per", $this->period);

		$actions = $this->ui_fac->dropdown()->standard($items)->withLabel($this->lng->txt("days"));

		$list_title = $this->lng->txt("cal_agenda").": ".ilDatePresentation::formatDate(new ilDate($this->seed, IL_CAL_DATE));
		if ($this->period != self::PERIOD_DAY)
		{
			$list_title.= " - ".ilDatePresentation::formatDate(new ilDate($this->period_end_day, IL_CAL_DATE));
		}

		$list = $this->ui_fac->panel()->listing()->standard($list_title, $groups)
			->withActions($actions);


		$comps = array_merge($modals, array($list));

		return $this->ui_ren->render($comps);

	}

	/**
	 * Get events
	 *
	 * @param
	 * @return
	 */
	function getEvents()
	{
		$schedule = new ilCalendarSchedule(new ilDate(time(),IL_CAL_UNIX),ilCalendarSchedule::TYPE_PD_UPCOMING);
		$schedule->setPeriod(new ilDate($this->seed, IL_CAL_DATE),
			new ilDate($this->period_end_day, IL_CAL_DATE));
		$schedule->addSubitemCalendars(true);
		$schedule->calculate();
		return $schedule->getScheduledEvents();
	}

	/**
	 * Get start/end date for item
	 *
	 * @param array $item item
	 * @return array
	 */
	function getDatesForItem($item)
	{
		$start = $item["dstart"];
		$end = $item["dend"];
		if($item["fullday"])
		{
			$start = new ilDate($start, IL_CAL_UNIX);
			$end = new ilDate($end, IL_CAL_UNIX);
		}
		else
		{
			$start = new ilDateTime($start, IL_CAL_UNIX);
			$end = new ilDateTime($end, IL_CAL_UNIX);
		}
		return array("start" => $start, "end" => $end);
	}

	/**
	 * Get modal for appointment (see similar code in ilCalendarBlockGUI)
	 */
	function getModalForApp()
	{
		$ilCtrl = $this->ctrl;

		$f = $this->ui_fac;
		$r = $this->ui_ren;

		// @todo: this needs optimization
		$events = $this->getEvents();
		foreach ($events as $item)
		{
			if ($item["event"]->getEntryId() == (int) $_GET["app_id"])
			{
				$dates = $this->getDatesForItem($item);

				// content of modal
				include_once("./Services/Calendar/classes/class.ilCalendarAppointmentPresentationGUI.php");
				$next_gui = ilCalendarAppointmentPresentationGUI::_getInstance(new ilDate($this->seed, IL_CAL_DATE), $item);
				$content = $ilCtrl->getHTML($next_gui);

				$modal = $f->modal()->roundtrip(ilDatePresentation::formatPeriod($dates["start"], $dates["end"]),$f->legacy($content));
				echo $r->renderAsync($modal);
			}
		}
		exit();
	}

}

?>