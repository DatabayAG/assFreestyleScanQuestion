<#1>
<?php
$res = $ilDB->queryF("SELECT * FROM qpl_qst_type WHERE type_tag = %s", array('text'), array('assFreestyleScanQuestion')
);
if ($res->numRows() == 0) {
	$res = $ilDB->query("SELECT MAX(question_type_id) maxid FROM qpl_qst_type");
	$data = $ilDB->fetchAssoc($res);
	$max = $data["maxid"] + 1;

	$affectedRows = $ilDB->manipulateF("INSERT INTO qpl_qst_type (question_type_id, type_tag, plugin) VALUES (%s, %s, %s)", array("integer", "text", "integer"), array($max, 'assFreestyleScanQuestion', 1)
	);
}
?>
<#2>
<?php
if(!$ilDB->tableExists('qpl_qst_fssqst_data'))
{
	$ilDB->createTable('qpl_qst_fssqst_data', array(
		'question_fi' => array(
			'type'    => 'integer',
			'length'  => 4,
			'notnull' => true,
			'default' => 0
		),
		'image_file'  => array(
			'type'    => 'text',
			'length'  => 255,
			'notnull' => false
		)
	));
}
?>
<#3>
<?php
$ilDB->addPrimaryKey('qpl_qst_fssqst_data', array('question_fi'));
?>