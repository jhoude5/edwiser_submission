<?php

require_once '../../config.php';
global $DB;


$PAGE->set_url('/local/edwiser_submission/draft.php');
$PAGE->set_context(context_system::instance());

require_login();
$modid = required_param('id', PARAM_TEXT);
$userid = required_param('userid', PARAM_INT);
$formid = required_param('formid', PARAM_INT);
$action = required_param('action', PARAM_TEXT);

if($action == 'draft') {
    $getsubmission = $DB->get_record('efb_submissionstatus', array('userid' => $userid, 'formid' => $formid));
    $draftarray['id'] = $getsubmission->id;
    $draftarray['status'] = 'Draft';
    $draftarray['submission'] = 'Draft';
    $DB->update_record('efb_submissionstatus', $draftarray);
    $url = '/local/edwiser_submission/index.php?userid=' . $userid . '&formid=' . $formid . '&mod=' . $modid;
    redirect($url,  'Form status updated.', 10,  \core\output\notification::NOTIFY_SUCCESS);
}
