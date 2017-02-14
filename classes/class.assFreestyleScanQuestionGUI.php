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
	 * @param ilTemplate $template
	 * @param $imagepath
	 */
	protected function renderImage(ilTemplate $template, $imagepath)
	{
		if(!file_exists($imagepath) || !is_file($imagepath))
		{
			return;
		}

		require_once 'Services/WebAccessChecker/classes/class.ilWACSignedPath.php';
		$template->setVariable('IMG_SRC', ilWACSignedPath::signFile($imagepath));
		$template->setVariable('IMG_ALT', $this->object->getTitle());
		$template->setVariable("IMG_TITLE", $this->object->getTitle());
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
			$this->writeQuestionSpecificPostData($this->form);
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
		if(isset($_POST['image_delete']) && (int)$_POST['image_delete'])
		{
			$this->object->deleteImage();
		}

		if(strlen($_FILES['image']['tmp_name']) > 0)
		{
			if($this->object->getSelfAssessmentEditingMode() && $this->object->getId() < 1)
			{
				$this->object->createNewQuestion();
			}
			$this->object->deleteImage();
			$this->object->setImageFilename($_FILES['image']['name'], $_FILES['image']['tmp_name']);
		}

		$this->object->setPoints(strlen($_POST['points']) > 0 ? (float)$_POST['points'] : '');
	}

	/**
	 * @inheritdoc
	 */
	public function getPreview($show_question_only = FALSE, $showInlineFeedback = false)
	{
		$template = $this->getOutputTemplate(self::$DEFAULT_PREVIEW_TEMPLATE);

		if(is_object($this->getPreviewSession()))
		{
			$files = $this->object->getPreviewFileUploads($this->getPreviewSession());
			require_once 'Modules/TestQuestionPool/classes/tables/class.assFileUploadFileTableGUI.php';
			$table_gui = new assFileUploadFileTableGUI(null , $this->getQuestionActionCmd(), 'ilAssQuestionPreview');
			$table_gui->setTitle($this->lng->txt('already_delivered_files'), 'icon_file.svg', $this->lng->txt('already_delivered_files'));
			$table_gui->setData($files);
			$table_gui->init();

			$template->setCurrentBlock('files');
			$template->setVariable('FILES', $table_gui->getHTML());
			$template->parseCurrentBlock();
		}

		$imagepath = $this->object->getImagePath() . $this->object->getImageFilename();

		$questiontext = $this->object->getQuestion();

		$template->setVariable('QUESTIONTEXT', $this->object->prepareTextareaOutput($questiontext, TRUE));

		$template->setVariable('CMD_UPLOAD', $this->getQuestionActionCmd());
		$template->setVariable('TEXT_UPLOAD', $this->object->prepareTextareaOutput($this->lng->txt('upload')));
		$template->setVariable('TXT_UPLOAD_FILE', $this->object->prepareTextareaOutput($this->lng->txt('file_add')));

		$this->renderImage($template, $imagepath);

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
		$output = '';
		return $this->object->prepareTextareaOutput($output, TRUE);
	}

	/**
	 * @inheritdoc
	 */
	public function getSolutionOutput($active_id, $pass = null, $graphicalOutput = false, $result_output = false, $show_question_only = true, $show_feedback = false, $show_correct_solution = false, $show_manual_scoring = false, $show_question_text = true)
	{
		$template = $this->getOutputTemplate(self::$SOLUTION_TEMPLATE);

		$solutionvalue = "";
		if (($active_id > 0) && (!$show_correct_solution))
		{
			$solutions = $this->object->getSolutionValues($active_id, $pass);
			require_once 'Modules/Test/classes/class.ilObjTest.php';
			if(!ilObjTest::_getUsePreviousAnswers($active_id, true))
			{
				if (is_null($pass)) $pass = ilObjTest::_getPass($active_id);
			}
			$solutions = $this->object->getSolutionValues($active_id, $pass);

			$files = ($show_manual_scoring) ? $this->object->getUploadedFilesForWeb($active_id, $pass) : $this->object->getUploadedFiles($active_id, $pass);
			include_once "./Modules/TestQuestionPool/classes/tables/class.assFileUploadFileTableGUI.php";
			$table_gui = new assFileUploadFileTableGUI($this->getTargetGuiClass(), 'gotoquestion');
			$table_gui->setTitle($this->lng->txt('already_delivered_files'), 'icon_file.svg', $this->lng->txt('already_delivered_files'));
			$table_gui->setData($files);
			$table_gui->init();
			$table_gui->setRowTemplate("tpl.il_as_qpl_fileupload_file_view_row.html", 'Modules/TestQuestionPool');
			$table_gui->setSelectAllCheckbox("");
			$table_gui->clearCommandButtons();
			$table_gui->disable('select_all');
			$table_gui->disable('numinfo');

			$template->setCurrentBlock('files');
			$template->setVariable('FILES', $table_gui->getHTML());
			$template->parseCurrentBlock();
		}

		$template->setVariable('CMD_UPLOAD', $this->getQuestionActionCmd());
		$template->setVariable('TEXT_UPLOAD', $this->object->prepareTextareaOutput($this->lng->txt('upload')));
		$template->setVariable('TXT_UPLOAD_FILE', $this->object->prepareTextareaOutput($this->lng->txt('file_add')));

		if (($active_id > 0) && (!$show_correct_solution))
		{
			$reached_points = $this->object->getReachedPoints($active_id, $pass);
			if ($graphicalOutput)
			{
				// output of ok/not ok icons for user entered solutions
				if ($reached_points == $this->object->getMaximumPoints())
				{
					$template->setCurrentBlock('icon_ok');
					$template->setVariable('ICON_OK', ilUtil::getImagePath('icon_ok.svg'));
					$template->setVariable('TEXT_OK', $this->lng->txt('answer_is_right'));
					$template->parseCurrentBlock();
				}
				else
				{
					$template->setCurrentBlock('icon_ok');
					if ($reached_points > 0)
					{
						$template->setVariable('ICON_NOT_OK', ilUtil::getImagePath('icon_mostly_ok.svg'));
						$template->setVariable('TEXT_NOT_OK', $this->lng->txt('answer_is_not_correct_but_positive'));
					}
					else
					{
						$template->setVariable('ICON_NOT_OK', ilUtil::getImagePath('icon_not_ok.svg'));
						$template->setVariable('TEXT_NOT_OK', $this->lng->txt('answer_is_wrong'));
					}
					$template->parseCurrentBlock();
				}
			}
		}
		else
		{
			$reached_points = $this->object->getPoints();
		}

		if($result_output)
		{
			$resulttext = ($reached_points == 1) ? "(%s " . $this->lng->txt("point") . ")" : "(%s " . $this->lng->txt("points") . ")";
			$template->setVariable("RESULT_OUTPUT", sprintf($resulttext, $reached_points));
		}
		if($show_question_text == true)
		{
			$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($this->object->getQuestion(), TRUE));
		}

		$this->renderImage($template, $this->object->getImagePath() . $this->object->getImageFilename());

		$questionoutput   = $template->get();
		$solutiontemplate = new ilTemplate("tpl.il_as_tst_solution_output.html", TRUE, TRUE, "Modules/TestQuestionPool");
		$feedback         = ($show_feedback && !$this->isTestPresentationContext()) ? $this->getAnswerFeedbackOutput($active_id, $pass) : "";
		if(strlen($feedback)) $solutiontemplate->setVariable("FEEDBACK", $feedback);
		$solutiontemplate->setVariable("SOLUTION_OUTPUT", $questionoutput);
		$solutionoutput = $solutiontemplate->get();
		if(!$show_question_only)
		{
			// get page object output
			$solutionoutput = $this->getILIASPage($solutionoutput);
		}
		return $solutionoutput;
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
		$template = $this->getOutputTemplate(self::$DEFAULT_PREVIEW_TEMPLATE);

		if($active_id)
		{
			$solutions = NULL;
			require_once 'Modules/Test/classes/class.ilObjTest.php';
			if(!ilObjTest::_getUsePreviousAnswers($active_id, true))
			{
				if(is_null($pass)) $pass = ilObjTest::_getPass($active_id);
			}

			// $files = $this->object->getUploadedFiles($active_id, $pass); // does not prefer intermediate but orders tstamp
			$files = $this->object->getUserSolutionPreferingIntermediate($active_id, $pass);
			include_once "./Modules/TestQuestionPool/classes/tables/class.assFileUploadFileTableGUI.php";
			$table_gui = new assFileUploadFileTableGUI(null, $this->getQuestionActionCmd());
			$table_gui->setTitle($this->lng->txt('already_delivered_files'), 'icon_file.svg', $this->lng->txt('already_delivered_files'));
			$table_gui->setData($files);
			$table_gui->init();

			$template->setCurrentBlock('files');
			$template->setVariable('FILES', $table_gui->getHTML());
			$template->parseCurrentBlock();
		}

		$template->setVariable('QUESTIONTEXT', $this->object->prepareTextareaOutput($this->object->question, TRUE));
		$template->setVariable('CMD_UPLOAD', $this->getQuestionActionCmd());
		$template->setVariable('TEXT_UPLOAD', $this->object->prepareTextareaOutput($this->lng->txt('upload')));

		$this->renderImage($template, $this->object->getImagePath() . $this->object->getImageFilename());

		$questionoutput = $template->get();
		if(!$show_question_only)
		{
			// get page object output
			$questionoutput = $this->getILIASPage($questionoutput);
		}
		$pageoutput = $this->outQuestionPage("", $is_postponed, $active_id, $questionoutput);

		return $pageoutput;
	}

	/**
	 * Get the output of the delivered template
	 *
	 * @param string $tpl_name
	 *
	 * @return string
	 */
	private function getOutputTemplate($tpl_name)
	{
		$tpl = $this->plugin->getTemplate($tpl_name);

		$tpl->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput(
			$this->object->getQuestion(), true
		));

		$tpl->setVariable("SRC_IMAGE", $this->object->getImagePathWeb() . $this->object->getImagePath());

		return $tpl;
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
