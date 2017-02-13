<?php
/* Copyright (c) 1998-2017 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Modules/TestQuestionPool/classes/class.assQuestion.php';
require_once 'Modules/TestQuestionPool/interfaces/interface.ilObjQuestionScoringAdjustable.php';
require_once 'Modules/TestQuestionPool/interfaces/interface.ilObjFileHandlingQuestionType.php';

/**
 * Class assFreestyleScanQuestion
 */
class assFreestyleScanQuestion extends assQuestion implements ilObjQuestionScoringAdjustable, ilObjFileHandlingQuestionType
{
	/** @var ilPlugin Reference of the plugin object*/
	protected $plugin;

	/** @var string The image file containing the name of image file. */
	protected $image_filename;

	/**
	 * The constructor takes possible arguments and creates an instance of the assFreestyleScanQuestion object
	 *
	 * @param string $title A title string to name the question
	 * @param string $comment A comment string to describe the question
	 * @param string $author A string containing the name of the author
	 * @param int $owner A numerical ID to identify the owner/creator
	 * @param string $question The question string of the assGraphicalAssignmentQuestion question
	 * @param string $image_filename A string containing the name(path) of the image
	 * @access public
	 * @see assQuestion::__construct()
	 */
	function __construct(
		$title = "",
		$comment = "",
		$author = "",
		$owner = -1,
		$question = "",
		$image_filename = ""
	) {
		parent::__construct($title, $comment, $author, $owner, $question);

		$this->image_filename = $image_filename;

		$this->getPlugin();
	}

	/**
	 * Singleton for initializing the Plugin
	 *
	 * @return ilPlugin The plugin object
	 * @access public
	 */
	public function getPlugin()
	{
		if ($this->plugin == null)
		{
			include_once "./Services/Component/classes/class.ilPlugin.php";
			$this->plugin = ilPlugin::getPluginObject(IL_COMP_MODULE, "TestQuestionPool", "qst", ilassFreestyleScanQuestionPlugin::getName());
		}
		return $this->plugin;
	}

	/**
	 * @inheritdoc
	 */
	public function isComplete()
	{
		if (
			strlen($this->title)
			&& ($this->author)
			&& ($this->question)
			&& ($this->image_filename)
			&& ($this->getMaximumPoints() >= 0)
			&& is_numeric($this->getMaximumPoints()))
		{
			return true;
		}
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public function saveToDb($original_id = "")
	{
		$this->saveQuestionDataToDb($original_id);
		$this->saveAdditionalQuestionDataToDb();
		parent::saveToDb();
	}

	/**
	 * @inheritdoc
	 */
	public function saveAdditionalQuestionDataToDb()
	{
		global $ilDB;

		$ilDB->manipulateF( "DELETE FROM " . $this->getAdditionalTableName() . " WHERE question_fi = %s",
			array( "integer" ),
			array( $this->getId() )
		);

		$ilDB->manipulateF( "INSERT INTO " . $this->getAdditionalTableName(
			) . " (question_fi, image_file) VALUES (%s, %s)",
			array("integer", "text"),
			array(
				$this->getId(),
				$this->image_filename
			)
		);
	}

	/**
	 * Binds data form array to assGraphicalAssignmentQuestion
	 *
	 * @param array $data
	 */
	private function bindData($data)
	{
		$this->setId($data['question_id']);
		$this->setTitle($data['title']);
		$this->setComment($data['description']);
		$this->setNrOfTries($data['nr_of_tries']);
		$this->setSuggestedSolution($data['solution_hint']);
		$this->setOriginalId($data['original_id']);
		$this->setObjId($data['obj_fi']);
		$this->setAuthor($data['author']);
		$this->setOwner($data['owner']);
		$this->setPoints($data['points']);
		$this->setImageFilename($data['image_file']);

		require_once 'Services/RTE/classes/class.ilRTE.php';
		$this->setQuestion(ilRTE::_replaceMediaObjectImageSrc($data["question_text"], 1));
		$this->setEstimatedWorkingTime(substr($data["working_time"], 0, 2), substr($data["working_time"], 3, 2), substr($data["working_time"], 6, 2));

		try
		{
			$this->setAdditionalContentEditingMode($data['add_cont_edit_mode']);
		}
		catch(ilTestQuestionPoolException $e)
		{
		}
	}

	/**
	 * @return string
	 */
	public function getImageFilename()
	{
		return $this->image_filename;
	}

	/**
	 * @param string $image_filename
	 * @param string $image_tempfilename
	 */
	public function setImageFilename($image_filename, $image_tempfilename = '')
	{
		if(!empty($image_filename))
		{
			$image_filename       = str_replace(" ", "_", $image_filename);
			$this->image_filename = $image_filename;
		}

		if(!empty($image_tempfilename))
		{
			$imagepath = $this->getImagePath();
			if(!file_exists($imagepath))
			{
				ilUtil::makeDirParents($imagepath);
			}
			if(!ilUtil::moveUploadedFile($image_tempfilename, $image_filename, $imagepath . $image_filename))
			{
				$this->ilias->raiseError("The image could not be uploaded!", $this->ilias->error_obj->MESSAGE);
			}
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getMaximumPoints()
	{
		return $this->getPoints();
	}

	/**
	 * @inheritdoc
	 */
	public function loadFromDb($question_id)
	{
		global $ilDB;

		$result = $ilDB->queryF(
			"SELECT qpl_questions.*, {$this->getAdditionalTableName()}.* FROM qpl_questions LEFT JOIN {$this->getAdditionalTableName()} ON {$this->getAdditionalTableName()}.question_fi = qpl_questions.question_id WHERE question_id = %s",
			array('integer'),
			array($question_id)
		);
		if($result->numRows() == 1)
		{
			$data = $ilDB->fetchAssoc($result);
			$this->bindData($data);
		}

		parent::loadFromDb($question_id);
	}

	/**
	 * @inheritdoc
	 */
	public function duplicate($for_test = true, $title = "", $author = "", $owner = "", $testObjId = null)
	{
		if ($this->id <= 0)
		{
			// The question has not been saved. It cannot be duplicated
			return;
		}
		// duplicate the question in database
		$this_id = $this->getId();
		$thisObjId = $this->getObjId();

		$clone = $this;
		include_once ("./Modules/TestQuestionPool/classes/class.assQuestion.php");
		$original_id = assQuestion::_getOriginalId($this->id);
		$clone->id = -1;

		if( (int)$testObjId > 0 )
		{
			$clone->setObjId($testObjId);
		}

		if ($title)
		{
			$clone->setTitle($title);
		}

		if ($author)
		{
			$clone->setAuthor($author);
		}
		if ($owner)
		{
			$clone->setOwner($owner);
		}

		if ($for_test)
		{
			$clone->saveToDb($original_id);
		}
		else
		{
			$clone->saveToDb();
		}

		// copy question page content
		$clone->copyPageOfQuestion($this_id);
		// copy XHTML media objects
		$clone->copyXHTMLMediaObjectsOfQuestion($this_id);
		// duplicate the image
		$clone->duplicateImage($this_id, $thisObjId);

		$clone->onDuplicate($thisObjId, $this_id, $clone->getObjId(), $clone->getId());

		return $clone->id;
	}

	/**
	 * Copies an assFileUpload object
	 */
	public function copyObject($target_questionpool_id, $title = "")
	{
		if ($this->id <= 0)
		{
			// The question has not been saved. It cannot be duplicated
			return;
		}
		// duplicate the question in database
		$clone = $this;
		include_once ("./Modules/TestQuestionPool/classes/class.assQuestion.php");
		$original_id = assQuestion::_getOriginalId($this->id);
		$clone->id = -1;
		$source_questionpool_id = $this->getObjId();
		$clone->setObjId($target_questionpool_id);
		if ($title)
		{
			$clone->setTitle($title);
		}
		$clone->saveToDb();

		// copy question page content
		$clone->copyPageOfQuestion($original_id);
		// copy XHTML media objects
		$clone->copyXHTMLMediaObjectsOfQuestion($original_id);
		// duplicate the image
		$clone->copyImage($original_id, $source_questionpool_id);

		$clone->onCopy($source_questionpool_id, $original_id, $clone->getObjId(), $clone->getId());

		return $clone->id;
	}

	public function createNewOriginalFromThisDuplicate($targetParentId, $targetQuestionTitle = "")
	{
		if ($this->id <= 0)
		{
			// The question has not been saved. It cannot be duplicated
			return;
		}

		include_once ("./Modules/TestQuestionPool/classes/class.assQuestion.php");

		$sourceQuestionId = $this->id;
		$sourceParentId = $this->getObjId();

		// duplicate the question in database
		$clone = $this;
		$clone->id = -1;

		$clone->setObjId($targetParentId);

		if ($targetQuestionTitle)
		{
			$clone->setTitle($targetQuestionTitle);
		}

		$clone->saveToDb();
		// copy question page content
		$clone->copyPageOfQuestion($sourceQuestionId);
		// copy XHTML media objects
		$clone->copyXHTMLMediaObjectsOfQuestion($sourceQuestionId);
		// duplicate the image
		$clone->copyImage($sourceQuestionId, $sourceParentId);

		$clone->onCopy($sourceParentId, $sourceQuestionId, $clone->getObjId(), $clone->getId());

		return $clone->id;
	}

	function duplicateImage($question_id, $objectId = null)
	{
		global $ilLog;

		$imagepath = $this->getImagePath();
		$imagepath_original = str_replace("/$this->id/images", "/$question_id/images", $imagepath);

		if( (int)$objectId > 0 )
		{
			$imagepath_original = str_replace("/$this->obj_id/", "/$objectId/", $imagepath_original);
		}

		if(!file_exists($imagepath))
		{
			ilUtil::makeDirParents($imagepath);
		}
		$filename = $this->getImageFilename();

		// #18755
		if(!file_exists($imagepath_original . $filename))
		{
			$ilLog->write("Could not find an image map file when trying to duplicate image: " . $imagepath_original . $filename);
			$imagepath_original = str_replace("/$this->obj_id/", "/$objectId/", $imagepath_original);
			$ilLog->write("Using fallback source directory:" . $imagepath_original);
		}

		if(!file_exists($imagepath_original . $filename) || !copy($imagepath_original . $filename, $imagepath . $filename))
		{
			$ilLog->write("Could not duplicate image for image map question: " . $imagepath_original . $filename);
		}
	}

	function copyImage($question_id, $source_questionpool)
	{
		$imagepath = $this->getImagePath();
		$imagepath_original = str_replace("/$this->id/images", "/$question_id/images", $imagepath);
		$imagepath_original = str_replace("/$this->obj_id/", "/$source_questionpool/", $imagepath_original);
		if (!file_exists($imagepath))
		{
			ilUtil::makeDirParents($imagepath);
		}
		$filename = $this->getImageFilename();
		if (!copy($imagepath_original . $filename, $imagepath . $filename))
		{
			print "image could not be copied!!!! ";
		}
	}

	/**
	 * @inheritdoc
	 */
	public function calculateReachedPoints($active_id, $pass = null, $authorizedSolution = true, $returndetails = FALSE)
	{
		$points = 0;
		return $points;
	}

	/**
	 * @param array $a_solution
	 * @return float
	 */
	protected function calculateReachedPointsForSolution($a_solution)
	{
		$points = 0;
		return $points;
	}

	/**
	 * Check file upload
	 * @return boolean Input ok, true/false
	 */
	protected function checkUpload()
	{
		$this->lng->loadLanguageModule('form');

		// remove trailing '/'
		while(substr($_FILES['upload']['name'], -1) == '/')
		{
			$_FILES['upload']['name'] = substr($_FILES['upload']['name'], 0, -1);
		}

		$filename     = $_FILES['upload']['name'];
		$filename_arr = pathinfo($_FILES['upload']['name']);
		$suffix       = $filename_arr['extension'];
		$mimetype     = $_FILES['upload']['type'];
		$size_bytes   = $_FILES['upload']['size'];
		$temp_name    = $_FILES['upload']['tmp_name'];
		$error        = $_FILES['upload']['error'];

		// error handling
		if($error > 0)
		{
			switch($error)
			{
				case UPLOAD_ERR_INI_SIZE:
					ilUtil::sendFailure($this->lng->txt('form_msg_file_size_exceeds'), true);
					return false;
					break;

				case UPLOAD_ERR_FORM_SIZE:
					ilUtil::sendFailure($this->lng->txt('form_msg_file_size_exceeds'), true);
					return false;
					break;

				case UPLOAD_ERR_PARTIAL:
					ilUtil::sendFailure($this->lng->txt('form_msg_file_partially_uploaded'), true);
					return false;
					break;

				case UPLOAD_ERR_NO_FILE:
					ilUtil::sendFailure($this->lng->txt('form_msg_file_no_upload'), true);
					return false;
					break;

				case UPLOAD_ERR_NO_TMP_DIR:
					ilUtil::sendFailure($this->lng->txt('form_msg_file_missing_tmp_dir'), true);
					return false;
					break;

				case UPLOAD_ERR_CANT_WRITE:
					ilUtil::sendFailure($this->lng->txt('form_msg_file_cannot_write_to_disk'), true);
					return false;
					break;

				case UPLOAD_ERR_EXTENSION:
					ilUtil::sendFailure($this->lng->txt('form_msg_file_upload_stopped_ext'), true);
					return false;
					break;
			}
		}

		// virus handling
		if(strlen($temp_name))
		{
			$vir = ilUtil::virusHandling($temp_name, $filename);
			if($vir[0] == false)
			{
				ilUtil::sendFailure($this->lng->txt('form_msg_file_virus_found') . '<br />' . $vir[1], true);
				return false;
			}
		}
		return true;
	}

	/**
	 * Returns the filesystem path for file uploads
	 */
	public function getFileUploadPath($test_id, $active_id, $question_id = null)
	{
		if (is_null($question_id)) $question_id = $this->getId();
		return CLIENT_WEB_DIR . "/assessment/tst_$test_id/$active_id/$question_id/files/";
	}

	/**
	 * Returns the filesystem path for file uploads
	 */
	protected function getPreviewFileUploadPath($userId)
	{
		return CLIENT_WEB_DIR . "/assessment/qst_preview/$userId/{$this->getId()}/fileuploads/";
	}

	/**
	 * Returns the file upload path for web accessible files of a question
	 *
	 * @access public
	 */
	function getFileUploadPathWeb($test_id, $active_id, $question_id = null)
	{
		if (is_null($question_id)) $question_id = $this->getId();
		include_once "./Services/Utilities/classes/class.ilUtil.php";
		$webdir = ilUtil::removeTrailingPathSeparators(CLIENT_WEB_DIR) . "/assessment/tst_$test_id/$active_id/$question_id/files/";
		return str_replace(ilUtil::removeTrailingPathSeparators(ILIAS_ABSOLUTE_PATH), ilUtil::removeTrailingPathSeparators(ILIAS_HTTP_PATH), $webdir);
	}

	/**
	 * Returns the filesystem path for file uploads
	 */
	protected function getPreviewFileUploadPathWeb($userId)
	{
		include_once "./Services/Utilities/classes/class.ilUtil.php";
		$webdir = ilUtil::removeTrailingPathSeparators(CLIENT_WEB_DIR) . "/assessment/qst_preview/$userId/{$this->getId()}/fileuploads/";
		return str_replace(ilUtil::removeTrailingPathSeparators(ILIAS_ABSOLUTE_PATH), ilUtil::removeTrailingPathSeparators(ILIAS_HTTP_PATH), $webdir);
	}

	public function getUploadedFiles($active_id, $pass = null, $authorized = true)
	{
		global $ilDB;

		if (is_null($pass))
		{
			$pass = $this->getSolutionMaxPass($active_id);
		}

		$result = $ilDB->queryF("SELECT * FROM tst_solutions WHERE active_fi = %s AND question_fi = %s AND pass = %s AND authorized = %s ORDER BY tstamp",
			array("integer", "integer", "integer", 'integer'),
			array($active_id, $this->getId(), $pass, (int)$authorized)
		);

		$found = array();

		while ($data = $ilDB->fetchAssoc($result))
		{
			array_push($found, $data);
		}

		return $found;
	}

	public function getPreviewFileUploads(ilAssQuestionPreviewSession $previewSession)
	{
		return (array)$previewSession->getParticipantsSolution();
	}

	/**
	 * Returns the web accessible uploaded files for an active user in a given pass
	 *
	 * @return array Results
	 */
	public function getUploadedFilesForWeb($active_id, $pass)
	{
		global $ilDB;

		$found = $this->getUploadedFiles($active_id, $pass);
		$result = $ilDB->queryF("SELECT test_fi FROM tst_active WHERE active_id = %s",
			array('integer'),
			array($active_id)
		);
		if ($result->numRows() == 1)
		{
			$row = $ilDB->fetchAssoc($result);
			$test_id = $row["test_fi"];
			$path = $this->getFileUploadPathWeb($test_id, $active_id);
			foreach ($found as $idx => $data)
			{
				$found[$idx]['webpath'] = $path;
			}
		}
		return $found;
	}

	protected function deleteUploadedFiles($files, $test_id, $active_id, $authorized)
	{
		global $ilDB;

		$pass = null;
		$active_id = null;
		foreach ($files as $solution_id)
		{
			$result = $ilDB->queryF("SELECT * FROM tst_solutions WHERE solution_id = %s AND authorized = %s",
				array("integer", 'integer'),
				array($solution_id, (int)$authorized)
			);
			if ($result->numRows() == 1)
			{
				$data = $ilDB->fetchAssoc($result);
				$pass = $data['pass'];
				$active_id = $data['active_fi'];
				@unlink($this->getFileUploadPath($test_id, $active_id) . $data['value1']);
			}
		}
		foreach ($files as $solution_id)
		{
			$affectedRows = $ilDB->manipulateF("DELETE FROM tst_solutions WHERE solution_id = %s AND authorized = %s",
				array("integer", 'integer'),
				array($solution_id, $authorized)
			);
		}
	}

	/**
	 * Deletes the image file
	 */
	public function deleteImage()
	{
		$file = $this->getImagePath() . $this->getImageFilename();
		@unlink($file);
		$this->image_filename = '';
	}

	protected function deletePreviewFileUploads($userId, $userSolution, $files)
	{
		foreach($files as $name)
		{
			if( isset($userSolution[$name]) )
			{
				unset($userSolution[$name]);
				@unlink($this->getPreviewFileUploadPath($userId) . $name);
			}
		}

		return $userSolution;
	}

	/**
	 * @inheritdoc
	 */
	public function saveWorkingData($active_id, $pass = NULL, $authorized = true)
	{
		global $ilDB;

		if(is_null($pass))
		{
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			$pass = ilObjTest::_getPass($active_id);
		}

		if($_POST['cmd'][$this->questionActionCmd] != $this->lng->txt('delete')
			&& strlen($_FILES["upload"]["tmp_name"])
		)
		{
			$checkUploadResult = $this->checkUpload();
		}
		else
		{
			$checkUploadResult = false;
		}

		$result  = $ilDB->queryF("SELECT test_fi FROM tst_active WHERE active_id = %s",
			array('integer'),
			array($active_id)
		);
		$test_id = 0;
		if($result->numRows() == 1)
		{
			$row     = $ilDB->fetchAssoc($result);
			$test_id = $row["test_fi"];
		}

		$this->getProcessLocker()->requestUserSolutionUpdateLock();

		$this->updateCurrentSolutionsAuthorization($active_id, $pass, $authorized);

		$entered_values = false;

		if($_POST['cmd'][$this->questionActionCmd] == $this->lng->txt('delete'))
		{
			if(is_array($_POST['deletefiles']) && count($_POST['deletefiles']) > 0)
			{
				$this->deleteUploadedFiles($_POST['deletefiles'], $test_id, $active_id, $authorized);
			}
			else
			{
				ilUtil::sendInfo($this->lng->txt('no_checkbox'), true);
			}
		}
		else if($checkUploadResult)
		{
			if(!@file_exists($this->getFileUploadPath($test_id, $active_id)))
			{
				ilUtil::makeDirParents($this->getFileUploadPath($test_id, $active_id));
			}

			$version      = time();
			$filename_arr = pathinfo($_FILES["upload"]["name"]);
			$extension    = $filename_arr["extension"];
			$newfile      = "file_" . $active_id . "_" . $pass . "_" . $version . "." . $extension;

			ilUtil::moveUploadedFile($_FILES["upload"]["tmp_name"], $_FILES["upload"]["name"], $this->getFileUploadPath($test_id, $active_id) . $newfile);

			$this->saveCurrentSolution($active_id, $pass, $newfile, $_FILES['upload']['name'], $authorized);

			$entered_values = true;
		}

		$this->getProcessLocker()->releaseUserSolutionUpdateLock();

		require_once 'Modules/Test/classes/class.ilObjAssessmentFolder.php';
		if($entered_values)
		{
			if(ilObjAssessmentFolder::_enabledAssessmentLogging())
			{
				$this->logAction($this->lng->txtlng('assessment', 'log_user_entered_values', ilObjAssessmentFolder::_getLogLanguage()), $active_id, $this->getId());
			}
		}
		else
		{
			if(ilObjAssessmentFolder::_enabledAssessmentLogging())
			{
				$this->logAction($this->lng->txtlng('assessment', 'log_user_not_entered_values', ilObjAssessmentFolder::_getLogLanguage()), $active_id, $this->getId());
			}
		}

		return true;
	}

	/**
	 * @param ilAssQuestionPreviewSession $previewSession
	 */
	protected function savePreviewData(ilAssQuestionPreviewSession $previewSession)
	{
		$userSolution = $previewSession->getParticipantsSolution();

		if( !is_array($userSolution) )
		{
			$userSolution = array();
		}

		if (strcmp($_POST['cmd'][$this->questionActionCmd], $this->lng->txt('delete')) == 0)
		{
			if (is_array($_POST['deletefiles']) && count($_POST['deletefiles']) > 0)
			{
				$userSolution = $this->deletePreviewFileUploads($previewSession->getUserId(), $userSolution, $_POST['deletefiles']);
			}
			else
			{
				ilUtil::sendInfo($this->lng->txt('no_checkbox'), true);
			}
		}
		else
		{
			if (strlen($_FILES["upload"]["tmp_name"]))
			{
				if ($this->checkUpload())
				{
					if( !@file_exists($this->getPreviewFileUploadPath($previewSession->getUserId())) )
					{
						ilUtil::makeDirParents($this->getPreviewFileUploadPath($previewSession->getUserId()));
					}

					$version = time();
					$filename_arr = pathinfo($_FILES["upload"]["name"]);
					$extension = $filename_arr["extension"];
					$newfile = "file_".md5($_FILES["upload"]["name"])."_" . $version . "." . $extension;
					ilUtil::moveUploadedFile($_FILES["upload"]["tmp_name"], $_FILES["upload"]["name"], $this->getPreviewFileUploadPath($previewSession->getUserId()) . $newfile);

					$userSolution[$newfile] = array(
						'solution_id' => $newfile,
						'value1' => $newfile,
						'value2' => $_FILES['upload']['name'],
						'tstamp' => $version,
						'webpath' => $this->getPreviewFileUploadPathWeb($previewSession->getUserId())
					);
				}
			}
		}

		$previewSession->setParticipantsSolution($userSolution);
	}

	protected function reworkWorkingData($active_id, $pass, $obligationsAnswered, $authorized)
	{
		$this->handleSubmission($active_id, $pass, $obligationsAnswered, $authorized);
	}

	protected function handleSubmission($active_id, $pass, $obligationsAnswered, $authorized)
	{
		if(!$authorized)
		{
			return;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getAdditionalTableName()
	{
		return 'qpl_qst_fssqst_data';
	}

	/**
	 * @inheritdoc
	 */
	public function getQuestionType()
	{
		return ilassFreestyleScanQuestionPlugin::getName();
	}

	/**
	 * @inheritdoc
	 */
	public function setExportDetailsXLS(&$worksheet, $startrow, $active_id, $pass, &$format_title, &$format_bold)
	{
		require_once 'Services/Excel/classes/class.ilExcelUtils.php';
		$worksheet->writeString($startrow, 0, ilExcelUtils::_convert_text($this->lng->txt($this->getQuestionType())), $format_title);
		$worksheet->writeString($startrow, 1, ilExcelUtils::_convert_text($this->getTitle()), $format_title);
		$i = 1;
		$solutions = $this->getSolutionValues($active_id, $pass);
		foreach ($solutions as $solution)
		{
			$worksheet->writeString($startrow + $i, 0, ilExcelUtils::_convert_text($this->lng->txt('result')), $format_bold);
			if (strlen($solution['value1']))
			{
				$worksheet->write($startrow + $i, 1, ilExcelUtils::_convert_text($solution['value1']));
				$worksheet->write($startrow + $i, 2, ilExcelUtils::_convert_text($solution['value2']));
			}
			$i++;
		}
		return $startrow + $i + 1;
	}

	/**
	 * @inheritdoc
	 */
	public function fromXML(&$item, &$questionpool_id, &$tst_id, &$tst_object, &$question_counter, &$import_mapping)
	{
		$this->getPlugin()->includeClass("import/qti12/class.assFreestyleScanQuestionImport.php");
		$import = new assFreestyleScanQuestionImport($this);
		$import->fromXML($item, $questionpool_id, $tst_id, $tst_object, $question_counter, $import_mapping);
	}

	/**
	 * @inheritdoc
	 */
	public function toXML($a_include_header = true, $a_include_binary = true, $a_shuffle = false, $test_output = false, $force_image_references = false)
	{
		$this->getPlugin()->includeClass("export/qti12/class.assFreestyleScanQuestionExport.php");
		$export = new assFreestyleScanQuestionExport($this);
		return $export->toXML($a_include_header, $a_include_binary, $a_shuffle, $test_output, $force_image_references);
	}

	/**
	 * @param int $active_id
	 * @param int  $pass
	 * @return array
	 */
	public function getBestSolution($active_id, $pass)
	{
		$user_solution = array();
		return $user_solution;
	}

	/**
	 * @inheritdoc
	 */
	public function hasFileUploads($test_id)
	{
		global $ilDB;

		$query  = "
			SELECT tst_solutions.solution_id 
			FROM tst_solutions, tst_active, qpl_questions 
			WHERE tst_solutions.active_fi = tst_active.active_id 
			AND tst_solutions.question_fi = qpl_questions.question_id 
			AND tst_solutions.question_fi = %s AND tst_active.test_fi = %s
		";

		$result = $ilDB->queryF(
			$query,
			array('integer', 'integer'),
			array($this->getId(), $test_id)
		);

		if ($result->numRows() > 0)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function deliverFileUploadZIPFile($test_id, $test_title)
	{
		global $ilDB, $lng;

		require_once 'Modules/TestQuestionPool/classes/class.ilAssFileUploadUploadsExporter.php';
		$exporter = new ilAssFileUploadUploadsExporter($ilDB, $lng);

		$exporter->setTestId($test_id);
		$exporter->setTestTitle($test_title);
		$exporter->setQuestion($this);

		$exporter->build();

		ilUtil::deliverFile(
			$exporter->getFinalZipFilePath(), $exporter->getDispoZipFileName(),
			$exporter->getZipFileMimeType(), false, true
		);
	}

	/**
	 * @inheritdoc
	 */
	public function isAnswered($active_id, $pass = null)
	{
		$numExistingSolutionRecords = assQuestion::getNumExistingSolutionRecords($active_id, $pass, $this->getId());

		return $numExistingSolutionRecords > 0;
	}

	/**
	 * @inheritdoc
	 */
	public static function isObligationPossible($questionId)
	{
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function isAutosaveable()
	{
		return false;
	}
}
