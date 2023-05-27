<?php
require_once '../../config.php';
require_once '../../blocks/moodleblock.class.php';
require_once '../../blocks/star_plans/block_star_plans.php';

global $USER, $DB, $CFG, $COURSE, $SESSION, $PAGE, $OUTPUT;
require_once('../studentdata/locallib.php');
require_once("forms/submit.php");
require_once("forms/grading.php");
require_once("forms/file_attachment_form.php");
require_once("locallib.php");
require_once($CFG->dirroot . '/comment/lib.php');
require_once($CFG->dirroot.'/lib/completionlib.php');

$modid = optional_param('mod', FALSE, PARAM_INT);
$userid = optional_param('userid', FALSE, PARAM_INT);
$formid = optional_param('formid', FALSE, PARAM_INT);

if (!$modid) {
  if (property_exists($SESSION, 'edwisermodid')) {
    $modid = $SESSION->edwisermodid;
  }
}
else {
  $SESSION->edwisermodid = $modid;
}
if (!$userid) {
  if (property_exists($SESSION, 'uid')) {
    $userid = $SESSION->uid;
  }
}
else {
  $SESSION->uid = $userid;
}
if (!$formid) {
  if (property_exists($SESSION, 'edwiserformid')) {
    $formid = $SESSION->edwiserformid;
  }

}
else {
  $SESSION->edwiserformid = $formid;
}

require_login();

$course = $DB->get_record('course', ['shortname'=>'STAR']);
$courseid = $course->id;
$context = context_course::instance($courseid);

$PAGE->set_url('/local/edwiser_submission/index.php');
$PAGE->set_context(context_system::instance());
$PAGE->requires->js('/local/studentdata/assets/studentdata.js');


// Role checking
$student = $trainer = FALSE;

$roles = $DB->get_records('role_assignments', ['userid' => $USER->id]);
foreach ($roles as $r) {
    $role = $DB->get_record('role', ['id' => $r->roleid]);
    if ($role->name == 'Student') {
        $student = true;
    } else {
        $trainer = true;
    }
}

$strpagetitle = get_string('pagetitle', 'local_edwiser_submission');
$strpageheading = get_string('pagetitle', 'local_edwiser_submission');
$moduletitle = '';
// Module info
$modinstance = $modid;
$modinfo = get_fast_modinfo($courseid);
foreach ($modinfo->cms as $item => $value) {
  if ($item == $modinstance) {
    $moduletitle = $value->name;
  }
}
// Submission checks
$submissionform = $DB->get_record('efb_form_data', [
  'formid' => $formid,
  'userid' => $userid,
]);

$form = $DB->get_record('efb_forms', ['id' => $formid]);
$formstatus = $DB->get_record('efb_submissionstatus', [
  'formid' => $formid,
  'userid' => $userid,
]);
// Breadcrumbs
$PAGE->navbar->add('My courses');
$PAGE->navbar->add('STAR', new moodle_url('/course/view.php', ['id' => $courseid]));

$groupname = $groupid = '';
$groupmemlist = $DB->get_record_select('groups_members', 'userid = ?', [$userid]);
if (!empty($groupmemlist)) {
  $grouplist = $DB->get_records_select('groups', 'id = ?', [$groupmemlist->groupid]);
  if (!empty($grouplist)) {
    foreach ($grouplist as $group) {
      $groupid = $group->id;
      $groupname = $group->name;
    }
  }
}
$program_team_url = '/report/stardashboard/groups.php?userid=' . $userid . '&groupid=' . $groupid . '&courseid=' . $courseid;
if ($trainer) {
  $PAGE->navbar->add($groupname, new moodle_url($program_team_url));
}

if ($moduletitle) {
  $sectiontitle = 'Section 1';
  $sectionurl = $program_team_url . '#section-1';
  if (in_array($moduletitle, [
    '16. Vocabulary Instruction Assessment',
    '18. Fluency Instruction Assessment',
    '20. Alphabetics Instruction Assessment',
    '22. Comprehension Instruction Assessment',
    '23. Individual Reflections',
    ])) {
    $sectiontitle = 'Section 2';
    $sectionurl = $program_team_url . '#section-2';
  }
  else {
    if ($moduletitle == '29. Create Lesson Plan Assessment') {
      $sectiontitle = 'Section 3';
      $sectionurl = $program_team_url . '#section-3';
    }
    else {
      $sectiontitle = 'General';
      $sectionurl = $program_team_url . '#section-0';
    }
  }
  $PAGE->navbar->add($sectiontitle, new moodle_url($sectionurl));
  $PAGE->navbar->add($moduletitle);
}
else {
  $PAGE->navbar->add('Submission not found');
}

$PAGE->set_title($strpagetitle);
$PAGE->set_heading($strpageheading);
$PAGE->set_pagelayout('starcourse');

$submission = $submissionform->submission ?? '';

if ($form) {
  $fields = json_decode($form->definition)->fields;
  $labels = [];
  foreach ($fields as $field) {
    if ($field->config->label) {
      if (!property_exists($field->config, 'hideLabel')) {
        array_push($labels, $field->config->label);
      }
    }
  }
}

$splitsubmission = json_decode($submission);
$value = $data = $formdata = [];
$forminfo = $formdata = [];
$submissionid = '';
// Comments
$comment = '';
if($submissionform) {
    comment::init();
    $options = new stdClass();
    $options->area    = 'submission_comments';
    $options->course    = $course;
    $options->context = $context;
    $options->itemid  = $submissionform->id;
    $options->showcount = true;
    $options->component = 'local_edwiser_submission';
    $options->displaycancel = true;

  $comment = new comment($options);
}
$userinfo = $DB->get_record('user', ['id' => $userid]);

// Load user profile fields and get starusername field.
profile_load_custom_fields($userinfo);
$starusername = $userinfo->username;
if (isset($userinfo->profile['starusername']) && !empty($userinfo->profile['starusername'])) {
  $starusername = $userinfo->profile['starusername'];
}
$toform = [];
$mform = new edwisersubmissionsubmit_form();
$edwisersubmission = new edwiser_submission();
if ($mform != '') {
  // Participant submits submission for feedback
  if ($fromform = $mform->get_data()) {
    $subform = $DB->get_record('efb_submissionstatus', [
      'formid' => $formid,
      'userid' => $userid,
    ]);
    $toform['id'] = $subform->id;
    $toform['status'] = 'Awaiting feedback';
    $toform['submission'] = 'Submitted';
    $edwisersubmission->submit_for_grading($subform, $trainer);
    $DB->update_record('efb_submissionstatus', $toform);
    $url = '/local/edwiser_submission/index.php?formid=' . $formid . '&userid=' . $userid . '&mod=' . $modid;
    redirect($url, 'Form status updated', 10, \core\output\notification::NOTIFY_SUCCESS);

  }

  // Trainer changes status of submission
  $actionform = new edwisersubmission_form();
  if ($fromform = $actionform->get_data()) {
    $submissionform = $DB->get_record('efb_submissionstatus', [
      'formid' => $formid,
      'userid' => $userid,
    ]);
    $edwiserform = $DB->get_record('efb_forms', ['id'=>$formid]);
    $toform['id'] = $submissionform->id;
    $toform['status'] = $fromform->status;
    // Update completion status
      $modinfo = get_fast_modinfo($courseid);
      $modid = '';
      $completion = false;
      foreach ($modinfo->cms as $value) {
          if ($value->name == '9. Comprehension Assessment') {
              if($edwiserform->title == 'Comprehension Reflections') {
                  $completion = true;
                  $modid = $value->id;
              }
          } else if ($value->name == '23. Individual Reflection: Instruction') {
              if($edwiserform->title == 'Individual Reflections') {
                  $modid = $value->id;
                  $completion = true;
              }
          }
      }
      if($fromform->status == 'Approved') {
        // Update completion state
        $completioninfo = new completion_info($course);
        if($completion) {
            $cm = get_coursemodule_from_id('page', $modid);
            $completioninfo->update_state($cm,COMPLETION_COMPLETE, $userid);
        }

    }
    $edwisersubmission->submit_for_grading($submissionform, $trainer);
    $DB->update_record('efb_submissionstatus', $toform);
    $url = '/local/edwiser_submission/index.php?formid=' . $formid . '&userid=' . $userid . '&mod=' . $modid;
    redirect($url, 'Form status updated', 10, \core\output\notification::NOTIFY_SUCCESS);

  }
}

// Student data
$getstudentdata = new studentdata();
$studentdata = $getstudentdata->get_student_data($userid);
$students = new stdClass();
$students->data = array_values($studentdata);
echo $OUTPUT->header();
// Floating student data chart
echo $OUTPUT->render_from_template('local_studentdata/modal_studenttable', $students);

//If submit send notification

if ($form && property_exists($form, 'title')) {
  echo '<h2>' . $starusername . ': ' . $form->title . '</h2>';
}
$starformid = $tablename = '';

if($moduletitle == '6. Alphabetics - Record assessments' || $moduletitle == '6. Alphabetics - Record Assessments') {
  if ($userid == $USER->id) {
    echo $OUTPUT->render_from_template('local_studentdata/alphabetics', $students);
  } else {
    echo $OUTPUT->render_from_template('local_studentdata/alphabetics_view', $students);
  }
} else if($moduletitle == '7. Fluency - Record reflections' || $moduletitle == '7. Fluency - Record Reflections') {
  if ($userid == $USER->id) {
    echo $OUTPUT->render_from_template('local_studentdata/fluency', $students);
  } else {
    echo $OUTPUT->render_from_template('local_studentdata/fluency_view', $students);
  }
} else if($moduletitle == '8. Vocabulary - Record assessment' || $moduletitle == '8. Vocabulary - Record Assessment') {
  if ($userid == $USER->id) {
    echo $OUTPUT->render_from_template('local_studentdata/vocabulary', $students);
  } else {
    echo $OUTPUT->render_from_template('local_studentdata/vocabulary_view', $students);
  }
} else if($moduletitle == '9. Comprehension - Record assessment' || $moduletitle == '9. Comprehension - Record Assessment') {
  if ($userid == $USER->id) {
    echo $OUTPUT->render_from_template('local_studentdata/comprehension', $students);
  } else {
    echo $OUTPUT->render_from_template('local_studentdata/comprehension_view', $students);
  }
}  else {
  if ($userid == $USER->id) {
    echo $OUTPUT->render_from_template('local_studentdata/instructional_priorities', $students);

  } else {
    echo $OUTPUT->render_from_template('local_studentdata/instructional_priorities_view', $students);
  }
    if($moduletitle == '16. Vocabulary Instruction Assessment') {
        $module = 'module16';
        $tablename = 'star_instruct_m16';
        $starformid = '16';
    } else if($moduletitle == '18. Fluency Instruction Assessment') {
        $module = 'module18';
        $tablename = 'star_instruct_m18';
        $starformid = '18';
    } else if($moduletitle == '20. Alphabetics Instruction Assessment') {
        $module = 'module20';
        $tablename = 'star_instruct_m20';
        $starformid = '20';
    } else if($moduletitle == '22. Comprehension Instruction Assessment') {
        $module = 'module22';
        $tablename = 'star_instruct_m22';
        $starformid = '22';
    } else if($moduletitle == '29. Create Lesson Plan Assessment'){
        $module = 'module29';
        $tablename = 'star_instruct_m29';
        $starformid = '29';
    }
    if($tablename != '') {


        $planform = $DB->get_records($tablename, array('formid' => $starformid, 'userid' => $userid));
        $data = array();
        $results = new stdClass();
        if ($moduletitle == '29. Create Lesson Plan Assessment') {
            $m29routine = array();
            foreach ($planform as $row) {
                foreach ($row as $index => $entry) {

                    $routine = array();
                    if ($index == 'minutes1' && $entry != '0' && $entry != '') {
                        $routine['minutes'] = $row->minutes1;
                        $routine['groupone'] = $row->groupone1;
                        $routine['grouptwo'] = $row->grouptwo1;
                    } else if ($index == 'minutes2' && $entry != '0' && $entry != '') {
                        $routine['minutes'] = $row->minutes2;
                        $routine['groupone'] = $row->groupone2;
                        $routine['grouptwo'] = $row->grouptwo2;
                    } else if ($index == 'minutes3' && $entry != '0' && $entry != '') {
                        $routine['minutes'] = $row->minutes3;
                        $routine['groupone'] = $row->groupone3;
                        $routine['grouptwo'] = $row->grouptwo3;
                    }
                    if (($index == 'alpha' && $entry == '') ||
                        ($index == 'vocab' && $entry == '') ||
                        ($index == 'fluency' && $entry == '') ||
                        ($index == 'compre' && $entry == '')) {
                        $datainfo[$index] = '-';
                    } else {
                        $datainfo[$index] = $entry;
                    }

                    if (!empty($routine)) {
                        array_push($m29routine, $routine);
                    }
                }
                if ($row->minutesperclass == '' || $row->minutesperclass == 0) {
                    $datainfo['class'] = '-';
                } else {
                    $datainfo['class'] = $row->minutesperclass;
                }
                if ($row->daysperweek == '' || $row->daysperweek == 0) {
                    $datainfo['days'] = '-';
                } else {
                    $datainfo['days'] = $row->daysperweek;
                }
                if (!empty($datainfo)) {
                    array_push($data, $datainfo);
                }
            }
            $results->data = array_values($data);
            $block_star_plans = new block_star_plans();
            $results->routine = array_values($m29routine);
            $results = $block_star_plans->get_instructional($results, $userid);
            echo $OUTPUT->render_from_template('block_star_plans/module29', $results);
        } else {
            if ($planform) {
                foreach ($planform as $form) {
                    array_push($data, $form);
                }
            }
            $results->data = array_values($data);
            $block_star_plans = new block_star_plans();
            $results = $block_star_plans->get_instructional($results, $userid);
            echo $OUTPUT->render_from_template('block_star_plans/' . $module, $results);
        }
    }
}

$results = new stdClass();
$actions = new stdClass();
$toform = [];
$mform = new edwisersubmission_form();
$fileattach = new local_file_attachment_form();
if ($student) {
  // todo investigate intermittent issue where db does not contain the record at this point and after first subission form appears empty
  $submissionform = $DB->get_record('efb_form_data', [
    'formid' => $formid,
    'userid' => $userid,
  ]);
  if ($submissionform) {
    if ($submissionform->updated == NULL) {
      $date = isset($submissionform->date) ? date('l, F j, Y, g:i A', $submissionform->date) : '';
    }
    else {
      $date = isset($submissionform->updated) ? date('l, F j, Y, g:i A', $submissionform->updated) : '';
    }

    $mform = new edwisersubmissionsubmit_form();
    if (empty($formstatus)) {
      $forminfo['status'] = 'Draft';
      $forminfo['userid'] = $userid;
      $forminfo['formid'] = $formid;
      $forminfo['submission'] = 'Not submitted';
      $forminfo['date'] = $date;
      if ($modid != 0) {
        $forminfo['modid'] = $modid;
      }
      $DB->insert_record('efb_submissionstatus', $forminfo);

      array_push($formdata, $forminfo);
    }
    else {

      $submissionid = $formstatus->id;
      if ($gradeform = $mform->get_data()) {
        $forminfo['id'] = $submissionid;
        $forminfo['date'] = $date;
        $forminfo['userid'] = $formstatus->userid;
        $forminfo['formid'] = $formid;
        $forminfo['modid'] = $modid;
        $forminfo['status'] = 'Awaiting approval';
        $forminfo['submission'] = $formstatus->submission;

        array_push($formdata, $forminfo);
      }
      else {
        $forminfo['date'] = $date;
        $forminfo['status'] = $formstatus->status;
        $forminfo['submission'] = $formstatus->submission;
        $forminfo['userid'] = $formstatus->userid;
        $forminfo['formid'] = $formid;
        $forminfo['modid'] = $modid;
        array_push($formdata, $forminfo);
      }
    }

    $sub = $submissioninfo = $submissiondata = [];

    $count = 0;
    foreach ($splitsubmission as $item) {
      if (!empty($item)) {
        $submissioninfo['name'] = $item->name;
        $submissioninfo['label'] = $labels[$count];
        $submissioninfo['value'] = nl2br($item->value);
        $count++;
        if (!empty($submissioninfo)) {
          array_push($submissiondata, $submissioninfo);
        }
      }
    }

    $modarr = $modins = [];
    foreach ($formdata as $value) {
      $modins['modid'] = $value['modid'];
      $modins['userid'] = $value['userid'];
      $modins['formid'] = $value['formid'];

      array_push($modarr, $modins);
    }

    $results->data = array_values($submissiondata);
    $results->form = array_values($formdata);
    $actions->data = array_values($modarr);
// File attachment form
      if($fileattach != '') {
          $subform = $DB->get_record('efb_submissionstatus', ['userid' => $userid, 'formid' => $formid]);

              if ($data = $fileattach->get_data()) {
                  // ... store or update $entry

                  file_save_draft_area_files($data->attachments, $context->id, 'local_edwiser_submission', 'attachment',
                      $subform->id, array('subdirs' => 0, 'maxbytes' => 2097152, 'maxfiles' => 50));
              }else {
                  if (empty($entry->id)) {
                      $entry = new stdClass;
                      $entry->id = $subform->id;
                  }
                  $draftitemid = file_get_submitted_draft_itemid('attachments');

                  file_prepare_draft_area($draftitemid, $context->id, 'local_edwiser_submission', 'attachment', $subform->id,
                      array('subdirs' => 0, 'maxbytes' => 2097152, 'maxfiles' => 50));

                  $entry->attachments = $draftitemid;

                  $fileattach->set_data($entry);
              }
          
      }


    echo $OUTPUT->render_from_template('local_edwiser_submission/submissionviewstatus', $results);
    echo '<tr><th>Submission Comments</th><td>';
    $comment->output(FALSE);
      $fileattach->display();
    echo '</td></tr>';
    echo $OUTPUT->render_from_template('local_edwiser_submission/submissionview', $results);
    if($userid == $USER->id) {
        echo $OUTPUT->render_from_template('local_edwiser_submission/actionbuttons', $actions);
        $formstatus = $DB->get_record('efb_submissionstatus', [
            'formid' => $formid,
            'userid' => $userid,
        ]);

        $mform->set_data($toform);
        $mform->display();
    }
  }
  elseif ($form && property_exists($form, 'title')) {
      echo '<h2>' . $form->title . '</h2><p>Submission not created</p>
           <a href="/mod/edwiserform/view.php?id='.$modid.'" class="btn btn-primary">Add a submission</a>
        ';

  }
}
else {
  if ($submissionform) {
    $currentstatus = $draft = [];
    if ($moduletitle == '29. Create Lesson Plan Assessment' && property_exists($results, 'data')) {
      foreach ($results->data as $field) {

        switch ($field[key($field)]) {
          case 'assessment 1':
            $fielddata[] = ['routine' => $field['value']];
            break;
          case 'assessment 2':
            $fielddata[] = ['routine_changes' => $field['value']];
            break;
          case 'assessment 3':
            $fielddata[] = ['issues' => $field['value']];
            break;
          case 'assessment 4':
            $fielddata[] = ['went_well1' => $field['value']];
            break;
          case 'assessment 5':
            $fielddata[] = ['impact' => $field['value']];
            break;
          case 'assessment 6':
            $fielddata[] = ['went_well2' => $field['value']];
            break;
          case 'assessment 7':
            $fielddata[] = ['questions' => $field['value']];
            break;
          default:
            $fielddata[] = $field;
        }
      }

      $results->data = $fielddata;
    }
    $submission = $submissionform->submission;
    $splitsubmission = json_decode($submission);
    $value = $data = $formdata = [];

    if ($submissionform->updated == NULL) {
      $date = date('l, F j, Y, g:i A', $submissionform->date);
    }
    else {
      $date = date('l, F j, Y, g:i A', $submissionform->updated);
    }

    $username = $userdata = [];
    $username['username'] = $starusername;
    array_push($userdata, $username);

    $formstatus = $DB->get_record('efb_submissionstatus', [
      'formid' => $formid,
      'userid' => $userid,
    ]);
    $currentstatus['status'] = $formstatus->status;
    $currentstatus['date'] = $date;
    array_push($draft, $currentstatus);

    $count = 0;
    foreach ($splitsubmission as $item) {
      if (!empty($item)) {
        $formdata['name'] = $item->name;
        $formdata['label'] = $labels[$count];
        $formdata['value'] = nl2br($item->value);
        $count++;
        if (!empty($formdata)) {
          array_push($data, $formdata);
        }
      }
    }
    // File attachment form
      if($fileattach != '') {
          $subform = $DB->get_record('efb_submissionstatus', ['userid' => $userid, 'formid' => $formid]);
              if ($data = $fileattach->get_data()) {
                  // ... store or update $entry

                  file_save_draft_area_files($data->attachments, $context->id, 'local_edwiser_submission', 'attachment',
                      $subform->id, array('subdirs' => 0, 'maxbytes' => 2097152, 'maxfiles' => 50));
              }else {
                  if (empty($entry->id)) {
                      $entry = new stdClass;
                      $entry->id = $subform->id;
                  }
                  $draftitemid = file_get_submitted_draft_itemid('attachments');

                  file_prepare_draft_area($draftitemid, $context->id, 'local_edwiser_submission', 'attachment', $subform->id,
                      array('subdirs' => 0, 'maxbytes' => 2097152, 'maxfiles' => 50));

                  $entry->attachments = $draftitemid;

                  $fileattach->set_data($entry);
              }

      }

    $results->data = array_values($data);
    $results->status = array_values($draft);
    echo $OUTPUT->render_from_template('local_edwiser_submission/trainerviewstatus', $results);
    echo $OUTPUT->render_from_template('local_edwiser_submission/trainerview', $results);
      echo '<div class="mt-5">';
      $comment->output(false);
      $fileattach->display();
      echo '</div></div>';

    $formstatus = $DB->get_record('efb_submissionstatus', [
      'formid' => $formid,
      'userid' => $userid,
    ]);

    if ($formstatus && $formstatus->submission === 'Submitted') {
      $mform->set_data($toform);
      $mform->display();
    }
  }

}
echo $OUTPUT->footer();
