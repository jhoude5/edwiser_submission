<?php

require_once '../../config.php';
require_once('forms/remove_form.php');
global $DB, $USER, $SESSION, $OUTPUT, $PAGE;

$formid = optional_param('formid', 0,PARAM_INT);
$userid = optional_param('userid', $USER->id,PARAM_INT);
$id = optional_param('id', 0,PARAM_INT);

if($id == 0) {
    if(isset($SESSION->edwisermodid)) {
        $id = $SESSION->edwisermodid;
    }
}
if($formid == 0) {
    if(isset($SESSION->edwiserformid)) {
        $formid = $SESSION->edwiserformid;
    }
}
$PAGE->set_url('/local/edwiser_submission/remove.php');
$PAGE->set_context(context_system::instance());

require_login();

$PAGE->set_heading('Student Achievement in Reading');
$PAGE->set_title('Student Achievement in Reading');

$removeform = new local_remove_edwiser_form();

$starcourse = $DB->get_record('course', ['shortname'=>'STAR']);
$courseid = $starcourse->id;

if($removeform != '') {
    if($removeform->is_cancelled()) {
        $url = new moodle_url('/local/edwiser_submission/index.php?formid='.$formid.'&userid='.$userid);
        redirect($url);

    } else if($fromform = $removeform->get_data()) {
        list ($course, $cm) = get_course_and_cm_from_cmid($id, 'edwiserform');
        $DB->delete_records('efb_form_data', array('userid' => $userid, 'formid' => $formid));
        $DB->delete_records('efb_submissionstatus', array('userid' => $userid, 'formid' => $formid, 'modid' => $id));

        redirect('/local/edwiser_submission/index.php?formid='.$formid.'&userid='.$userid, 'Submission removed.');
    }
}
echo $OUTPUT->header();
$removeform->display();
echo $OUTPUT->footer();
