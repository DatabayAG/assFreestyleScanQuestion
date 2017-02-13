<?php
/* Copyright (c) 1998-2017 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Modules/TestQuestionPool/classes/class.assQuestionGUI.php';
require_once './Modules/TestQuestionPool/interfaces/interface.ilGuiQuestionScoringAdjustable.php';

/**
 * @ilCtrl_isCalledBy assFreestyleScanQuestionGUI: ilObjQuestionPoolGUI, ilObjTestGUI, ilTestOutputGUI
 */
class assFreestyleScanQuestionGUI extends assQuestionGUI implements ilGuiQuestionScoringAdjustable
{
	private static $DEFAULT_PREVIEW_TEMPLATE = "default/tpl.il_as_qpl_fssqst_output.html";
	private static $SOLUTION_TEMPLATE        = "default/tpl.il_as_qpl_fssqst_output_solution.html";

	/**
	 * The ilPropertyFormGui representing the assGraphicalAssignmentQuestion
	 *
	 * @var ilPropertyFormGUI
	 */
	private $form;

    /**
	 * The ilPlugin object representing the assGraphicalAssignmentQuestion
	 *
	 * @var ilPlugin|null|assFreestyleScanQuestion
	 */
	public $plugin = null;

	/**
	 * @param int $id
	 */
	public function __construct($id = -1)
	{
		parent::__construct();
		$this->object = new assFreestyleScanQuestion();
		$this->plugin = $this->object->getPlugin();
		if($id > 0)
		{
			$this->object->loadFromDb($id);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function editQuestion($checkonly = FALSE)
	{
		$save = $this->isSaveCommand();
		$this->initializeEditTemplate();

		$this->initQuestionForm();

		$errors = false;

		if ($save)
		{
			$this->form->setValuesByPost();
			$errors = !$this->form->checkInput();
			$this->form->setValuesByPost(); // again, because checkInput now performs the whole stripSlashes handling and 
			// $this->form need this if we don't want to have duplication of backslashes
			if ($errors) $checkonly = false;
		}

		if (!$checkonly) $this->tpl->setVariable("QUESTION_DATA", $this->form->getHTML());
		return $errors;
	}

	/**
	 * @inheritdoc
	 */
	public function writePostData($always = false)
	{
		$hasErrors = (!$always) ? $this->editQuestion(true) : false;
		if(!$hasErrors)
		{
			require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
			$this->writeQuestionGenericPostData();
			$this->writeQuestionSpecificPostData(new ilPropertyFormGUI());
			$this->saveTaxonomyAssignments();
			return 0;
		}
		return 1;
	}

	/**
	 * @inheritdoc
	 */
	public function writeQuestionSpecificPostData(ilPropertyFormGUI $form)
	{
		if($this->ctrl->getCmd() == 'deleteImage')
		{
			/// @todo: Delete
			$this->object->deleteImage();
		}
		else
		{
			if(strlen($_FILES['image']['tmp_name']) == 0)
			{
				$this->object->setImageFilename($_POST['image_name']);
			}
		}

		if(strlen($_FILES['image']['tmp_name']))
		{
			if($this->object->getSelfAssessmentEditingMode() && $this->object->getId() < 1)
			{
				$this->object->createNewQuestion();
			}
			$this->object->setImageFilename($_FILES['image']['name'], $_FILES['image']['tmp_name']);
		}

		$this->object->setPoints(strlen($_POST['points']) > 0 ? (float)$_POST['points'] : '');
	}

	/**
	 * @inheritdoc
	 */
	public function getPreview($show_question_only = FALSE, $showInlineFeedback = false)
	{
		$imagepath = $this->object->getImagePath() . $this->object->getImageFilename();

		$template = $this->getOutputTemplate(self::$DEFAULT_PREVIEW_TEMPLATE);

		$questiontext = $this->object->getQuestion();

		$template->setVariable('QUESTIONTEXT', $this->object->prepareTextareaOutput($questiontext, TRUE));

		require_once  'Services/WebAccessChecker/classes/class.ilWACSignedPath.php';
		$template->setVariable('IMG_SRC', ilWACSignedPath::signFile($imagepath));
		$template->setVariable('IMG_ALT', $this->object->getTitle());
		$template->setVariable("IMG_TITLE", $this->object->getTitle());

		$questionoutput = $template->get();
		if (!$show_question_only)
		{
			// get page object output
			$questionoutput = $this->getILIASPage($questionoutput);
		}
		return $questionoutput;
	}

	/**
	 * @inheritdoc
	 */
	function getSpecificFeedbackOutput($active_id, $pass)
	{
		$output = "";
		return $this->object->prepareTextareaOutput($output, TRUE);
	}

	/**
	 * @inheritdoc
	 */
	public function getSolutionOutput($active_id, $pass = null, $graphicalOutput = false, $result_output = false, $show_question_only = true, $show_feedback = false, $show_correct_solution = false, $show_manual_scoring = false, $show_question_text = true)
	{
		// get the solution of the user for the active pass or from the last pass if allowed
		$user_solutions = array();
		if (($active_id > 0) && (!$show_correct_solution))
		{
			$solutions =& $this->object->getSolutionValues($active_id, $pass);
			foreach ($solutions as $idx => $solution_value)
			{
				$user_solutions[] = $solution_value["value2"];
			}
		}
		else
		{
			$best_solutions = array();
		}


		// generate the question output
		#include_once "./classes/class.ilTemplate.php";
		$solution_template = new ilTemplate("tpl.il_as_tst_solution_output.html",true, true, "Modules/TestQuestionPool");
		$template = $this->getOutputTemplate(self::$SOLUTION_TEMPLATE, $user_solutions);

		if (($active_id > 0) && (!$show_correct_solution))
		{
			if ($graphicalOutput)
			{
				// output of ok/not ok icons for user entered solutions
				$reached_points = $this->object->getReachedPoints($active_id, $pass);
				if ($reached_points == $this->object->getMaximumPoints())
				{
					$template->setCurrentBlock("icon_ok");
					$template->setVariable("ICON_OK", ilUtil::getImagePath("icon_ok.svg"));
					$template->setVariable("TEXT_OK", $this->lng->txt("answer_is_right"));
					$template->parseCurrentBlock();
				}
				else
				{
					$template->setCurrentBlock("icon_ok");
					if ($reached_points > 0)
					{
						$template->setVariable("ICON_NOT_OK", ilUtil::getImagePath("icon_mostly_ok.svg"));
						$template->setVariable("TEXT_NOT_OK", $this->lng->txt("answer_is_not_correct_but_positive"));
					}
					else
					{
						$template->setVariable("ICON_NOT_OK", ilUtil::getImagePath("icon_not_ok.svg"));
						$template->setVariable("TEXT_NOT_OK", $this->lng->txt("answer_is_wrong"));
					}
					$template->parseCurrentBlock();
				}
			}
		}
		$template->setVariable("QUESTIONTEXT", $this->object->getQuestion());
		$template->setVariable("ID_COUNTER", uniqid());

		$feedback = ($show_feedback) ? $this->getAnswerFeedbackOutput($active_id, $pass) : "";

		if (strlen($feedback)) $solution_template->setVariable("FEEDBACK", $feedback);

		$solution_template->setVariable("SOLUTION_OUTPUT", $template->get());

		$output = $solution_template->get();

		if (!$show_question_only)
		{
			// get page object output
			$output = $this->getILIASPage($output);
		}

		return $output;

	}

	/**
	 * @inheritdoc
	 */
	public function setQuestionTabs()
	{
		global $rbacsystem, $ilTabs;

		$this->ctrl->setParameterByClass("ilAssQuestionPageGUI", "q_id", $_GET['q_id']);

		$classname = ilassFreestyleScanQuestionPlugin::getName() . 'GUI';
		$this->ctrl->setParameterByClass(strtolower($classname), "sel_question_types", ilassFreestyleScanQuestionPlugin::getName());
		$this->ctrl->setParameterByClass(strtolower($classname), "q_id", $_GET['q_id']);

		if($_GET['q_id'])
		{
			if($rbacsystem->checkAccess("write", $_GET['ref_id']))
			{
				$ilTabs->addTarget("edit_page",
					$this->ctrl->getLinkTargetByClass("ilAssQuestionPageGUI", "edit"),
					array("edit", "insert", "exec_pg"),
					"", "", false);
			}

			$this->addTab_QuestionPreview($ilTabs);
		}

		if($rbacsystem->checkAccess("write", $_GET['ref_id']))
		{
			$url = $this->ctrl->getLinkTargetByClass($classname, "editQuestion");
			$ilTabs->addTarget("edit_question",
				$url,
				array("editQuestion", "save", "cancel", "saveEdit"),
				$classname, "", false
			);
		}

		// add tab for question feedback within common class assQuestionGUI
		$this->addTab_QuestionFeedback($ilTabs);

		// add tab for question hint within common class assQuestionGUI
		$this->addTab_QuestionHints($ilTabs);

		// add tab for question's suggested solution within common class assQuestionGUI
		$this->addTab_SuggestedSolution($ilTabs, $classname);

		// Assessment of questions sub menu entry
		if ($_GET["q_id"])
		{
			$ilTabs->addTarget("statistics",
				$this->ctrl->getLinkTargetByClass($classname, "assessment"),
				array("assessment"),
				$classname, "");
		}

		if (($_GET["calling_test"] > 0) || ($_GET["test_ref_id"] > 0))
		{
			$ref_id = $_GET["calling_test"];
			if (strlen($ref_id) == 0) $ref_id = $_GET["test_ref_id"];

			global $___test_express_mode;

			if (!$_GET['test_express_mode'] && !$___test_express_mode) {
				$ilTabs->setBackTarget($this->lng->txt("backtocallingtest"), "ilias.php?baseClass=ilObjTestGUI&cmd=questions&ref_id=$ref_id");
			}
			else {
				$link = ilTestExpressPage::getReturnToPageLink();
				$ilTabs->setBackTarget($this->lng->txt("backtocallingtest"), $link);
			}
		}
		else
		{
			$ilTabs->setBackTarget($this->lng->txt("qpl"), $this->ctrl->getLinkTargetByClass("ilobjquestionpoolgui", "questions"));
		}
	}

	/**
	 * Initialize the form representing the configuration GUI
	 */
	private function initQuestionForm()
	{
		include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setTitle($this->plugin->txt(ilassFreestyleScanQuestionPlugin::getName()));
		$form->setMultipart(true);
		$form->setTableWidth("100%");
		$form->setId(ilassFreestyleScanQuestionPlugin::getPluginId());

		$this->addBasicQuestionFormProperties($form);
		$this->populateQuestionSpecificFormPart($form);

		$this->populateTaxonomyFormSection($form);
		$this->addQuestionFormCommandButtons($form);

		if($this->object->getId())
		{
			$hidden = new ilHiddenInputGUI("", "ID");
			$hidden->setValue($this->object->getId());
			$form->addItem($hidden);
		}

		$this->form = $form;
	}

	/**
	 * @inheritdoc
	 */
	public function populateQuestionSpecificFormPart(ilPropertyFormGUI $form)
	{

		// points
		$points = new ilNumberInputGUI($this->lng->txt('points'), 'points');
		$points->allowDecimals(true);
		$points->setValue(
			is_numeric($this->object->getPoints()) && $this->object->getPoints() >= 0 ? $this->object->getPoints() : ''
		);
		$points->setRequired(true);
		$points->setSize(3);
		$points->setMinValue(0.0);
		$points->setMinvalueShouldBeGreater(false);
		$form->addItem($points);

		$image = new ilImageFileInputGUI($this->lng->txt('image'), 'image');
		$image->setRequired( true );
		if(strlen($this->object->getImageFilename()))
		{
			$image->setImage($this->object->getImagePathWeb() . $this->object->getImageFilename());
		}
		$form->addItem( $image );
	}

	/**
	 * Initialize the assGraphicalAssignmentQuestion template for the PropertyFormGUI
	 * and insert the form html into the template
	 */
	private function initializeEditTemplate()
	{
		$this->getQuestionTemplate();
	}

	/**
	 * Get the html output of the assGraphicalAssignmentQuestion for test
	 *
	 * @param int $active_id
	 * @param null|int $pass
	 * @param bool $is_postponed
	 * @param bool $use_post_solutions
	 * @param bool $show_feedback
	 *
	 * @return mixed
	 */
	public function getTestOutput($active_id, $pass = null, $is_postponed = false, $use_post_solutions = false, $show_feedback = false)
	{
		// get the solution of the user for the active pass or from the last pass if allowed
		$user_solution = array();
		if($active_id)
		{
			require_once './Modules/Test/classes/class.ilObjTest.php';
			if(!ilObjTest::_getUsePreviousAnswers($active_id, true))
			{
				if (is_null($pass)) $pass = ilObjTest::_getPass($active_id);
			}

			$solutions = $this->object->getUserSolutionPreferingIntermediate($active_id, $pass);
			if(is_array($solutions))
			{
				foreach($solutions as $solution)
				{
					$user_solution[$solution['value1']] = $solution['value2'];
				}
			}
		}

		$tpl = $this->getOutputTemplate(self::$DEFAULT_PREVIEW_TEMPLATE, $user_solution, true);
		$output = $tpl->get();

		return $this->outQuestionPage("", $is_postponed, $active_id, $output);
	}

	/**
	 * Get the output of the delivered template
	 *
	 * @param string $tpl_name
	 *
	 * @return string
	 */
	private function getOutputTemplate($tpl_name, $solutions = array(), $for_test = false)
	{
		$tpl = $this->plugin->getTemplate($tpl_name);

		$tpl->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput(
			$this->object->getQuestion(), true
		));

		$tpl->setVariable("SRC_IMAGE", $this->object->getImagePathWeb() . $this->object->getImagePath());

		return $tpl;
	}

	/**
	 * Handles the file input for ilassFreestyleScanQuestionPlugin
	 *
	 * @param array $input
	 */
	private function handleFileUpload($input)
	{
		if(strlen($input['tmp_name'])) {
			$this->object->setImage($input['name'], $input['tmp_name']);
		} else {
			$this->object->setImage($input['name']);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getAfterParticipationSuppressionQuestionPostVars()
	{
		return array();
	}

	/**
	 * @inheritdoc
	 */
	public function getAggregatedAnswersView($relevant_answers)
	{
		return '';
	}

	/**
	 * @inheritdoc
	 */
	public function getFormEncodingType()
	{
		return self::FORM_ENCODING_MULTIPART;
	}
}
