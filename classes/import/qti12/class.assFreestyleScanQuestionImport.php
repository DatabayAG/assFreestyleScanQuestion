<?php
/* Copyright (c) 1998-2017 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Modules/TestQuestionPool/classes/import/qti12/class.assQuestionImport.php';

/**
 * Class assFreestyleScanQuestionImport
 */
class assFreestyleScanQuestionImport extends assQuestionImport
{
	/**
	 * @inheritdoc
	 */
	public function fromXML(&$item, $questionpool_id, &$tst_id, &$tst_object, &$question_counter, &$import_mapping)
	{
		global $ilUser;

		// empty session variable for imported xhtml mobs
		unset($_SESSION["import_mob_xhtml"]);
		$presentation = $item->getPresentation();
		$duration     = $item->getDuration();

		$questionimage = array();
		foreach($presentation->order as $entry)
		{
			if($entry["type"] == "response")
			{
				$response   = $presentation->response[$entry["index"]];
				$rendertype = $response->getRenderType();

				if(strtolower(get_class($rendertype)) == "ilqtirenderhotspot")
				{
					foreach($rendertype->material as $material)
					{
						for($i = 0; $i < $material->getMaterialCount(); $i++)
						{
							$m = $material->getMaterial($i);
							if(strcmp($m["type"], "matimage") == 0)
							{
								$questionimage = array(
									"imagetype" => $m["material"]->getImageType(),
									"label"     => $m["material"]->getLabel(),
									"content"   => $m["material"]->getContent()
								);
							}
						}
					}
				}
			}
		}

		$this->addGeneralMetadata($item);
		$this->object->setTitle($item->getTitle());
		$this->object->setNrOfTries($item->getMaxattempts());
		$this->object->setComment($item->getComment());
		$this->object->setAuthor($item->getAuthor());
		$this->object->setOwner($ilUser->getId());
		$this->object->setQuestion($this->object->QTIMaterialToString($item->getQuestiontext()));
		$this->object->setObjId($questionpool_id);
		$this->object->setEstimatedWorkingTime($duration["h"], $duration["m"], $duration["s"]);
		$this->object->setPoints($item->getMetadataEntry("points"));
		$this->object->setImageFilename($questionimage["label"]);
		// additional content editing mode information
		$this->object->setAdditionalContentEditingMode(
			$this->fetchAdditionalContentEditingModeInformation($item)
		);
		$this->object->saveToDb();

		$feedbacksgeneric = $this->getFeedbackGeneric($item);

		$image =& base64_decode($questionimage["content"]);
		$imagepath = $this->object->getImagePath();
		if (!file_exists($imagepath))
		{
			include_once "./Services/Utilities/classes/class.ilUtil.php";
			ilUtil::makeDirParents($imagepath);
		}
		$imagepath .=  $questionimage["label"];
		$fh = fopen($imagepath, "wb");
		if($fh)
		{
			fwrite($fh, $image);
			fclose($fh);
		}

		// handle the import of media objects in XHTML code
		$questiontext = $this->object->getQuestion();
		if (is_array($_SESSION["import_mob_xhtml"]))
		{
			include_once "./Services/MediaObjects/classes/class.ilObjMediaObject.php";
			include_once "./Services/RTE/classes/class.ilRTE.php";
			foreach ($_SESSION["import_mob_xhtml"] as $mob)
			{
				if ($tst_id > 0)
				{
					$importfile = $this->getTstImportArchivDirectory() . '/' . $mob["uri"];
				}
				else
				{
					$importfile = $this->getQplImportArchivDirectory() . '/' . $mob["uri"];
				}

				$GLOBALS['ilLog']->write(__METHOD__.': import mob from dir: '. $importfile);

				$media_object =& ilObjMediaObject::_saveTempFileAsMediaObject(basename($importfile), $importfile, FALSE);
				ilObjMediaObject::_saveUsage($media_object->getId(), "qpl:html", $this->object->getId());
				$questiontext = str_replace("src=\"" . $mob["mob"] . "\"", "src=\"" . "il_" . IL_INST_ID . "_mob_" . $media_object->getId() . "\"", $questiontext);
				foreach ($feedbacksgeneric as $correctness => $material)
				{
					$feedbacksgeneric[$correctness] = str_replace("src=\"" . $mob["mob"] . "\"", "src=\"" . "il_" . IL_INST_ID . "_mob_" . $media_object->getId() . "\"", $material);
				}
			}
		}
		$this->object->setQuestion(ilRTE::_replaceMediaObjectImageSrc($questiontext, 1));
		foreach ($feedbacksgeneric as $correctness => $material)
		{
			$this->object->feedbackOBJ->importGenericFeedback(
				$this->object->getId(), $correctness, ilRTE::_replaceMediaObjectImageSrc($material, 1)
			);
		}
		$this->object->saveToDb();
		if (count($item->suggested_solutions))
		{
			foreach ($item->suggested_solutions as $suggested_solution)
			{
				$this->object->setSuggestedSolution($suggested_solution["solution"]->getContent(), $suggested_solution["gap_index"], true);
			}
			$this->object->saveToDb();
		}
		if ($tst_id > 0)
		{
			$q_1_id = $this->object->getId();
			$question_id = $this->object->duplicate(true, null, null, null, $tst_id);
			$tst_object->questions[$question_counter++] = $question_id;
			$import_mapping[$item->getIdent()] = array("pool" => $q_1_id, "test" => $question_id);
		}
		else
		{
			$import_mapping[$item->getIdent()] = array("pool" => $this->object->getId(), "test" => 0);
		}
	}
}
