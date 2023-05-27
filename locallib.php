<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   local_star_instructional
 * @copyright 2022 Jennifer Aube
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
class edwiser_submission {
    /**
     * Get the current course module.
     *
     * @return cm_info|null The course module or null if not known
     */
    public function get_course_module() {
        // TODO get id
        if ($this->context->contextlevel == CONTEXT_MODULE) {
            $modinfo = get_fast_modinfo($this->get_course());
            $this->coursemodule = $modinfo->get_cm($this->context->instanceid);
            return $this->coursemodule;
        }
        return null;
    }
    /**
     * Message someone about something (static so it can be called from cron).
     *
     * @param int $userid
     * @param stdClass $userfrom
     * @param stdClass $userto
     * @param string $messagetype
     * @param string $eventtype
     * @param int $updatetime
     * @param stdClass $coursemodule
     * @param stdClass $context
     * @param stdClass $course
     * @param string $modulename
     * @param string $assignmentname
     * @param bool $blindmarking
     * @param int $uniqueidforuser
     * @param bool $trainer
     * @return void
     */
    public static function send_assignment_notification($userid,
                                                        $userfrom,
                                                        $userto,
                                                        $messagetype,
                                                        $eventtype,
                                                        $updatetime,
                                                        $coursemodule,
                                                        $context,
                                                        $course,
                                                        $modulename,
                                                        $assignmentname,
                                                        $blindmarking,
                                                        $trainer) {
        global $CFG, $PAGE, $SESSION, $DB;
        $modid = $formid = '';
        if(isset($SESSION->edwisermodid)) {
            $modid = $SESSION->edwisermodid;
        }
        if(isset($SESSION->edwisermodid)) {
            $formid = $SESSION->edwiserformid;
        }
        if(!$trainer) {
            $userid = $userfrom->id;
        } else {
            $userid = $userto->id;
        }
        $info = new stdClass();
        $info->username = fullname($userfrom, true);
        $info->assignment = format_string($assignmentname, true, array('context'=>$context));
        $info->url = $CFG->wwwroot.'/local/edwiser_submission/index.php?formid='.$formid.'&modid='.$modid.'&userid='.$userid;
        $info->timeupdated = userdate(time(), get_string('strftimerecentfull'));
        $submission = $DB->get_record('efb_submissionstatus', ['userid'=>$userid, 'formid'=>$formid]);
        $info->submissionstatus = (!empty($submission->status)) ? $submission->status : $submission->submission;
        $postsubject = get_string($messagetype . 'small', 'modulestar', $info);
        $posttext = self::format_notification_message_text($messagetype,
            $info,
            $course,
            $context,
            $modulename,
            $userid,
            $formid,
            $modid,
            $assignmentname);
        $posthtml = '';
        if ($userto->mailformat == 1) {
            $posthtml = self::format_notification_message_html($messagetype,
                $info,
                $course,
                $context,
                $modulename,
                $coursemodule,
                $formid,
                $userid,
                $assignmentname);
        }

        // STAR Toolkit noreply user account
        if(!$staruser = $DB->get_record('user', ['username' => 'startoolkit'])) {
            $staruserarry = [
              'username' => 'startoolkit',
              'firstname' => 'STAR',
              'lastname' => 'Toolkit',
              'email' => 'noreply@courses.lincs.ed.gov'
            ];
            $DB->insert_record('user', $staruserarry);
            $staruser = $DB->get_record('user', ['username' => 'startoolkit']);
        }
        $eventdata = new \core\message\message();
        $eventdata->courseid         = $course->id;
        $eventdata->modulename       = 'edwiser_submission';
        $eventdata->userfrom         = $staruser;
        $eventdata->userto           = $userto;
        $eventdata->subject          = $postsubject;
        $eventdata->fullmessage      = $posttext;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml  = $posthtml;
        $eventdata->smallmessage     = $postsubject;

        $eventdata->name            = $eventtype;
        $eventdata->component       = 'mod_modulestar';
        $eventdata->notification    = 1;
        $eventdata->contexturl      = $info->url;
        $eventdata->contexturlname  = $info->assignment;
        $customdata = [
            'cmid' => $coursemodule->id,
            'instance' => $coursemodule,
            'messagetype' => $messagetype,
            'blindmarking' => $blindmarking,
            'uniqueidforuser' => '',
        ];
        // Check if the userfrom is real and visible.
        if (!empty($userfrom->id) && core_user::is_real_user($userfrom->id)) {
            $userpicture = new user_picture($userfrom);
            $userpicture->size = 1; // Use f1 size.
            $userpicture->includetoken = $userto->id; // Generate an out-of-session token for the user receiving the message.
            $customdata['notificationiconurl'] = $userpicture->get_url($PAGE)->out(false);
        }
        $eventdata->customdata = $customdata;

        message_send($eventdata);
    }
    /**
     * Format a notification for plain text.
     *
     * @param string $messagetype
     * @param stdClass $info
     * @param stdClass $course
     * @param stdClass $context
     * @param string $modulename
     * @param string $assignmentname
     */
    protected static function format_notification_message_text($messagetype,
                                                               $info,
                                                               $course,
                                                               $context,
                                                               $modulename,
                                                               $assignmentname) {
        $posttext  = 'STAR Toolkit: '. format_string($assignmentname, true). "\n";
        $posttext .= '---------------------------------------------------------------------' . "\n";
        $posttext .= get_string($messagetype . 'text', 'modulestar', $info)."\n";
        $posttext .= "\n---------------------------------------------------------------------\n";
        return $posttext;
    }
    /**
     * Format a notification for HTML.
     *
     * @param string $messagetype
     * @param stdClass $info
     * @param stdClass $course
     * @param stdClass $context
     * @param string $modulename
     * @param stdClass $coursemodule
     * @param string $assignmentname
     */
    protected static function format_notification_message_html($messagetype,
                                                               $info,
                                                               $course,
                                                               $context,
                                                               $modulename,
                                                               $coursemodule,
                                                               $formid,
                                                               $userid,
                                                               $assignmentname) {
        global $CFG;
        $posthtml  = '<p><font face="sans-serif">' .
            '<a href="' . $CFG->wwwroot . '/course/view.php?id=' . $course->id . '">' .
            'STAR Toolkit' .
            '</a></font></p>';
        $posthtml .= '<hr /><font face="sans-serif">';
        $posthtml .= '<p>' . get_string($messagetype . 'html', 'modulestar', $info) . '</p>';
        $posthtml .= '<p><a href="' . $CFG->wwwroot . '/local/edwiser_submission/index.php?formid=' . $formid . '&userid=' . $userid . '">Click here to view the submission.</a></p>';
        $posthtml .= '</font><hr />';
        return $posthtml;
    }
    /**
     * Send notifications to graders upon student submissions.
     *
     * @param stdClass $submission
     * @param bool $trainer
     * @return void
     */
    protected function notify_graders(stdClass $submission, $trainer) {
        global $DB, $USER;
        if ($submission->userid) {
            $user = $DB->get_record('user', array('id'=>$submission->userid), '*', MUST_EXIST);
        } else {
            $user = $USER;
        }
        $formdata = $DB->get_record('efb_form_data', array('formid'=>$submission->formid, 'userid'=>$user->id));
        if ($notifyusers = $this->get_notifiable_users($user->id, $trainer)) {
            foreach ($notifyusers as $notifyuser) {

                if($trainer) {
                    $this->send_notification($submission,
                        $user,
                        $notifyuser,
                        'trainersubmissionupdated',
                        'submission_notification',
                        $formdata->date, $trainer);
                } else {
                    $this->send_notification($submission,
                        $user,
                        $notifyuser,
                        'participantsubmissionupdated',
                        'submission_notification',
                        $formdata->date, $trainer);
                }
            }
        }
    }
    /**
     * Message someone about something.
     *
     * @param stdClass $submission
     * @param stdClass $userfrom
     * @param stdClass $userto
     * @param string $messagetype
     * @param string $eventtype
     * @param int $updatetime
     * @param bool $trainer
     * @return void
     */
    public function send_notification($submission, $userfrom, $userto, $messagetype, $eventtype, $updatetime, $trainer) {
        global $USER, $DB;
        $userid = core_user::is_real_user($userfrom->id) ? $userfrom->id : $USER->id;
        $course = $DB->get_record('course', ['shortname'=>'STAR']);
        $courseid = $course->id;
        $context = context_course::instance($courseid);

        $edwiserform = $DB->get_record('efb_forms', array('id'=>$submission->formid));
        $modulename = $edwiserform->title;
        self::send_assignment_notification($submission->userid,
            $userfrom,
            $userto,
            $messagetype,
            $eventtype,
            $updatetime,
            $submission,
            $context,
            $course,
            'Edwiser submissions',
            $modulename,
            '',
            '',
            $trainer);
    }
    /**
     * Returns a list of users that should receive notification about given submission.
     *
     * @param int $userid The submission to grade
     * @param bool $trainer
     * @return array
     */
    protected function get_notifiable_users($userid, $trainer) {
        global $DB;
        $starcourse = $DB->get_record('course', ['shortname'=>'STAR']);
        $courseid = $starcourse->id;
        // Potential users should be active users only.
        $context = context_course::instance($courseid);
        $usergroup = $DB->get_record('groups_members', array('userid'=>$userid));
        $potentialusers = get_enrolled_users($context, '',
            $usergroup->groupid, 'u.*', null, null, null, true);

        $notifiabletrainers = $notifiablestudents = array();

        if($trainer) {
            foreach ($potentialusers as $potentialuser) {
                // Must be the trainer of the participants group
                $roles = $DB->get_records('role_assignments', array('userid' => $potentialuser->id));
                foreach ($roles as $r) {
                    $role = $DB->get_record('role', array('id' => $r->roleid));
                    if ($role->name === 'Student' && $potentialuser->id == $userid) {
                        $notifiablestudents[$potentialuser->id] = $potentialuser;
                    }
                }
            }
            return $notifiablestudents;
        } else {
            foreach ($potentialusers as $potentialuser) {
                if ($potentialuser->id == $userid) {
                    // Do not send self.
                    continue;
                }
                // Must be the trainer of the participants group
                $roles = $DB->get_records('role_assignments', array('userid' => $potentialuser->id));
                foreach ($roles as $r) {
                    $role = $DB->get_record('role', array('id' => $r->roleid));
                    if ($role->name === 'Teacher') {
                        $notifiabletrainers[$potentialuser->id] = $potentialuser;
                    }
                }
            }
            return $notifiabletrainers;
        }

    }
    /**
     * Get the settings from the modulestar settings
     * @param int|null $userid the id of the user to load the modulestar instance for.
     * @return stdClass The settings
     */
    public function get_instance(int $userid = null) : stdClass {
        global $USER;
        $userid = $userid ?? $USER->id;

        $this->instance = $DB->get_record('modulestar', array('name'=>'5. Challenges - Record Challenges'), '*', MUST_EXIST);

        // If we have the user instance already, just return it.
        if (isset($this->userinstances[$userid])) {
            return $this->userinstances[$userid];
        }

        // Calculate properties which vary per user.
        $this->userinstances[$userid] = $this->calculate_properties($this->instance, $userid);
        return $this->userinstances[$userid];
    }
    /**
     * Submit a submission for grading.
     *
     * @param stdClass $submission - The form data
     * @param bool $trainer - Indication for trainer status change
     * @return bool Return false if the submission was not submitted.
     */
    public function submit_for_grading($submission, $trainer) {
        global $USER, $DB;

        $this->notify_graders($submission, $trainer);
        return true;
    }
}